from abc import ABC, abstractmethod
from collections.abc import AsyncGenerator

from app.config import get_settings, LLMProvider
from app.database import SessionLocal
from app.models.settings import AppSettings

env_settings = get_settings()


def get_llm_settings() -> tuple[str, str]:
    """Get LLM provider and model from database settings.

    Returns:
        Tuple of (provider, model)
    """
    db = SessionLocal()
    try:
        settings = db.query(AppSettings).first()
        if settings:
            return settings.llm_provider, settings.chat_model
        # Defaults if no settings in database yet
        return "openai", "gpt-4o-mini"
    finally:
        db.close()


class BaseLLMService(ABC):
    """Abstract base class for LLM services."""

    @abstractmethod
    def chat(self, messages: list[dict], temperature: float = 0.7) -> str:
        """Generate a chat response."""
        pass

    @abstractmethod
    async def chat_stream(
        self, messages: list[dict], temperature: float = 0.7
    ) -> AsyncGenerator[str, None]:
        """Generate a streaming chat response."""
        pass


class OpenAILLMService(BaseLLMService):
    """OpenAI LLM service."""

    def __init__(self, model: str = "gpt-4o-mini"):
        from openai import OpenAI, AsyncOpenAI

        self.client = OpenAI(api_key=env_settings.openai_api_key)
        self.async_client = AsyncOpenAI(api_key=env_settings.openai_api_key)
        self.model = model

    def chat(self, messages: list[dict], temperature: float = 0.7) -> str:
        response = self.client.chat.completions.create(
            model=self.model,
            messages=messages,
            temperature=temperature,
        )
        return response.choices[0].message.content

    async def chat_stream(
        self, messages: list[dict], temperature: float = 0.7
    ) -> AsyncGenerator[str, None]:
        stream = await self.async_client.chat.completions.create(
            model=self.model,
            messages=messages,
            temperature=temperature,
            stream=True,
        )
        async for chunk in stream:
            if chunk.choices[0].delta.content:
                yield chunk.choices[0].delta.content


class AnthropicLLMService(BaseLLMService):
    """Anthropic Claude LLM service."""

    def __init__(self, model: str = "claude-3-haiku-20240307"):
        from anthropic import Anthropic, AsyncAnthropic

        self.client = Anthropic(api_key=env_settings.anthropic_api_key)
        self.async_client = AsyncAnthropic(api_key=env_settings.anthropic_api_key)
        self.model = model

    def chat(self, messages: list[dict], temperature: float = 0.7) -> str:
        # Convert OpenAI format to Anthropic format
        system = ""
        anthropic_messages = []

        for msg in messages:
            if msg["role"] == "system":
                system = msg["content"]
            else:
                anthropic_messages.append({"role": msg["role"], "content": msg["content"]})

        response = self.client.messages.create(
            model=self.model,
            max_tokens=4096,
            system=system,
            messages=anthropic_messages,
            temperature=temperature,
        )
        return response.content[0].text

    async def chat_stream(
        self, messages: list[dict], temperature: float = 0.7
    ) -> AsyncGenerator[str, None]:
        # Convert OpenAI format to Anthropic format
        system = ""
        anthropic_messages = []

        for msg in messages:
            if msg["role"] == "system":
                system = msg["content"]
            else:
                anthropic_messages.append({"role": msg["role"], "content": msg["content"]})

        async with self.async_client.messages.stream(
            model=self.model,
            max_tokens=4096,
            system=system,
            messages=anthropic_messages,
            temperature=temperature,
        ) as stream:
            async for text in stream.text_stream:
                yield text


class OllamaLLMService(BaseLLMService):
    """Ollama local LLM service."""

    def __init__(self, model: str = "llama2"):
        import httpx

        self.base_url = env_settings.ollama_base_url
        self.model = model
        self.client = httpx.Client(timeout=120)
        self.async_client = httpx.AsyncClient(timeout=120)

    def chat(self, messages: list[dict], temperature: float = 0.7) -> str:
        response = self.client.post(
            f"{self.base_url}/api/chat",
            json={
                "model": self.model,
                "messages": messages,
                "stream": False,
                "options": {"temperature": temperature},
            },
        )
        response.raise_for_status()
        return response.json()["message"]["content"]

    async def chat_stream(
        self, messages: list[dict], temperature: float = 0.7
    ) -> AsyncGenerator[str, None]:
        import json

        async with self.async_client.stream(
            "POST",
            f"{self.base_url}/api/chat",
            json={
                "model": self.model,
                "messages": messages,
                "stream": True,
                "options": {"temperature": temperature},
            },
        ) as response:
            async for line in response.aiter_lines():
                if line:
                    data = json.loads(line)
                    if "message" in data and "content" in data["message"]:
                        yield data["message"]["content"]


class LLMService:
    """Facade for LLM services that reads configuration from database."""

    def __init__(self, provider: str | LLMProvider | None = None, model: str | None = None):
        # If not specified, read from database
        if provider is None or model is None:
            db_provider, db_model = get_llm_settings()
            provider = provider or db_provider
            model = model or db_model

        # Normalize provider to string
        if isinstance(provider, LLMProvider):
            provider = provider.value

        if provider == "openai":
            self._service = OpenAILLMService(model=model)
        elif provider == "anthropic":
            self._service = AnthropicLLMService(model=model)
        elif provider == "ollama":
            self._service = OllamaLLMService(model=model)
        else:
            raise ValueError(f"Unknown LLM provider: {provider}")

    def chat(self, messages: list[dict], temperature: float = 0.7) -> str:
        return self._service.chat(messages, temperature)

    async def chat_stream(
        self, messages: list[dict], temperature: float = 0.7
    ) -> AsyncGenerator[str, None]:
        async for chunk in self._service.chat_stream(messages, temperature):
            yield chunk


def get_llm_service() -> LLMService:
    """Get an LLM service instance.

    Note: This creates a new instance each time to pick up any settings changes.
    """
    return LLMService()
