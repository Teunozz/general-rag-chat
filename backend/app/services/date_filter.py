"""Date filter utilities for temporal query constraints."""

from dataclasses import dataclass
from datetime import datetime


@dataclass
class DateFilter:
    """Date range filter for vector search queries.

    Attributes:
        start_date: Beginning of the date range (inclusive)
        end_date: End of the date range (inclusive)
        original_expression: The original temporal expression from the query (e.g., "last week")
        include_undated: Whether to include documents without a published_at date
    """

    start_date: datetime | None = None
    end_date: datetime | None = None
    original_expression: str | None = None
    include_undated: bool = True

    def is_active(self) -> bool:
        """Check if the filter has any active date constraints."""
        return self.start_date is not None or self.end_date is not None

    def to_timestamps(self) -> tuple[int | None, int | None]:
        """Convert dates to Unix timestamps for Qdrant filtering.

        Returns:
            Tuple of (start_timestamp, end_timestamp), either may be None
        """
        start_ts = int(self.start_date.timestamp()) if self.start_date else None
        end_ts = int(self.end_date.timestamp()) if self.end_date else None
        return start_ts, end_ts

    def __repr__(self) -> str:
        parts = []
        if self.original_expression:
            parts.append(f"expr={self.original_expression!r}")
        if self.start_date:
            parts.append(f"start={self.start_date.date()}")
        if self.end_date:
            parts.append(f"end={self.end_date.date()}")
        if not self.include_undated:
            parts.append("exclude_undated")
        return f"DateFilter({', '.join(parts)})"
