DO $$
BEGIN
    IF to_regclass('jobs') IS NULL THEN
        RETURN;
    END IF;

    ALTER TABLE jobs DROP CONSTRAINT IF EXISTS jobs_channel_id_schedule_slot_id_run_at_key_key;
    ALTER TABLE jobs DROP CONSTRAINT IF EXISTS jobs_channel_slot_run_at_key_unique;

    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'jobs'
          AND column_name = 'run_at_key'
          AND (
                data_type <> 'bigint'
                OR is_generated = 'ALWAYS'
          )
    ) THEN
        ALTER TABLE jobs DROP COLUMN run_at_key;
    END IF;

    ALTER TABLE jobs ADD COLUMN IF NOT EXISTS run_at_key BIGINT;

    UPDATE jobs
    SET run_at_key = (FLOOR(EXTRACT(EPOCH FROM (run_at AT TIME ZONE 'UTC')) / 60))::BIGINT
    WHERE run_at_key IS NULL;

    ALTER TABLE jobs ALTER COLUMN run_at_key SET NOT NULL;
    ALTER TABLE jobs ADD CONSTRAINT jobs_channel_slot_run_at_key_unique UNIQUE(channel_id, schedule_slot_id, run_at_key);
END $$;
