from datetime import datetime

from fastapi import APIRouter, HTTPException
from pydantic import BaseModel

from app.api.deps import AdminUser, CurrentUser, DbSession
from app.models.recap import Recap, RecapStatus, RecapType
from app.services.recap import get_recap_service

router = APIRouter()


class RecapResponse(BaseModel):
    id: int
    recap_type: RecapType
    status: RecapStatus
    title: str | None
    content: str | None
    summary: str | None
    period_start: datetime
    period_end: datetime
    document_count: int
    source_ids: list[int]
    error_message: str | None
    generated_at: datetime | None
    created_at: datetime

    class Config:
        from_attributes = True


class GenerateRecapRequest(BaseModel):
    recap_type: RecapType
    start_date: datetime | None = None
    end_date: datetime | None = None


@router.get("", response_model=list[RecapResponse])
async def list_recaps(
    current_user: CurrentUser,
    db: DbSession,
    recap_type: RecapType | None = None,
    limit: int = 20,
):
    """List recaps, optionally filtered by type."""
    query = db.query(Recap).filter(Recap.status == RecapStatus.READY)

    if recap_type:
        query = query.filter(Recap.recap_type == recap_type)

    recaps = query.order_by(Recap.period_end.desc()).limit(limit).all()
    return recaps


@router.get("/latest", response_model=dict)
async def get_latest_recaps(current_user: CurrentUser, db: DbSession):
    """Get the latest recap of each type."""
    result = {}

    for recap_type in RecapType:
        recap = (
            db.query(Recap)
            .filter(Recap.recap_type == recap_type, Recap.status == RecapStatus.READY)
            .order_by(Recap.period_end.desc())
            .first()
        )
        if recap:
            result[recap_type.value] = RecapResponse.model_validate(recap)

    return result


@router.get("/{recap_id}", response_model=RecapResponse)
async def get_recap(recap_id: int, current_user: CurrentUser, db: DbSession):
    """Get a specific recap."""
    recap = db.query(Recap).filter(Recap.id == recap_id).first()
    if not recap:
        raise HTTPException(status_code=404, detail="Recap not found")
    return recap


@router.post("/generate", response_model=RecapResponse)
async def generate_recap(request: GenerateRecapRequest, admin_user: AdminUser, db: DbSession):
    """Manually trigger recap generation."""
    recap_service = get_recap_service()

    try:
        recap = recap_service.generate_recap(
            db=db,
            recap_type=request.recap_type,
            start_date=request.start_date,
            end_date=request.end_date,
        )
        return recap
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))


@router.delete("/{recap_id}")
async def delete_recap(recap_id: int, admin_user: AdminUser, db: DbSession):
    """Delete a recap."""
    recap = db.query(Recap).filter(Recap.id == recap_id).first()
    if not recap:
        raise HTTPException(status_code=404, detail="Recap not found")

    db.delete(recap)
    db.commit()
    return {"message": "Recap deleted successfully"}
