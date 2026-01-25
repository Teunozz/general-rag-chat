import logging
import uuid
from contextlib import asynccontextmanager

from fastapi import FastAPI, Request, Depends
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from slowapi import Limiter, _rate_limit_exceeded_handler
from slowapi.errors import RateLimitExceeded
from slowapi.util import get_remote_address
from sqlalchemy.orm import Session

from app.config import get_settings
from app.database import init_db, get_db
from app.models.settings import AppSettings
from app.api import auth, chat, sources, recaps, admin, conversations


settings = get_settings()
logger = logging.getLogger(__name__)

# Rate limiter
limiter = Limiter(key_func=get_remote_address)


@asynccontextmanager
async def lifespan(app: FastAPI):
    # Startup
    init_db()
    yield
    # Shutdown


app = FastAPI(
    title=settings.app_name,
    description=settings.app_description,
    version="0.1.0",
    lifespan=lifespan,
)

# Add rate limiter to app state
app.state.limiter = limiter
app.add_exception_handler(RateLimitExceeded, _rate_limit_exceeded_handler)

# CORS middleware - restricted methods and headers
app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://localhost:3000",
        "http://127.0.0.1:3000",
        "http://0.0.0.0:3000",
        "http://frontend:3000",
    ],
    allow_credentials=True,
    allow_methods=["GET", "POST", "PUT", "DELETE", "PATCH", "OPTIONS"],
    allow_headers=["Content-Type", "Authorization", "Accept", "Origin", "X-Requested-With"],
    expose_headers=["Content-Length"],
)


# Global exception handler - sanitize error messages
@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    import traceback

    # Generate error ID for tracking
    error_id = str(uuid.uuid4())[:8]

    # Log full details server-side
    logger.error(
        f"[{error_id}] Unhandled exception on {request.method} {request.url.path}: "
        f"{type(exc).__name__}: {exc}"
    )
    logger.error(f"[{error_id}] {traceback.format_exc()}")

    # Return generic message to client
    return JSONResponse(
        status_code=500,
        content={
            "detail": "An internal server error occurred",
            "error_id": error_id,
        },
    )

# Include routers
app.include_router(auth.router, prefix="/api/auth", tags=["Authentication"])
app.include_router(chat.router, prefix="/api/chat", tags=["Chat"])
app.include_router(conversations.router, prefix="/api/conversations", tags=["Conversations"])
app.include_router(sources.router, prefix="/api/sources", tags=["Sources"])
app.include_router(recaps.router, prefix="/api/recaps", tags=["Recaps"])
app.include_router(admin.router, prefix="/api/admin", tags=["Admin"])


@app.get("/api/health")
async def health_check():
    return {"status": "healthy", "app_name": settings.app_name}


@app.get("/api/settings/public")
async def get_public_settings(db: Session = Depends(get_db)):
    """Get public application settings for frontend."""
    # Try to get settings from database first
    db_settings = db.query(AppSettings).first()
    if db_settings:
        return {
            "app_name": db_settings.app_name,
            "app_description": db_settings.app_description,
            "primary_color": db_settings.primary_color,
            "secondary_color": db_settings.secondary_color,
        }
    # Fallback to config settings with default colors
    return {
        "app_name": settings.app_name,
        "app_description": settings.app_description,
        "primary_color": "#3B82F6",
        "secondary_color": "#1E40AF",
    }
