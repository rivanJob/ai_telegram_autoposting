CREATE TABLE IF NOT EXISTS channels (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL,
    chat_id VARCHAR(128) NOT NULL UNIQUE,
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS themes (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(120) NOT NULL UNIQUE,
    description TEXT NOT NULL DEFAULT '',
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS prompts (
    id BIGSERIAL PRIMARY KEY,
    theme_id BIGINT NOT NULL REFERENCES themes(id) ON DELETE CASCADE,
    version INTEGER NOT NULL,
    title VARCHAR(200) NOT NULL,
    body TEXT NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_by BIGINT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(theme_id, version)
);

CREATE TABLE IF NOT EXISTS schedule_slots (
    id BIGSERIAL PRIMARY KEY,
    channel_id BIGINT NOT NULL REFERENCES channels(id) ON DELETE CASCADE,
    theme_id BIGINT NOT NULL REFERENCES themes(id) ON DELETE CASCADE,
    day_of_week SMALLINT NOT NULL CHECK(day_of_week BETWEEN 0 AND 6),
    slot_time VARCHAR(5) NOT NULL,
    post_type VARCHAR(16) NOT NULL DEFAULT 'text',
    prompt_override TEXT NOT NULL DEFAULT '',
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE,
    position INTEGER NOT NULL DEFAULT 0,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS schedule_campaigns (
    id BIGSERIAL PRIMARY KEY,
    slot_id BIGINT NOT NULL REFERENCES schedule_slots(id) ON DELETE CASCADE,
    starts_on DATE NOT NULL,
    ends_on DATE NOT NULL,
    prompt_override TEXT NOT NULL DEFAULT '',
    post_type_override VARCHAR(16) NULL,
    is_enabled BOOLEAN NOT NULL DEFAULT TRUE
);

CREATE TABLE IF NOT EXISTS schedule_exceptions (
    id BIGSERIAL PRIMARY KEY,
    slot_id BIGINT NOT NULL REFERENCES schedule_slots(id) ON DELETE CASCADE,
    exception_date DATE NOT NULL,
    reason TEXT NOT NULL DEFAULT '',
    UNIQUE(slot_id, exception_date)
);

CREATE TABLE IF NOT EXISTS jobs (
    id BIGSERIAL PRIMARY KEY,
    channel_id BIGINT NOT NULL REFERENCES channels(id) ON DELETE RESTRICT,
    theme_id BIGINT NOT NULL REFERENCES themes(id) ON DELETE RESTRICT,
    schedule_slot_id BIGINT NULL REFERENCES schedule_slots(id) ON DELETE SET NULL,
    run_at TIMESTAMPTZ NOT NULL,
    run_at_key TIMESTAMPTZ GENERATED ALWAYS AS (date_trunc('minute', run_at)) STORED,
    status VARCHAR(16) NOT NULL CHECK(status IN ('NEW','RUNNING','DONE','ERROR')),
    attempts INTEGER NOT NULL DEFAULT 0,
    prompt_snapshot TEXT NOT NULL DEFAULT '',
    parsed_payload JSONB NULL,
    llm_raw TEXT NULL,
    telegram_raw JSONB NULL,
    last_error TEXT NULL,
    started_at TIMESTAMPTZ NULL,
    finished_at TIMESTAMPTZ NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    UNIQUE(channel_id, schedule_slot_id, run_at_key)
);

CREATE TABLE IF NOT EXISTS admin_users (
    id BIGSERIAL PRIMARY KEY,
    email VARCHAR(200) NOT NULL UNIQUE,
    password_hash VARCHAR(255) NOT NULL,
    totp_secret VARCHAR(64) NOT NULL,
    must_setup_totp BOOLEAN NOT NULL DEFAULT TRUE,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS admin_sessions (
    id BIGSERIAL PRIMARY KEY,
    admin_user_id BIGINT NOT NULL REFERENCES admin_users(id) ON DELETE CASCADE,
    session_id VARCHAR(128) NOT NULL,
    ip VARCHAR(45) NULL,
    user_agent TEXT NULL,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    expires_at TIMESTAMPTZ NOT NULL
);

CREATE TABLE IF NOT EXISTS audit_log (
    id BIGSERIAL PRIMARY KEY,
    admin_user_id BIGINT NULL REFERENCES admin_users(id) ON DELETE SET NULL,
    action VARCHAR(150) NOT NULL,
    status VARCHAR(16) NOT NULL,
    ip VARCHAR(45),
    user_agent TEXT,
    meta JSONB NOT NULL DEFAULT '{}'::jsonb,
    created_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS settings (
    key VARCHAR(100) PRIMARY KEY,
    value JSONB NOT NULL,
    updated_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
);

CREATE TABLE IF NOT EXISTS rate_limits (
    scope VARCHAR(50) NOT NULL,
    key_hash VARCHAR(64) NOT NULL,
    attempts INTEGER NOT NULL DEFAULT 0,
    last_attempt_at TIMESTAMPTZ NOT NULL DEFAULT NOW(),
    PRIMARY KEY(scope, key_hash)
);

CREATE INDEX IF NOT EXISTS idx_jobs_status_runat ON jobs(status, run_at);
CREATE INDEX IF NOT EXISTS idx_slots_day_time ON schedule_slots(day_of_week, slot_time);
CREATE INDEX IF NOT EXISTS idx_audit_created_at ON audit_log(created_at DESC);
