# AUTPOSTER SYSTEM — ROADMAP
Version: 1.1
Status: CANONICAL IMPLEMENTATION PLAN

Goal: deliver a complete “turnkey” autoposter + admin web interface with WireGuard egress for Grok.

---

# PHASE 0 — REPO + FOUNDATION
- Create project structure (src, admin, db, public, bin)
- Composer setup
- .env example
- Basic logging
- Nginx vhost template

Deliverables:
- working local bootstrap
- README with setup

---

# PHASE 1 — DATABASE (PostgreSQL)
Design and implement migrations for:
- channels
- themes
- prompts (versioned)
- schedules (rules, slots, overrides)
- jobs
- audit_log
- admin_users (+ optional admin_sessions)
- settings

Deliverables:
- migrations
- seed (optional)

---

# PHASE 2 — ADMIN AUTH + SECURITY BASE
Implement:
- Login with password hash
- TOTP setup flow
- Session hardening
- CSRF tokens
- Rate limit login
- Security headers
- Optional IP allowlist
- Audit log (login success/fail, config changes)

Deliverables:
- /admin working securely
- admin can login only with 2FA

---

# PHASE 3 — ADMIN UI “PRO” (beautiful & detailed)
Implement Admin pages:
1) Dashboard (stats, next runs, errors)
2) Channels CRUD
3) Themes CRUD
4) Prompt Manager:
   - editor with variables and snippets
   - versioning and rollback
5) Schedule Builder:
   - weekly grid view
   - date-range campaigns
   - drag & drop slots
   - exceptions/blackouts
6) Jobs Monitor:
   - filters (status, theme, channel, date)
   - open job detail (raw JSON, raw LLM)
   - retry / run now
7) Test Lab:
   - generate from selected theme
   - JSON validation
   - live Telegram preview
   - send manually

Deliverables:
- UI is fully usable without editing files
- DB becomes the main config

---

# PHASE 4 — GROK CLIENT (with strict JSON + VPN awareness)
Implement:
- Grok HTTP client
- strict JSON guard
- schema validation (server-side)
- store raw response and parsed JSON
- optional “repair” attempt via second pass prompt (configurable)

Deliverables:
- reliable JSON output pipeline

---

# PHASE 5 — TELEGRAM CLIENT
Implement:
- sendMessage
- sendPhoto
- sendVideo
- sendMediaGroup
- buttons for card
- message formatting rules

Deliverables:
- post types fully working

---

# PHASE 6 — SCHEDULER
Implement scheduler:
- reads schedule from DB
- creates jobs for due slots
- prevents duplicates (unique constraint)
- supports date-range overrides and exceptions
- respects quiet hours and limits

Deliverables:
- cron-friendly scheduler

---

# PHASE 7 — WORKER
Implement worker:
- fetch NEW jobs
- mark RUNNING
- call Grok (through VPN route)
- validate JSON
- publish to Telegram
- mark DONE or ERROR
- retry with backoff

Deliverables:
- robust worker

---

# PHASE 8 — WIREGUARD EGRESS CONTROL
Provide deployment scripts/instructions:
Option A (recommended): route worker traffic via wg0 using fwmark + dedicated user.
- Create linux user `autoposter`
- Run worker as that user
- iptables mangle OUTPUT mark for that user
- ip rule add fwmark -> table 51820
- ip route add default dev wg0 table 51820
- DNS considerations

Verification steps:
- curl to Grok endpoint shows wg IP
- tcpdump shows traffic over wg0

Deliverables:
- doc + example scripts

---

# PHASE 9 — DEPLOYMENT + OPS
- systemd units for worker + scheduler
- log rotation
- health endpoints (optional)
- backups (pg_dump)
- monitoring basics

Deliverables:
- production-ready deployment

---

# PHASE 10 — FINAL POLISH
- UI polish (icons, layout, dark mode)
- safety guardrails for content categories
- additional templates and presets

Deliverables:
- “everything круто” turnkey result

---

END OF ROADMAP
