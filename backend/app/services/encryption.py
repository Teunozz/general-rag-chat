"""Encryption utilities for sensitive data fields."""

from functools import lru_cache
from hashlib import sha256

from cryptography.fernet import Fernet, InvalidToken

from app.config import get_settings


@lru_cache()
def get_encryption_key() -> bytes | None:
    """
    Get the encryption key from settings/environment.

    The key must be a 32-byte base64-encoded Fernet key.
    Generate with: python -c "from cryptography.fernet import Fernet; print(Fernet.generate_key().decode())"
    """
    settings = get_settings()
    if settings.encryption_key:
        return settings.encryption_key.encode()
    return None


def get_fernet() -> Fernet | None:
    """Get Fernet instance if encryption key is configured."""
    key = get_encryption_key()
    if key:
        try:
            return Fernet(key)
        except Exception:
            return None
    return None


def encrypt_field(value: str) -> str:
    """
    Encrypt a string field.

    If encryption is not configured (development mode), returns the value unchanged.
    """
    if not value:
        return value

    fernet = get_fernet()
    if not fernet:
        # Encryption not configured - return as-is (development mode)
        return value

    try:
        return fernet.encrypt(value.encode()).decode()
    except Exception:
        # If encryption fails, return original (shouldn't happen)
        return value


def decrypt_field(value: str) -> str:
    """
    Decrypt a string field.

    If encryption is not configured, returns the value unchanged.
    """
    if not value:
        return value

    fernet = get_fernet()
    if not fernet:
        # Encryption not configured (development mode)
        return value

    return fernet.decrypt(value.encode()).decode()


def hash_for_lookup(value: str) -> str:
    """
    Create a deterministic hash for lookup purposes.

    This is used for email lookups - we can't search encrypted values
    but we can search by hash.
    """
    if not value:
        return ""
    # Normalize to lowercase for consistent hashing
    return sha256(value.lower().strip().encode()).hexdigest()
