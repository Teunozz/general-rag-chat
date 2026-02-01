-- Migration: Add email notification settings
-- Run this migration on existing databases to add email notification columns

-- Add email notification columns to users table
ALTER TABLE users
ADD COLUMN IF NOT EXISTS email_notifications_enabled BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE users
ADD COLUMN IF NOT EXISTS email_daily_recap BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE users
ADD COLUMN IF NOT EXISTS email_weekly_recap BOOLEAN NOT NULL DEFAULT TRUE;

ALTER TABLE users
ADD COLUMN IF NOT EXISTS email_monthly_recap BOOLEAN NOT NULL DEFAULT FALSE;

-- Add email notification master switches to app_settings table
ALTER TABLE app_settings
ADD COLUMN IF NOT EXISTS email_notifications_enabled BOOLEAN NOT NULL DEFAULT FALSE;

ALTER TABLE app_settings
ADD COLUMN IF NOT EXISTS email_recap_notifications_enabled BOOLEAN NOT NULL DEFAULT FALSE;

-- Verify the changes
SELECT 'users columns:' AS info;
SELECT column_name, data_type, column_default
FROM information_schema.columns
WHERE table_name = 'users' AND column_name LIKE 'email%'
ORDER BY ordinal_position;

SELECT 'app_settings columns:' AS info;
SELECT column_name, data_type, column_default
FROM information_schema.columns
WHERE table_name = 'app_settings' AND column_name LIKE 'email%'
ORDER BY ordinal_position;
