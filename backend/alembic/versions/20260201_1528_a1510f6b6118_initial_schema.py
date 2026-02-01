"""initial schema

Revision ID: a1510f6b6118
Revises:
Create Date: 2026-02-01 15:28:22.856436

"""
from typing import Sequence, Union

from alembic import op
import sqlalchemy as sa
from sqlalchemy.dialects import postgresql

# revision identifiers, used by Alembic.
revision: str = 'a1510f6b6118'
down_revision: Union[str, None] = None
branch_labels: Union[str, Sequence[str], None] = None
depends_on: Union[str, Sequence[str], None] = None


def upgrade() -> None:
    # Create users table
    op.create_table(
        'users',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('email', sa.String(length=512), nullable=False),
        sa.Column('email_hash', sa.String(length=64), nullable=True),
        sa.Column('hashed_password', sa.String(length=255), nullable=False),
        sa.Column('name', sa.String(length=512), nullable=True),
        sa.Column('role', sa.Enum('ADMIN', 'USER', name='userrole'), nullable=False),
        sa.Column('is_active', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('email_notifications_enabled', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('email_daily_recap', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('email_weekly_recap', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('email_monthly_recap', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id'),
    )
    op.create_index(op.f('ix_users_id'), 'users', ['id'], unique=False)
    op.create_index(op.f('ix_users_email_hash'), 'users', ['email_hash'], unique=True)

    # Create sources table
    op.create_table(
        'sources',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('name', sa.String(length=255), nullable=False),
        sa.Column('description', sa.Text(), nullable=True),
        sa.Column('source_type', sa.Enum('WEBSITE', 'DOCUMENT', 'RSS', name='sourcetype'), nullable=False),
        sa.Column('status', sa.Enum('PENDING', 'PROCESSING', 'READY', 'ERROR', name='sourcestatus'), nullable=False),
        sa.Column('url', sa.String(length=2048), nullable=True),
        sa.Column('file_path', sa.String(length=1024), nullable=True),
        sa.Column('crawl_depth', sa.Integer(), nullable=True, server_default='1'),
        sa.Column('crawl_same_domain_only', sa.Boolean(), nullable=True, server_default='true'),
        sa.Column('refresh_interval_minutes', sa.Integer(), nullable=True, server_default='60'),
        sa.Column('config', sa.JSON(), nullable=True),
        sa.Column('error_message', sa.Text(), nullable=True),
        sa.Column('last_indexed_at', sa.DateTime(), nullable=True),
        sa.Column('document_count', sa.Integer(), nullable=True, server_default='0'),
        sa.Column('chunk_count', sa.Integer(), nullable=True, server_default='0'),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id'),
    )
    op.create_index(op.f('ix_sources_id'), 'sources', ['id'], unique=False)

    # Create documents table
    op.create_table(
        'documents',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('source_id', sa.Integer(), nullable=False),
        sa.Column('title', sa.String(length=512), nullable=True),
        sa.Column('url', sa.String(length=2048), nullable=True),
        sa.Column('file_path', sa.String(length=1024), nullable=True),
        sa.Column('content', sa.Text(), nullable=True),
        sa.Column('content_hash', sa.String(length=64), nullable=True),
        sa.Column('status', sa.Enum('PENDING', 'PROCESSING', 'INDEXED', 'ERROR', name='documentstatus'), nullable=False),
        sa.Column('content_type', sa.String(length=100), nullable=True),
        sa.Column('word_count', sa.Integer(), nullable=True, server_default='0'),
        sa.Column('chunk_count', sa.Integer(), nullable=True, server_default='0'),
        sa.Column('extra_data', sa.JSON(), nullable=True),
        sa.Column('error_message', sa.Text(), nullable=True),
        sa.Column('published_at', sa.DateTime(), nullable=True),
        sa.Column('indexed_at', sa.DateTime(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.ForeignKeyConstraint(['source_id'], ['sources.id']),
        sa.PrimaryKeyConstraint('id'),
    )
    op.create_index(op.f('ix_documents_id'), 'documents', ['id'], unique=False)
    op.create_index(op.f('ix_documents_content_hash'), 'documents', ['content_hash'], unique=False)

    # Create document_chunks table
    op.create_table(
        'document_chunks',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('document_id', sa.Integer(), nullable=False),
        sa.Column('chunk_index', sa.Integer(), nullable=False),
        sa.Column('content', sa.Text(), nullable=False),
        sa.Column('token_count', sa.Integer(), nullable=True, server_default='0'),
        sa.Column('vector_id', sa.String(length=64), nullable=True),
        sa.Column('extra_data', sa.JSON(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.ForeignKeyConstraint(['document_id'], ['documents.id']),
        sa.PrimaryKeyConstraint('id'),
    )
    op.create_index(op.f('ix_document_chunks_id'), 'document_chunks', ['id'], unique=False)
    op.create_index(op.f('ix_document_chunks_vector_id'), 'document_chunks', ['vector_id'], unique=False)

    # Create recaps table
    op.create_table(
        'recaps',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('recap_type', sa.Enum('DAILY', 'WEEKLY', 'MONTHLY', name='recaptype'), nullable=False),
        sa.Column('status', sa.Enum('PENDING', 'GENERATING', 'READY', 'ERROR', name='recapstatus'), nullable=False),
        sa.Column('title', sa.String(length=255), nullable=True),
        sa.Column('content', sa.Text(), nullable=True),
        sa.Column('summary', sa.Text(), nullable=True),
        sa.Column('period_start', sa.Date(), nullable=False),
        sa.Column('period_end', sa.Date(), nullable=False),
        sa.Column('document_count', sa.Integer(), nullable=True, server_default='0'),
        sa.Column('source_ids', sa.JSON(), nullable=True),
        sa.Column('extra_data', sa.JSON(), nullable=True),
        sa.Column('error_message', sa.Text(), nullable=True),
        sa.Column('generated_at', sa.DateTime(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id'),
    )
    op.create_index(op.f('ix_recaps_id'), 'recaps', ['id'], unique=False)

    # Create conversations table
    op.create_table(
        'conversations',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('user_id', sa.Integer(), nullable=False),
        sa.Column('title', sa.String(length=255), nullable=True),
        sa.Column('source_ids', postgresql.JSONB(astext_type=sa.Text()), nullable=True),
        sa.Column('summary', sa.Text(), nullable=True),
        sa.Column('summary_up_to_message_id', sa.Integer(), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.ForeignKeyConstraint(['user_id'], ['users.id']),
        sa.PrimaryKeyConstraint('id'),
    )
    op.create_index(op.f('ix_conversations_id'), 'conversations', ['id'], unique=False)
    op.create_index(op.f('ix_conversations_user_id'), 'conversations', ['user_id'], unique=False)

    # Create messages table
    op.create_table(
        'messages',
        sa.Column('id', sa.Integer(), nullable=False),
        sa.Column('conversation_id', sa.Integer(), nullable=False),
        sa.Column('role', sa.String(length=20), nullable=False),
        sa.Column('content', sa.Text(), nullable=False),
        sa.Column('sources', postgresql.JSONB(astext_type=sa.Text()), nullable=True),
        sa.Column('created_at', sa.DateTime(), nullable=True),
        sa.ForeignKeyConstraint(['conversation_id'], ['conversations.id'], ondelete='CASCADE'),
        sa.PrimaryKeyConstraint('id'),
    )
    op.create_index(op.f('ix_messages_id'), 'messages', ['id'], unique=False)
    op.create_index(op.f('ix_messages_conversation_id'), 'messages', ['conversation_id'], unique=False)

    # Create app_settings table
    op.create_table(
        'app_settings',
        sa.Column('id', sa.Integer(), nullable=False, server_default='1'),
        sa.Column('app_name', sa.String(length=255), nullable=True, server_default='RAG System'),
        sa.Column('app_description', sa.Text(), nullable=True, server_default='Your personal knowledge base'),
        sa.Column('logo_path', sa.String(length=512), nullable=True),
        sa.Column('primary_color', sa.String(length=7), nullable=True, server_default='#3B82F6'),
        sa.Column('secondary_color', sa.String(length=7), nullable=True, server_default='#1E40AF'),
        sa.Column('llm_provider', sa.String(length=50), nullable=True, server_default='openai'),
        sa.Column('chat_model', sa.String(length=100), nullable=True, server_default='gpt-4o-mini'),
        sa.Column('embedding_provider', sa.String(length=50), nullable=True, server_default='openai'),
        sa.Column('embedding_model', sa.String(length=100), nullable=True, server_default='text-embedding-3-small'),
        sa.Column('recap_enabled', sa.Boolean(), nullable=True, server_default='true'),
        sa.Column('recap_daily_enabled', sa.Boolean(), nullable=True, server_default='true'),
        sa.Column('recap_weekly_enabled', sa.Boolean(), nullable=True, server_default='true'),
        sa.Column('recap_monthly_enabled', sa.Boolean(), nullable=True, server_default='true'),
        sa.Column('recap_daily_hour', sa.Integer(), nullable=True, server_default='6'),
        sa.Column('recap_weekly_day', sa.Integer(), nullable=True, server_default='0'),
        sa.Column('recap_monthly_day', sa.Integer(), nullable=True, server_default='1'),
        sa.Column('chat_context_chunks', sa.Integer(), nullable=True, server_default='15'),
        sa.Column('chat_temperature', sa.Float(), nullable=True, server_default='0.7'),
        sa.Column('context_window_size', sa.Integer(), nullable=False, server_default='1'),
        sa.Column('full_doc_score_threshold', sa.Float(), nullable=False, server_default='0.85'),
        sa.Column('max_full_doc_chars', sa.Integer(), nullable=False, server_default='10000'),
        sa.Column('max_context_tokens', sa.Integer(), nullable=False, server_default='16000'),
        sa.Column('chat_system_prompt', sa.Text(), nullable=True),
        sa.Column('query_enrichment_enabled', sa.Boolean(), nullable=False, server_default='true'),
        sa.Column('query_enrichment_prompt', sa.Text(), nullable=True),
        sa.Column('email_notifications_enabled', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('email_recap_notifications_enabled', sa.Boolean(), nullable=False, server_default='false'),
        sa.Column('extra_config', sa.JSON(), nullable=True),
        sa.Column('updated_at', sa.DateTime(), nullable=True),
        sa.PrimaryKeyConstraint('id'),
    )


def downgrade() -> None:
    op.drop_table('app_settings')
    op.drop_index(op.f('ix_messages_conversation_id'), table_name='messages')
    op.drop_index(op.f('ix_messages_id'), table_name='messages')
    op.drop_table('messages')
    op.drop_index(op.f('ix_conversations_user_id'), table_name='conversations')
    op.drop_index(op.f('ix_conversations_id'), table_name='conversations')
    op.drop_table('conversations')
    op.drop_index(op.f('ix_recaps_id'), table_name='recaps')
    op.drop_table('recaps')
    op.drop_index(op.f('ix_document_chunks_vector_id'), table_name='document_chunks')
    op.drop_index(op.f('ix_document_chunks_id'), table_name='document_chunks')
    op.drop_table('document_chunks')
    op.drop_index(op.f('ix_documents_content_hash'), table_name='documents')
    op.drop_index(op.f('ix_documents_id'), table_name='documents')
    op.drop_table('documents')
    op.drop_index(op.f('ix_sources_id'), table_name='sources')
    op.drop_table('sources')
    op.drop_index(op.f('ix_users_email_hash'), table_name='users')
    op.drop_index(op.f('ix_users_id'), table_name='users')
    op.drop_table('users')

    # Drop enum types
    op.execute('DROP TYPE IF EXISTS userrole')
    op.execute('DROP TYPE IF EXISTS sourcetype')
    op.execute('DROP TYPE IF EXISTS sourcestatus')
    op.execute('DROP TYPE IF EXISTS documentstatus')
    op.execute('DROP TYPE IF EXISTS recaptype')
    op.execute('DROP TYPE IF EXISTS recapstatus')
