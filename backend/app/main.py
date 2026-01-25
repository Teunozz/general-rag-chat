from contextlib import asynccontextmanager

from fastapi import FastAPI, Request, Depends
from fastapi.middleware.cors import CORSMiddleware
from fastapi.responses import JSONResponse
from sqlalchemy.orm import Session

from app.config import get_settings
from app.database import init_db, get_db
from app.models.settings import AppSettings
from app.api import auth, chat, sources, recaps, admin, conversations


settings = get_settings()


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

# CORS middleware
app.add_middleware(
    CORSMiddleware,
    allow_origins=[
        "http://localhost:3000",
        "http://127.0.0.1:3000",
        "http://0.0.0.0:3000",
        "http://frontend:3000",
    ],
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
    expose_headers=["*"],
)


# Global exception handler to ensure CORS headers on errors
@app.exception_handler(Exception)
async def global_exception_handler(request: Request, exc: Exception):
    import traceback
    print(f"[ERROR] Unhandled exception on {request.method} {request.url.path}:")
    print(f"[ERROR] {type(exc).__name__}: {exc}")
    traceback.print_exc()
    return JSONResponse(
        status_code=500,
        content={"detail": str(exc)},
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
