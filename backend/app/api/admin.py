from datetime import datetime

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel, EmailStr

from app.api.deps import AdminUser, DbSession, get_password_hash
from app.models.document import Document
from app.models.recap import Recap
from app.models.settings import AppSettings
from app.models.source import Source, SourceStatus
from app.models.user import User, UserRole
from app.services.encryption import decrypt_field, encrypt_field, hash_for_lookup
from app.services.model_registry import (
    OPENAI_EMBEDDING_MODELS,
    SENTENCE_TRANSFORMER_MODELS,
    get_all_model_options,
    get_model_ids_for_provider,
    refresh_all_models,
)
from app.services.query_enrichment import DEFAULT_ENRICHMENT_PROMPT
from app.services.security import validate_password_strength
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


def _user_response_with_decrypted_fields(user: User) -> dict:
    """Create a user response with decrypted email and name fields."""
    return {
        "id": user.id,
        "email": decrypt_field(user.email),
        "name": decrypt_field(user.name) if user.name else None,
        "role": user.role,
        "is_active": user.is_active,
        "created_at": user.created_at,
    }


@router.get("/users", response_model=list[UserResponse])
async def list_users(admin_user: AdminUser, db: DbSession):
    """List all users."""
    users = db.query(User).order_by(User.created_at.desc()).all()
    return [_user_response_with_decrypted_fields(user) for user in users]


@router.post("/users", response_model=UserResponse)
async def create_user(user_data: UserCreate, admin_user: AdminUser, db: DbSession):
    """Create a new user."""
    # Validate password strength
    is_valid, error_msg = validate_password_strength(user_data.password)
    if not is_valid:
        raise HTTPException(status_code=400, detail=error_msg)

    # Check if email exists using hash lookup
    email_hash = hash_for_lookup(user_data.email)
    existing = db.query(User).filter(User.email_hash == email_hash).first()
    if existing:
        raise HTTPException(status_code=400, detail="Email already registered")

    # Create user with encrypted email and name
    user = User(
        email=encrypt_field(user_data.email),
        email_hash=email_hash,
        hashed_password=get_password_hash(user_data.password),
        name=encrypt_field(user_data.name) if user_data.name else None,
        role=user_data.role,
    )
    db.add(user)
    db.commit()
    db.refresh(user)
    return _user_response_with_decrypted_fields(user)


@router.put("/users/{user_id}", response_model=UserResponse)
async def update_user(user_id: int, user_update: UserUpdate, admin_user: AdminUser, db: DbSession):
    """Update a user."""
    user = db.query(User).filter(User.id == user_id).first()
    if not user:
        raise HTTPException(status_code=404, detail="User not found")

    # Prevent admin from modifying their own role
    if user_id == admin_user.id and user_update.role is not None:
        raise HTTPException(
            status_code=400,
            detail="Cannot modify your own role. Another admin must do this.",
        )

    update_data = user_update.model_dump(exclude_unset=True)
    for field, value in update_data.items():
        if field == "email" and value is not None:
            # Encrypt email and update hash
            user.email = encrypt_field(value)
            user.email_hash = hash_for_lookup(value)
        elif field == "name" and value is not None:
            # Encrypt name
            user.name = encrypt_field(value)
        else:
            setattr(user, field, value)

    db.commit()
    db.refresh(user)
    return _user_response_with_decrypted_fields(user)


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
    query_enrichment_enabled: bool
    query_enrichment_prompt: str | None
    # Context expansion settings
    context_window_size: int
    full_doc_score_threshold: float
    max_full_doc_chars: int
    max_context_tokens: int
    # Email notification settings
    email_notifications_enabled: bool
    email_recap_notifications_enabled: bool

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
    query_enrichment_enabled: bool | None = None
    query_enrichment_prompt: str | None = None
    # Context expansion settings
    context_window_size: int | None = None
    full_doc_score_threshold: float | None = None
    max_full_doc_chars: int | None = None
    max_context_tokens: int | None = None
    # Email notification settings
    email_notifications_enabled: bool | None = None
    email_recap_notifications_enabled: bool | None = None


# Embedding models are imported from model_registry
# Chat models are fetched dynamically from provider APIs


def validate_embedding_settings(provider: str, model: str) -> tuple[bool, str]:
    """Validate that embedding provider and model are compatible."""
    if provider == "openai":
        valid_ids = [m["id"] for m in OPENAI_EMBEDDING_MODELS]
        if model not in valid_ids:
            return (
                False,
                f"Invalid OpenAI embedding model '{model}'. Allowed: {', '.join(valid_ids)}",
            )
    elif provider == "sentence_transformers":
        valid_ids = [m["id"] for m in SENTENCE_TRANSFORMER_MODELS]
        if model not in valid_ids:
            return (
                False,
                f"Invalid Sentence Transformers model '{model}'. Allowed: {', '.join(valid_ids)}",
            )
    else:
        return (
            False,
            f"Invalid embedding provider '{provider}'. Allowed: openai, sentence_transformers",
        )
    return True, ""


def validate_llm_settings(provider: str, model: str) -> tuple[bool, str]:
    """Validate that LLM provider and model are compatible."""
    if provider == "openai":
        valid_models = get_model_ids_for_provider("openai")
        if model not in valid_models:
            return False, f"Invalid OpenAI chat model '{model}'. Allowed: {', '.join(valid_models)}"
    elif provider == "anthropic":
        valid_models = get_model_ids_for_provider("anthropic")
        if model not in valid_models:
            return (
                False,
                f"Invalid Anthropic chat model '{model}'. Allowed: {', '.join(valid_models)}",
            )
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
    """Get allowed values for settings dropdowns.

    Chat models are fetched from provider APIs and cached for 1 hour.
    """
    options = get_all_model_options()
    options["default_enrichment_prompt"] = DEFAULT_ENRICHMENT_PROMPT
    return options


@router.post("/settings/refresh-models")
async def refresh_models(admin_user: AdminUser):
    """Force refresh of available models from provider APIs."""
    return refresh_all_models()


@router.put("/settings", response_model=SettingsResponse)
async def update_settings(settings_update: SettingsUpdate, admin_user: AdminUser, db: DbSession):
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
        "embedding_provider" in update_data
        and update_data["embedding_provider"] != settings.embedding_provider
    ) or (
        "embedding_model" in update_data
        and update_data["embedding_model"] != settings.embedding_model
    )

    for field, value in update_data.items():
        setattr(settings, field, value)

    db.commit()
    db.refresh(settings)

    # Reset embedding and vector store caches if embedding settings changed
    if embedding_changed:
        from app.services.embeddings import (
            get_embedding_settings_hash,
            reset_embedding_service,
            set_cached_settings_version,
        )
        from app.services.vector_store import reset_vector_store
        from app.tasks.cache import broadcast_cache_invalidation

        # 1. Reset local caches (for API server)
        reset_embedding_service()
        reset_vector_store()

        # 2. Update Redis version key so workers detect the change
        set_cached_settings_version(get_embedding_settings_hash())

        # 3. Broadcast to workers to clear their caches immediately
        broadcast_cache_invalidation()

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
    total_chunks = (
        db.query(Source).with_entities(db.query(Source.chunk_count).scalar_subquery()).scalar() or 0
    )
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


# Email Settings
class TestEmailRequest(BaseModel):
    email: EmailStr | None = None  # If not provided, send to admin's email


class TestEmailResponse(BaseModel):
    success: bool
    message: str


@router.post("/settings/test-email", response_model=TestEmailResponse)
async def test_email(
    request: TestEmailRequest,
    admin_user: AdminUser,
    db: DbSession,
):
    """Send a test email to verify SMTP configuration."""
    from app.services.email import get_email_service
    from app.services.email_templates import render_test_email

    settings = get_or_create_settings(db)

    # Get recipient email
    if request.email:
        recipient = request.email
    else:
        # Use admin's email
        recipient = decrypt_field(admin_user.email)

    if not recipient:
        raise HTTPException(status_code=400, detail="Could not determine recipient email")

    # First test connection
    email_service = get_email_service()
    success, message = email_service.test_connection()

    if not success:
        return TestEmailResponse(success=False, message=message)

    # Render and send test email
    html, text = render_test_email(settings.app_name)
    subject = f"Test Email - {settings.app_name}"

    success = email_service.send_email(recipient, subject, html, text)

    if success:
        return TestEmailResponse(
            success=True,
            message=f"Test email sent successfully to {recipient}",
        )
    else:
        return TestEmailResponse(
            success=False,
            message="Failed to send test email. Check server logs for details.",
        )
