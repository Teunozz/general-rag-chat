-- Migration: Add context expansion settings to app_settings table
-- Run this migration on existing databases to add the new columns

-- Add context_window_size column (default: 1)
ALTER TABLE app_settings
ADD COLUMN IF NOT EXISTS context_window_size INTEGER NOT NULL DEFAULT 1;

-- Add full_doc_score_threshold column (default: 0.85)
ALTER TABLE app_settings
ADD COLUMN IF NOT EXISTS full_doc_score_threshold FLOAT NOT NULL DEFAULT 0.85;

-- Add max_full_doc_chars column (default: 10000)
ALTER TABLE app_settings
ADD COLUMN IF NOT EXISTS max_full_doc_chars INTEGER NOT NULL DEFAULT 10000;

-- Add max_context_tokens column (default: 16000)
ALTER TABLE app_settings
ADD COLUMN IF NOT EXISTS max_context_tokens INTEGER NOT NULL DEFAULT 16000;

-- Update chat_context_chunks default to 15 (for new rows only, existing values preserved)
-- Note: This only affects the default for new rows, existing values are unchanged
-- ALTER TABLE app_settings ALTER COLUMN chat_context_chunks SET DEFAULT 15;

-- Verify the changes
SELECT column_name, data_type, column_default
FROM information_schema.columns
WHERE table_name = 'app_settings'
ORDER BY ordinal_position;
