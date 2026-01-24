from datetime import datetime, timedelta
from functools import lru_cache

from sqlalchemy.orm import Session

from app.config import get_settings
from app.models.document import Document, DocumentStatus
from app.models.recap import Recap, RecapType, RecapStatus
from app.services.llm import LLMService, get_llm_service

settings = get_settings()

RECAP_SYSTEM_PROMPT = """You are a content summarizer. Your task is to create a {recap_type} recap of the following content that was added to the knowledge base.

Create a well-structured summary that:
1. Highlights the most important new information
2. Groups related topics together
3. Uses clear headings and bullet points
4. Mentions specific sources when relevant
5. Is concise but comprehensive

Format your response in Markdown."""

RECAP_USER_PROMPT = """Please create a {recap_type} recap for the period from {start_date} to {end_date}.

The following {doc_count} documents were added:

{documents}

Create a comprehensive summary of all this new content."""


class RecapService:
    """Service for generating content recaps."""

    def __init__(self, llm_service: LLMService | None = None):
        self.llm = llm_service or get_llm_service()

    def get_period_dates(self, recap_type: RecapType) -> tuple[datetime, datetime]:
        """Get start and end dates for a recap period."""
        now = datetime.utcnow()
        today = now.replace(hour=0, minute=0, second=0, microsecond=0)

        if recap_type == RecapType.DAILY:
            start = today - timedelta(days=1)
            end = today
        elif recap_type == RecapType.WEEKLY:
            # Last 7 days
            start = today - timedelta(days=7)
            end = today
        elif recap_type == RecapType.MONTHLY:
            # Last 30 days
            start = today - timedelta(days=30)
            end = today
        else:
            raise ValueError(f"Unknown recap type: {recap_type}")

        return start, end

    def get_new_documents(
        self, db: Session, start_date: datetime, end_date: datetime
    ) -> list[Document]:
        """Get documents indexed within the date range."""
        return (
            db.query(Document)
            .filter(
                Document.indexed_at >= start_date,
                Document.indexed_at < end_date,
                Document.status == DocumentStatus.INDEXED,
            )
            .order_by(Document.indexed_at.desc())
            .all()
        )

    def format_documents_for_prompt(self, documents: list[Document]) -> str:
        """Format documents for the LLM prompt."""
        formatted = []
        for doc in documents:
            parts = [f"**{doc.title or 'Untitled'}**"]
            if doc.url:
                parts.append(f"URL: {doc.url}")
            parts.append(f"Indexed: {doc.indexed_at.strftime('%Y-%m-%d %H:%M')}")

            # Include a content preview from chunks if available
            if doc.chunks:
                preview = " ".join([c.content for c in doc.chunks[:3]])[:500]
                parts.append(f"Preview: {preview}...")

            formatted.append("\n".join(parts))

        return "\n\n---\n\n".join(formatted)

    def generate_recap(
        self,
        db: Session,
        recap_type: RecapType,
        start_date: datetime | None = None,
        end_date: datetime | None = None,
    ) -> Recap:
        """Generate a recap for the specified period."""
        # Get dates
        if start_date is None or end_date is None:
            start_date, end_date = self.get_period_dates(recap_type)

        # Check if recap already exists
        existing = (
            db.query(Recap)
            .filter(
                Recap.recap_type == recap_type,
                Recap.period_start == start_date.date(),
                Recap.period_end == end_date.date(),
            )
            .first()
        )

        if existing and existing.status == RecapStatus.READY:
            return existing

        # Create or update recap
        if existing:
            recap = existing
        else:
            recap = Recap(
                recap_type=recap_type,
                period_start=start_date.date(),
                period_end=end_date.date(),
            )
            db.add(recap)

        recap.status = RecapStatus.GENERATING
        db.commit()

        try:
            # Get new documents
            documents = self.get_new_documents(db, start_date, end_date)

            if not documents:
                recap.status = RecapStatus.READY
                recap.title = f"No new content ({recap_type.value})"
                recap.content = "No new documents were added during this period."
                recap.summary = "No new content to summarize."
                recap.document_count = 0
                recap.source_ids = []
                recap.generated_at = datetime.utcnow()
                db.commit()
                return recap

            # Format documents
            doc_text = self.format_documents_for_prompt(documents)

            # Build prompt
            system = RECAP_SYSTEM_PROMPT.format(recap_type=recap_type.value)
            user_prompt = RECAP_USER_PROMPT.format(
                recap_type=recap_type.value,
                start_date=start_date.strftime("%Y-%m-%d"),
                end_date=end_date.strftime("%Y-%m-%d"),
                doc_count=len(documents),
                documents=doc_text,
            )

            messages = [
                {"role": "system", "content": system},
                {"role": "user", "content": user_prompt},
            ]

            # Generate recap
            content = self.llm.chat(messages, temperature=0.5)

            # Generate title
            title_prompt = f"Create a short, descriptive title (max 10 words) for this {recap_type.value} recap:\n\n{content[:500]}"
            title = self.llm.chat(
                [{"role": "user", "content": title_prompt}],
                temperature=0.3,
            ).strip().strip('"')

            # Generate summary (first paragraph)
            summary_prompt = f"Create a 2-3 sentence summary of this recap:\n\n{content[:1000]}"
            summary = self.llm.chat(
                [{"role": "user", "content": summary_prompt}],
                temperature=0.3,
            )

            # Update recap
            recap.status = RecapStatus.READY
            recap.title = title
            recap.content = content
            recap.summary = summary
            recap.document_count = len(documents)
            recap.source_ids = list(set(d.source_id for d in documents))
            recap.generated_at = datetime.utcnow()
            db.commit()

            return recap

        except Exception as e:
            recap.status = RecapStatus.ERROR
            recap.error_message = str(e)
            db.commit()
            raise

    def get_latest_recaps(
        self, db: Session, recap_type: RecapType | None = None, limit: int = 10
    ) -> list[Recap]:
        """Get the latest recaps."""
        query = db.query(Recap).filter(Recap.status == RecapStatus.READY)

        if recap_type:
            query = query.filter(Recap.recap_type == recap_type)

        return query.order_by(Recap.period_end.desc()).limit(limit).all()


@lru_cache
def get_recap_service() -> RecapService:
    return RecapService()
