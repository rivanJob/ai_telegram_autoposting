DO $$
BEGIN
    IF to_regclass('jobs') IS NULL THEN
        RETURN;
    END IF;

    IF EXISTS (
        SELECT 1
        FROM information_schema.columns
        WHERE table_schema = 'public'
          AND table_name = 'jobs'
          AND column_name = 'run_at_key'
          AND data_type = 'bigint'
    ) THEN
        RETURN;
    END IF;

    ALTER TABLE jobs DROP CONSTRAINT IF EXISTS jobs_channel_id_schedule_slot_id_run_at_key_key;
    ALTER TABLE jobs DROP CONSTRAINT IF EXISTS jobs_channel_slot_run_at_key_unique;
    ALTER TABLE jobs DROP COLUMN IF EXISTS run_at_key;
    ALTER TABLE jobs ADD COLUMN run_at_key BIGINT GENERATED ALWAYS AS ((FLOOR(EXTRACT(EPOCH FROM run_at) / 60))::BIGINT) STORED;
    ALTER TABLE jobs ADD CONSTRAINT jobs_channel_slot_run_at_key_unique UNIQUE(channel_id, schedule_slot_id, run_at_key);
END $$;
