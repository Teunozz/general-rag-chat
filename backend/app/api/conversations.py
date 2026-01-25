from datetime import datetime

from fastapi import APIRouter, HTTPException, status
from pydantic import BaseModel

from app.api.deps import CurrentUser, DbSession
from app.models.conversation import Conversation, Message

router = APIRouter()


class MessageResponse(BaseModel):
    id: int
    role: str
    content: str
    sources: list | None
    created_at: datetime

    class Config:
        from_attributes = True


class ConversationListItem(BaseModel):
    id: int
    title: str | None
    source_ids: list | None
    created_at: datetime
    updated_at: datetime

    class Config:
        from_attributes = True


class ConversationResponse(BaseModel):
    id: int
    title: str | None
    source_ids: list | None
    created_at: datetime
    updated_at: datetime
    messages: list[MessageResponse]

    class Config:
        from_attributes = True


class CreateConversationRequest(BaseModel):
    title: str | None = None
    source_ids: list[int] | None = None


@router.get("", response_model=list[ConversationListItem])
async def list_conversations(current_user: CurrentUser, db: DbSession):
    """List all conversations for the current user."""
    conversations = (
        db.query(Conversation)
        .filter(Conversation.user_id == current_user.id)
        .order_by(Conversation.updated_at.desc())
        .all()
    )
    return conversations


@router.post("", response_model=ConversationListItem, status_code=status.HTTP_201_CREATED)
async def create_conversation(
    request: CreateConversationRequest,
    current_user: CurrentUser,
    db: DbSession,
):
    """Create a new conversation."""
    conversation = Conversation(
        user_id=current_user.id,
        title=request.title,
        source_ids=request.source_ids or [],
    )
    db.add(conversation)
    db.commit()
    db.refresh(conversation)
    return conversation


@router.get("/{conversation_id}", response_model=ConversationResponse)
async def get_conversation(
    conversation_id: int,
    current_user: CurrentUser,
    db: DbSession,
):
    """Get a conversation with all its messages."""
    conversation = (
        db.query(Conversation)
        .filter(Conversation.id == conversation_id, Conversation.user_id == current_user.id)
        .first()
    )
    if not conversation:
        raise HTTPException(status_code=404, detail="Conversation not found")
    return conversation


@router.delete("/{conversation_id}", status_code=status.HTTP_204_NO_CONTENT)
async def delete_conversation(
    conversation_id: int,
    current_user: CurrentUser,
    db: DbSession,
):
    """Delete a conversation and all its messages."""
    conversation = (
        db.query(Conversation)
        .filter(Conversation.id == conversation_id, Conversation.user_id == current_user.id)
        .first()
    )
    if not conversation:
        raise HTTPException(status_code=404, detail="Conversation not found")

    db.delete(conversation)
    db.commit()
    return None


@router.patch("/{conversation_id}", response_model=ConversationListItem)
async def update_conversation(
    conversation_id: int,
    request: CreateConversationRequest,
    current_user: CurrentUser,
    db: DbSession,
):
    """Update a conversation's title or source filters."""
    conversation = (
        db.query(Conversation)
        .filter(Conversation.id == conversation_id, Conversation.user_id == current_user.id)
        .first()
    )
    if not conversation:
        raise HTTPException(status_code=404, detail="Conversation not found")

    if request.title is not None:
        conversation.title = request.title
    if request.source_ids is not None:
        conversation.source_ids = request.source_ids

    db.commit()
    db.refresh(conversation)
    return conversation
