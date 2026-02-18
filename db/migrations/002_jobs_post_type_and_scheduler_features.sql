ALTER TABLE jobs
    ADD COLUMN IF NOT EXISTS post_type VARCHAR(16) NOT NULL DEFAULT 'text';

UPDATE jobs
SET post_type = 'text'
WHERE post_type IS NULL;
