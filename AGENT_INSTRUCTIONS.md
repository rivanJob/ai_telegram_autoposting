# AUTPOSTER SYSTEM — AGENT INSTRUCTIONS (FOR CODEX)
Version: 1.1

You are implementing a complete turnkey system:
- autonomous Grok content generation
- Telegram autoposting
- full admin web interface
- strict security
- WireGuard-only egress for Grok calls

You MUST strictly follow:
- GLOBAL_RULES.md (v1.1)
- ROADMAP.md (v1.1)

No deviations.

---

# 1. PRIMARY OUTPUT
Produce a working repository with:
- migrations (PostgreSQL)
- /admin secure UI
- scheduler + worker scripts
- Grok client with strict JSON
- Telegram client supporting post types
- deployment instructions including WireGuard routing

Everything should run on one server.

---

# 2. CODING REQUIREMENTS
- PHP 8.2+ compatible
- Use PDO with prepared statements
- Composer autoload
- Clear folder structure
- No secrets in code
- Robust error handling
- Minimal external libs (dotenv allowed; UI can be vanilla HTML/JS/CSS)

---

# 3. ADMIN UI REQUIREMENTS (“красивая и подробная”)
UI must include:
- Modern design (cards, clean layout, responsive)
- Sidebar navigation
- Forms with validation + tooltips
- Schedule editor:
  - weekly grid (Mon–Sun, time slots)
  - date range campaigns
  - exceptions/blackouts
  - drag-and-drop reorder slots
- Prompt editor:
  - syntax highlighting (simple JS) OR well-formatted textarea with snippets
  - variables picker (e.g. {{audience}}, {{tone}}, {{category}})
  - version history
- Post preview:
  - how it looks in Telegram (title/text/tags/buttons)
- Jobs table:
  - filters, pagination
  - job detail modal with raw JSON + raw LLM response
  - retry/run-now buttons

Admin actions must be written to audit_log.

---

# 4. GROK JSON CONTRACT
Worker must send prompts that force strict JSON only.
If model returns invalid JSON:
- job → ERROR
- save raw response
Optional: “repair” pass (configurable), then validate again.

---

# 5. WIREGUARD REQUIREMENT (MANDATORY)
All Grok API requests must egress via WireGuard wg0.

Implementation must provide:
- deployment instructions
- example iptables/ip rule commands for policy routing
- recommended approach: run worker as dedicated Linux user and mark its packets

Agent must add docs:
- /docs/WIREGUARD_EGRESS.md
- /docs/DEPLOYMENT.md

---

# 6. IMPLEMENTATION STEPS (strict order)
Follow ROADMAP phases sequentially:
0 → 1 → 2 → ... → 10

Each phase:
- implement
- ensure it runs
- commit logical changes

---

# 7. MUST DELIVER FILES
At minimum include:
- README.md (quick start)
- .env.example
- db/migrations/*.sql
- bin/scheduler.php
- bin/worker.php
- src/* (clients, guards, services)
- public/admin (or /admin) web UI
- docs/WIREGUARD_EGRESS.md
- docs/DEPLOYMENT.md

---

# 8. DEFINITION OF DONE
System is DONE when:
- Admin panel can configure channels/themes/prompts/schedules fully via UI
- Scheduler creates jobs from configured schedules
- Worker calls Grok via WireGuard routing and posts to Telegram
- Post types work: text/photo/video/album/card
- Security features are enabled (2FA, CSRF, rate limit, secure cookies)
- Jobs/audit logs are visible in admin panel

---

END OF AGENT INSTRUCTIONS
