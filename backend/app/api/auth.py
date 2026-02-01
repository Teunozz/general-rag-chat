from datetime import timedelta
from typing import Annotated

from fastapi import APIRouter, Depends, HTTPException, Request, status
from fastapi.security import OAuth2PasswordRequestForm
from pydantic import BaseModel, EmailStr
from slowapi import Limiter
from slowapi.util import get_remote_address
from sqlalchemy import text

from app.api.deps import (
    CurrentUser,
    DbSession,
    authenticate_user,
    create_access_token,
    get_password_hash,
    get_user_by_email,
)
from app.config import get_settings
from app.models.user import User, UserRole
from app.services.encryption import decrypt_field, encrypt_field, hash_for_lookup
from app.services.security import validate_password_strength

router = APIRouter()
settings = get_settings()
limiter = Limiter(key_func=get_remote_address)


class Token(BaseModel):
    access_token: str
    token_type: str


class UserCreate(BaseModel):
    email: EmailStr
    password: str
    name: str | None = None


class UserResponse(BaseModel):
    id: int
    email: str
    name: str | None
    role: UserRole
    is_active: bool
    # Email notification preferences
    email_notifications_enabled: bool
    email_daily_recap: bool
    email_weekly_recap: bool
    email_monthly_recap: bool

    class Config:
        from_attributes = True


class UserUpdate(BaseModel):
    name: str | None = None
    email: EmailStr | None = None
    # Email notification preferences
    email_notifications_enabled: bool | None = None
    email_daily_recap: bool | None = None
    email_weekly_recap: bool | None = None
    email_monthly_recap: bool | None = None


class PasswordChange(BaseModel):
    current_password: str
    new_password: str


def _user_response_with_decrypted_fields(user: User) -> dict:
    """Create a user response with decrypted email and name fields."""
    return {
        "id": user.id,
        "email": decrypt_field(user.email),
        "name": decrypt_field(user.name) if user.name else None,
        "role": user.role,
        "is_active": user.is_active,
        "email_notifications_enabled": user.email_notifications_enabled,
        "email_daily_recap": user.email_daily_recap,
        "email_weekly_recap": user.email_weekly_recap,
        "email_monthly_recap": user.email_monthly_recap,
    }


@router.post("/login", response_model=Token)
@limiter.limit("5/minute")
async def login(
    request: Request,
    form_data: Annotated[OAuth2PasswordRequestForm, Depends()],
    db: DbSession,
):
    """Login and get access token."""
    user = authenticate_user(db, form_data.username, form_data.password)
    if not user:
        raise HTTPException(
            status_code=status.HTTP_401_UNAUTHORIZED,
            detail="Incorrect email or password",
            headers={"WWW-Authenticate": "Bearer"},
        )
    access_token_expires = timedelta(minutes=settings.access_token_expire_minutes)
    # Use the email hash for token subject (stable identifier for lookups)
    # Use decrypted email in token for display purposes
    decrypted_email = decrypt_field(user.email)
    access_token = create_access_token(
        data={"sub": decrypted_email, "user_id": user.id},
        expires_delta=access_token_expires,
    )
    return Token(access_token=access_token, token_type="bearer")


@router.post("/register", response_model=UserResponse)
@limiter.limit("3/minute")
async def register(request: Request, user_data: UserCreate, db: DbSession):
    """Register a new user."""
    # Validate password strength
    is_valid, error_msg = validate_password_strength(user_data.password)
    if not is_valid:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=error_msg,
        )

    # Check if user already exists
    existing_user = get_user_by_email(db, user_data.email)
    if existing_user:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Email already registered",
        )

    # Use advisory lock to prevent race condition when checking for first user
    # This ensures only one transaction can check/create the first admin at a time
    db.execute(text("SELECT pg_advisory_xact_lock(1)"))

    # Check if this is the first user (make them admin)
    user_count = db.query(User).count()
    role = UserRole.ADMIN if user_count == 0 else UserRole.USER

    # Create user with encrypted email and name
    user = User(
        email=encrypt_field(user_data.email),
        email_hash=hash_for_lookup(user_data.email),
        hashed_password=get_password_hash(user_data.password),
        name=encrypt_field(user_data.name) if user_data.name else None,
        role=role,
    )
    db.add(user)
    db.commit()
    db.refresh(user)

    # Return user with decrypted fields for response
    return _user_response_with_decrypted_fields(user)


@router.get("/me", response_model=UserResponse)
async def get_current_user_info(current_user: CurrentUser):
    """Get current user information."""
    return _user_response_with_decrypted_fields(current_user)


@router.put("/me", response_model=UserResponse)
async def update_current_user(
    user_update: UserUpdate,
    current_user: CurrentUser,
    db: DbSession,
):
    """Update current user information."""
    if user_update.name is not None:
        # Encrypt the name before storing
        current_user.name = encrypt_field(user_update.name)
    if user_update.email is not None:
        # Check if email is taken
        existing = get_user_by_email(db, user_update.email)
        if existing and existing.id != current_user.id:
            raise HTTPException(
                status_code=status.HTTP_400_BAD_REQUEST,
                detail="Email already in use",
            )
        # Encrypt email and update hash for lookups
        current_user.email = encrypt_field(user_update.email)
        current_user.email_hash = hash_for_lookup(user_update.email)

    # Update email notification preferences
    if user_update.email_notifications_enabled is not None:
        current_user.email_notifications_enabled = user_update.email_notifications_enabled
    if user_update.email_daily_recap is not None:
        current_user.email_daily_recap = user_update.email_daily_recap
    if user_update.email_weekly_recap is not None:
        current_user.email_weekly_recap = user_update.email_weekly_recap
    if user_update.email_monthly_recap is not None:
        current_user.email_monthly_recap = user_update.email_monthly_recap

    db.commit()
    db.refresh(current_user)
    return _user_response_with_decrypted_fields(current_user)


@router.post("/change-password")
async def change_password(
    password_data: PasswordChange,
    current_user: CurrentUser,
    db: DbSession,
):
    """Change current user's password."""
    from app.api.deps import verify_password

    if not verify_password(password_data.current_password, current_user.hashed_password):
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail="Incorrect current password",
        )

    # Validate new password strength
    is_valid, error_msg = validate_password_strength(password_data.new_password)
    if not is_valid:
        raise HTTPException(
            status_code=status.HTTP_400_BAD_REQUEST,
            detail=error_msg,
        )

    current_user.hashed_password = get_password_hash(password_data.new_password)
    db.commit()
    return {"message": "Password changed successfully"}
