from datetime import datetime

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, EmailStr

from app.api.deps import AdminUser, DbSession, get_password_hash
from app.models.user import User, UserRole
from app.models.source import Source, SourceStatus
from app.models.document import Document
from app.models.recap import Recap
from app.models.settings import AppSettings
from app.services.vector_store import get_vector_store

router = APIRouter()


# User Management
class UserCreate(BaseModel):
    email: EmailStr
    password: str
    name: str | None = None
    role: UserRole = UserRole.USER


class UserUpdate(BaseModel):
    email: EmailStr | None = None
    name: str | None = None
    role: UserRole | None = None
    is_active: bool | None = None


class UserResponse(BaseModel):
    id: int
    email: str
    name: str | None
    role: UserRole
    is_active: bool
    created_at: datetime

    class Config:
        from_attributes = True


@router.get("/users", response_model=list[UserResponse])
async def list_users(admin_user: AdminUser, db: DbSession):
    """List all users."""
    users = db.query(User).order_by(User.created_at.desc()).all()
    return users


@router.post("/users", response_model=UserResponse)
async def create_user(user_data: UserCreate, admin_user: AdminUser, db: DbSession):
    """Create a new user."""
    # Check if email exists
    existing = db.query(User).filter(User.email == user_data.email).first()
    if existing:
        raise HTTPException(status_code=400, detail="Email already registered")

    user = User(
        email=user_data.email,
        hashed_password=get_password_hash(user_data.password),
        name=user_data.name,
        role=user_data.role,
    )
    db.add(user)
    db.commit()
    db.refresh(user)
    return user


@router.put("/users/{user_id}", response_model=UserResponse)
async def update_user(
    user_id: int, user_update: UserUpdate, admin_user: AdminUser, db: DbSession
):
    """Update a user."""
    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise HTTPException(status_code=404, detail="User not found")

    update_data = user_update.model_dump(exclude_unset=True)
    for field, value in update_data.items():
        setattr(user, field, value)

    db.commit()
    db.refresh(user)
    return user


@router.delete("/users/{user_id}")
async def delete_user(user_id: int, admin_user: AdminUser, db: DbSession):
    """Delete a user."""
    if user_id == admin_user.id:
        raise HTTPException(status_code=400, detail="Cannot delete yourself")

    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise HTTPException(status_code=404, detail="User not found")

    db.delete(user)
    db.commit()
    return {"message": "User deleted successfully"}


# Settings Management
class SettingsResponse(BaseModel):
    app_name: str
    app_description: str
    logo_path: str | None
    primary_color: str
    secondary_color: str
    llm_provider: str
    chat_model: str
    embedding_provider: str
    embedding_model: str
    recap_enabled: bool
    recap_daily_enabled: bool
    recap_weekly_enabled: bool
    recap_monthly_enabled: bool
    recap_daily_hour: int
    recap_weekly_day: int
    recap_monthly_day: int
    chat_context_chunks: int
    chat_temperature: float
    chat_system_prompt: str

    class Config:
        from_attributes = True


class SettingsUpdate(BaseModel):
    app_name: str | None = None
    app_description: str | None = None
    primary_color: str | None = None
    secondary_color: str | None = None
    llm_provider: str | None = None
    chat_model: str | None = None
    embedding_provider: str | None = None
    embedding_model: str | None = None
    recap_enabled: bool | None = None
    recap_daily_enabled: bool | None = None
    recap_weekly_enabled: bool | None = None
    recap_monthly_enabled: bool | None = None
    recap_daily_hour: int | None = None
    recap_weekly_day: int | None = None
    recap_monthly_day: int | None = None
    chat_context_chunks: int | None = None
    chat_temperature: float | None = None
    chat_system_prompt: str | None = None


# Allowed models per provider
OPENAI_EMBEDDING_MODELS = [
    "text-embedding-3-small",
    "text-embedding-3-large",
    "text-embedding-ada-002",
]
SENTENCE_TRANSFORMER_MODELS = [
    "all-MiniLM-L6-v2",
    "all-mpnet-base-v2",
    "paraphrase-MiniLM-L6-v2",
    "all-MiniLM-L12-v2",
    "multi-qa-MiniLM-L6-cos-v1",
]
OPENAI_CHAT_MODELS = [
    "gpt-4o",
    "gpt-4o-mini",
    "gpt-4-turbo",
    "gpt-4",
    "gpt-3.5-turbo",
]
ANTHROPIC_CHAT_MODELS = [
    "claude-3-5-sonnet-20241022",
    "claude-3-5-haiku-20241022",
    "claude-3-opus-20240229",
    "claude-3-sonnet-20240229",
    "claude-3-haiku-20240307",
]


def validate_embedding_settings(provider: str, model: str) -> tuple[bool, str]:
    """Validate that embedding provider and model are compatible."""
    if provider == "openai":
        if model not in OPENAI_EMBEDDING_MODELS:
            return False, f"Invalid OpenAI embedding model '{model}'. Allowed: {', '.join(OPENAI_EMBEDDING_MODELS)}"
    elif provider == "sentence_transformers":
        if model not in SENTENCE_TRANSFORMER_MODELS:
            return False, f"Invalid Sentence Transformers model '{model}'. Allowed: {', '.join(SENTENCE_TRANSFORMER_MODELS)}"
    else:
        return False, f"Invalid embedding provider '{provider}'. Allowed: openai, sentence_transformers"
    return True, ""


def validate_llm_settings(provider: str, model: str) -> tuple[bool, str]:
    """Validate that LLM provider and model are compatible."""
    if provider == "openai":
        if model not in OPENAI_CHAT_MODELS:
            return False, f"Invalid OpenAI chat model '{model}'. Allowed: {', '.join(OPENAI_CHAT_MODELS)}"
    elif provider == "anthropic":
        if model not in ANTHROPIC_CHAT_MODELS:
            return False, f"Invalid Anthropic chat model '{model}'. Allowed: {', '.join(ANTHROPIC_CHAT_MODELS)}"
    elif provider == "ollama":
        # Ollama models are user-defined, so we allow any non-empty string
        if not model or not model.strip():
            return False, "Ollama model name cannot be empty"
    else:
        return False, f"Invalid LLM provider '{provider}'. Allowed: openai, anthropic, ollama"
    return True, ""


def get_or_create_settings(db) -> AppSettings:
    """Get or create app settings singleton."""
    settings = db.query(AppSettings).first()
    if not settings:
        settings = AppSettings()
        db.add(settings)
        db.commit()
        db.refresh(settings)
    return settings


@router.get("/settings", response_model=SettingsResponse)
async def get_settings(admin_user: AdminUser, db: DbSession):
    """Get application settings."""
    settings = get_or_create_settings(db)
    return settings


@router.get("/settings/options")
async def get_settings_options(admin_user: AdminUser):
    """Get allowed values for settings dropdowns."""
    return {
        "llm_providers": ["openai", "anthropic", "ollama"],
        "embedding_providers": ["openai", "sentence_transformers"],
        "openai_chat_models": OPENAI_CHAT_MODELS,
        "anthropic_chat_models": ANTHROPIC_CHAT_MODELS,
        "openai_embedding_models": OPENAI_EMBEDDING_MODELS,
        "sentence_transformer_models": SENTENCE_TRANSFORMER_MODELS,
    }


@router.put("/settings", response_model=SettingsResponse)
async def update_settings(
    settings_update: SettingsUpdate, admin_user: AdminUser, db: DbSession
):
    """Update application settings."""
    settings = get_or_create_settings(db)

    update_data = settings_update.model_dump(exclude_unset=True)

    # Validate embedding provider/model compatibility
    new_embed_provider = update_data.get("embedding_provider", settings.embedding_provider)
    new_embed_model = update_data.get("embedding_model", settings.embedding_model)
    is_valid, error_msg = validate_embedding_settings(new_embed_provider, new_embed_model)
    if not is_valid:
        raise HTTPException(status_code=400, detail=error_msg)

    # Validate LLM provider/model compatibility
    new_llm_provider = update_data.get("llm_provider", settings.llm_provider)
    new_llm_model = update_data.get("chat_model", settings.chat_model)
    is_valid, error_msg = validate_llm_settings(new_llm_provider, new_llm_model)
    if not is_valid:
        raise HTTPException(status_code=400, detail=error_msg)

    # Check if embedding settings are changing
    embedding_changed = (
        ("embedding_provider" in update_data and update_data["embedding_provider"] != settings.embedding_provider)
        or ("embedding_model" in update_data and update_data["embedding_model"] != settings.embedding_model)
    )

    for field, value in update_data.items():
        setattr(settings, field, value)

    db.commit()
    db.refresh(settings)

    # Reset embedding service cache if embedding settings changed
    if embedding_changed:
        from app.services.embeddings import reset_embedding_service
        reset_embedding_service()

    return settings


# Dashboard Stats
class DashboardStats(BaseModel):
    total_sources: int
    sources_by_status: dict[str, int]
    total_documents: int
    total_chunks: int
    total_recaps: int
    total_users: int
    vector_store_info: dict


@router.get("/stats", response_model=DashboardStats)
async def get_dashboard_stats(admin_user: AdminUser, db: DbSession):
    """Get dashboard statistics."""
    # Source stats
    total_sources = db.query(Source).count()
    sources_by_status = {}
    for status in SourceStatus:
        count = db.query(Source).filter(Source.status == status).count()
        sources_by_status[status.value] = count

    # Document stats
    total_documents = db.query(Document).count()

    # Calculate total chunks from all sources
    total_chunks = db.query(Source).with_entities(
        db.query(Source.chunk_count).scalar_subquery()
    ).scalar() or 0
    # Actually sum the chunk counts
    from sqlalchemy import func
    total_chunks = db.query(func.sum(Source.chunk_count)).scalar() or 0

    # Recap stats
    total_recaps = db.query(Recap).count()

    # User stats
    total_users = db.query(User).count()

    # Vector store info
    vector_store = get_vector_store()
    vector_store_info = vector_store.get_collection_info()

    return DashboardStats(
        total_sources=total_sources,
        sources_by_status=sources_by_status,
        total_documents=total_documents,
        total_chunks=total_chunks,
        total_recaps=total_recaps,
        total_users=total_users,
        vector_store_info=vector_store_info,
    )
