# AUTPOSTER SYSTEM — GLOBAL RULES
Version: 1.1
Status: CANONICAL

This document is the SINGLE SOURCE OF TRUTH for the AutoPoster system.

---

# 1. PURPOSE

Build a fully autonomous content autoposting platform with:
- Grok integration (via external API)
- Telegram publishing (via Bot API)
- Full-featured admin web interface
- Job-based scheduler/worker architecture
- VPN egress control: Grok requests MUST go via WireGuard

System must support:
- automated generation and posting 24/7
- configurable prompts per theme/slot
- flexible schedule by day/time/date ranges
- multiple post types: text/photo/video/album/card
- strict JSON contract from LLM
- robust security and auditability
- zero manual intervention after configuration

---

# 2. CORE PRINCIPLES

## 2.1 Configuration-driven (no hardcoding)
All behavior MUST be configurable via admin panel:
- channels
- themes
- prompts
- schedules (day/time/date ranges)
- enable/disable flags
- timezone
- posting limits
- retries, backoff, quiet hours

Config may be stored in DB. File-based config is allowed only for bootstrap.
After admin is enabled, DB becomes the source of truth.

## 2.2 Strict JSON Contract (LLM)
LLM must return ONLY JSON. No markdown, no wrappers, no extra text.
Invalid JSON MUST be rejected and job marked ERROR with preserved raw response.

## 2.3 Job-based processing
Every generation/posting is a Job.
Job states: NEW → RUNNING → DONE | ERROR
No direct publishing without a job record.

## 2.4 Idempotency
Scheduler MUST not create duplicates for same (channel_id, slot_id, scheduled_at).
Worker MUST be safe to retry.

## 2.5 Separation of concerns
Modules:
- Scheduler
- Worker
- Grok Client
- Telegram Client
- Media Handler (optional)
- Storage layer (PostgreSQL)
- Admin panel (UI + API)
- Security layer
- Audit/Logging

No mixing responsibilities.

## 2.6 VPN Egress Rule (WireGuard)
All outbound requests to Grok API MUST go through WireGuard tunnel.

Implementation MUST support policy routing so that:
- Only worker (or only requests to Grok host) use wg0
- Telegram API and other server traffic may use default route (optional configurable)

The system MUST provide deployment instructions to enforce:
- wg-quick up wg0
- ip rule / ip route (or cgroup-based routing) to ensure Grok traffic uses wg0
- verification steps (curl tests, ip route get, tcpdump)

The worker MUST support one of these enforcement mechanisms (selectable):
A) Run worker under dedicated Linux user (recommended) + fwmark routing
B) Route by destination domain/IP (Grok endpoint) via table 51820
C) Run worker inside Docker container with network configured to wg0-only

A is preferred.

## 2.7 Security-first admin panel
Admin MUST implement:
- bcrypt/argon2 password hash
- TOTP 2FA
- CSRF protection
- rate limiting (login and sensitive actions)
- secure cookies (HttpOnly, Secure, SameSite=Strict)
- strict headers (CSP, XFO, etc.)
- optional IP allowlist
- audit log for admin actions

Secrets MUST be stored in env and never printed.

## 2.8 Telegram publishing rules
Publishing only via Telegram Bot API.
Bot must be admin in channel with posting rights.

## 2.9 Post types
System must handle:
- text: sendMessage
- photo: sendPhoto (caption)
- video: sendVideo (caption)
- album: sendMediaGroup
- card: sendMessage with InlineKeyboard buttons (or photo+buttons)

Each type MUST be explicitly implemented.

## 2.10 Web UI Quality Rule (“make it круто”)
Admin web interface MUST be:
- modern, clean UI
- fast, responsive (desktop-first)
- highly detailed controls
- visual schedule editor (calendar + weekly grid)
- drag-and-drop rearrange slots
- prompt editor with templates and variables
- post preview (how it will look in Telegram)
- JSON editor with validation and schema hints
- jobs dashboard with filters, retry, inspect raw LLM response
- logs & audit trail

---

# 3. DATA MODEL (minimum)

PostgreSQL MUST include at least:

- channels
- themes
- prompts (versioned)
- schedules (rules and slots)
- jobs
- admin_users
- admin_sessions (optional)
- audit_log
- settings

DB is authoritative for runtime.

---

# 4. SCHEDULING RULES

Scheduling must support:
- timezone
- per-day-of-week slots
- per-date-range overrides (campaigns)
- exceptions/blackouts
- per-theme frequency limits
- quiet hours (optional)

Admin UI MUST manage all these visually.

---

# 5. FAILURE HANDLING

- Any failure → job ERROR with last_error
- Keep raw model response for debugging
- Retry strategy configurable: max_attempts + backoff
- Manual retry from admin panel

---

# 6. EXTENSIBILITY

Adding new theme / schedule slot / prompt MUST NOT require code changes.
Only configuration in UI.

---

END OF GLOBAL RULES
