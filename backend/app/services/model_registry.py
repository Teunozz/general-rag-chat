"""
Service for fetching and caching available models from LLM providers.
"""

import json
import logging
from datetime import datetime

import httpx
import redis

from app.config import get_settings

logger = logging.getLogger(__name__)
settings = get_settings()

# Cache TTL in seconds (1 hour)
CACHE_TTL = 3600

# Redis key prefixes
ANTHROPIC_MODELS_KEY = "models:anthropic:chat"
OPENAI_CHAT_MODELS_KEY = "models:openai:chat"
MODELS_LAST_UPDATED_KEY = "models:last_updated"

# Fallback models if API fetch fails
FALLBACK_ANTHROPIC_MODELS = [
    {"id": "claude-sonnet-4-20250514", "display_name": "Claude Sonnet 4"},
    {"id": "claude-3-5-sonnet-20241022", "display_name": "Claude 3.5 Sonnet"},
    {"id": "claude-3-5-haiku-20241022", "display_name": "Claude 3.5 Haiku"},
    {"id": "claude-3-opus-20240229", "display_name": "Claude 3 Opus"},
    {"id": "claude-3-haiku-20240307", "display_name": "Claude 3 Haiku"},
]

FALLBACK_OPENAI_MODELS = [
    {"id": "gpt-4o", "display_name": "GPT-4o"},
    {"id": "gpt-4o-mini", "display_name": "GPT-4o Mini"},
    {"id": "gpt-4-turbo", "display_name": "GPT-4 Turbo"},
    {"id": "gpt-4", "display_name": "GPT-4"},
    {"id": "gpt-3.5-turbo", "display_name": "GPT-3.5 Turbo"},
]

# Embedding models (these don't have a discovery API, keep static)
# max_tokens is the model's effective context window for generating quality embeddings
OPENAI_EMBEDDING_MODELS = [
    {"id": "text-embedding-3-small", "max_tokens": 8191},
    {"id": "text-embedding-3-large", "max_tokens": 8191},
    {"id": "text-embedding-ada-002", "max_tokens": 8191},
]

SENTENCE_TRANSFORMER_MODELS = [
    {"id": "all-MiniLM-L6-v2", "max_tokens": 256},
    {"id": "all-mpnet-base-v2", "max_tokens": 384},
    {"id": "paraphrase-MiniLM-L6-v2", "max_tokens": 256},
    {"id": "all-MiniLM-L12-v2", "max_tokens": 256},
    {"id": "multi-qa-MiniLM-L6-cos-v1", "max_tokens": 512},
]

# Default max tokens if model not found
DEFAULT_EMBEDDING_MAX_TOKENS = 256


def get_redis_client() -> redis.Redis:
    """Get a Redis client instance."""
    return redis.from_url(settings.redis_url, decode_responses=True)


def fetch_anthropic_models() -> list[dict]:
    """Fetch available models from Anthropic API."""
    if not settings.anthropic_api_key:
        logger.warning("Anthropic API key not configured, using fallback models")
        return FALLBACK_ANTHROPIC_MODELS

    try:
        with httpx.Client(timeout=10) as client:
            response = client.get(
                "https://api.anthropic.com/v1/models",
                headers={
                    "x-api-key": settings.anthropic_api_key,
                    "anthropic-version": "2023-06-01",
                },
            )
            response.raise_for_status()
            data = response.json()

            models = []
            for model in data.get("data", []):
                model_id = model.get("id", "")
                # Filter to only include chat models (claude-*)
                if model_id.startswith("claude-"):
                    models.append(
                        {
                            "id": model_id,
                            "display_name": model.get("display_name", model_id),
                        }
                    )

            if models:
                # Sort by created_at descending (newest first) if available
                return models

            logger.warning("No Anthropic models returned from API, using fallback")
            return FALLBACK_ANTHROPIC_MODELS

    except Exception as e:
        logger.error(f"Failed to fetch Anthropic models: {e}")
        return FALLBACK_ANTHROPIC_MODELS


def fetch_openai_models() -> list[dict]:
    """Fetch available chat models from OpenAI API."""
    if not settings.openai_api_key:
        logger.warning("OpenAI API key not configured, using fallback models")
        return FALLBACK_OPENAI_MODELS

    try:
        with httpx.Client(timeout=10) as client:
            response = client.get(
                "https://api.openai.com/v1/models",
                headers={
                    "Authorization": f"Bearer {settings.openai_api_key}",
                },
            )
            response.raise_for_status()
            data = response.json()

            # Filter to only chat models (gpt-*)
            chat_model_prefixes = ("gpt-4", "gpt-3.5", "o1", "o3")
            models = []
            seen_ids = set()

            for model in data.get("data", []):
                model_id = model.get("id", "")
                # Only include chat-capable models, exclude fine-tuned and instruct variants
                if (
                    model_id.startswith(chat_model_prefixes)
                    and not model_id.endswith("-instruct")
                    and ":ft-" not in model_id
                    and model_id not in seen_ids
                ):
                    seen_ids.add(model_id)
                    # Create a display name
                    display_name = model_id.upper().replace("-", " ").replace("GPT ", "GPT-")
                    models.append(
                        {
                            "id": model_id,
                            "display_name": display_name,
                        }
                    )

            if models:
                # Sort to put most common models first
                priority = ["gpt-4o", "gpt-4o-mini", "gpt-4-turbo", "gpt-4", "gpt-3.5-turbo"]

                def sort_key(m):
                    try:
                        return priority.index(m["id"])
                    except ValueError:
                        return len(priority)

                models.sort(key=sort_key)
                return models

            logger.warning("No OpenAI chat models returned from API, using fallback")
            return FALLBACK_OPENAI_MODELS

    except Exception as e:
        logger.error(f"Failed to fetch OpenAI models: {e}")
        return FALLBACK_OPENAI_MODELS


def get_cached_models(key: str) -> list[dict] | None:
    """Get cached models from Redis."""
    try:
        client = get_redis_client()
        data = client.get(key)
        if data:
            return json.loads(data)
    except Exception as e:
        logger.error(f"Failed to read from Redis cache: {e}")
    return None


def set_cached_models(key: str, models: list[dict]) -> None:
    """Store models in Redis cache with TTL."""
    try:
        client = get_redis_client()
        client.setex(key, CACHE_TTL, json.dumps(models))
    except Exception as e:
        logger.error(f"Failed to write to Redis cache: {e}")


def get_anthropic_chat_models(force_refresh: bool = False) -> list[dict]:
    """Get Anthropic chat models, using cache if available."""
    if not force_refresh:
        cached = get_cached_models(ANTHROPIC_MODELS_KEY)
        if cached:
            return cached

    models = fetch_anthropic_models()
    set_cached_models(ANTHROPIC_MODELS_KEY, models)
    return models


def get_openai_chat_models(force_refresh: bool = False) -> list[dict]:
    """Get OpenAI chat models, using cache if available."""
    if not force_refresh:
        cached = get_cached_models(OPENAI_CHAT_MODELS_KEY)
        if cached:
            return cached

    models = fetch_openai_models()
    set_cached_models(OPENAI_CHAT_MODELS_KEY, models)
    return models


def refresh_all_models() -> dict:
    """Refresh all provider models and return the updated lists."""
    anthropic_models = fetch_anthropic_models()
    openai_models = fetch_openai_models()

    set_cached_models(ANTHROPIC_MODELS_KEY, anthropic_models)
    set_cached_models(OPENAI_CHAT_MODELS_KEY, openai_models)

    # Store last updated timestamp
    try:
        client = get_redis_client()
        client.set(MODELS_LAST_UPDATED_KEY, datetime.utcnow().isoformat())
    except Exception as e:
        logger.error(f"Failed to store last updated timestamp: {e}")

    return {
        "anthropic_chat_models": anthropic_models,
        "openai_chat_models": openai_models,
    }


def get_last_updated() -> str | None:
    """Get the timestamp when models were last refreshed."""
    try:
        client = get_redis_client()
        return client.get(MODELS_LAST_UPDATED_KEY)
    except Exception as e:
        logger.error(f"Failed to get last updated timestamp: {e}")
    return None


def get_all_model_options(force_refresh: bool = False) -> dict:
    """Get all model options for the settings page."""
    anthropic_models = get_anthropic_chat_models(force_refresh)
    openai_models = get_openai_chat_models(force_refresh)

    return {
        "llm_providers": ["openai", "anthropic", "ollama"],
        "embedding_providers": ["openai", "sentence_transformers"],
        "openai_chat_models": openai_models,
        "anthropic_chat_models": anthropic_models,
        "openai_embedding_models": [m["id"] for m in OPENAI_EMBEDDING_MODELS],
        "sentence_transformer_models": [m["id"] for m in SENTENCE_TRANSFORMER_MODELS],
        "last_updated": get_last_updated(),
    }


def get_model_ids_for_provider(provider: str) -> list[str]:
    """Get list of valid model IDs for a provider."""
    if provider == "openai":
        return [m["id"] for m in get_openai_chat_models()]
    elif provider == "anthropic":
        return [m["id"] for m in get_anthropic_chat_models()]
    return []


def get_embedding_model_max_tokens(provider: str, model: str) -> int:
    """Get the max tokens for an embedding model.

    Args:
        provider: The embedding provider (openai, sentence_transformers)
        model: The model ID

    Returns:
        The max token limit for the model
    """
    if provider == "openai":
        models = OPENAI_EMBEDDING_MODELS
    elif provider == "sentence_transformers":
        models = SENTENCE_TRANSFORMER_MODELS
    else:
        return DEFAULT_EMBEDDING_MAX_TOKENS

    for m in models:
        if m["id"] == model:
            return m["max_tokens"]

    return DEFAULT_EMBEDDING_MAX_TOKENS
