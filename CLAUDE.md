## High-Level Mindset
- Deliver complete, production-ready solutions. Never leave half-finished code or suggest "tabling it for later."
- Understand the code before calling it done. Be able to explain failure modes and edge cases.
- Use code/scripts for deterministic tasks (math, conversions, formatting) instead of raw LLM reasoning.

## Non-Negotiable Permission & Change Protocol
- **Permission First:** Ask for explicit user permission before applying any changes to files. This applies to:
  - Creating new files
  - Editing existing files
  - Deleting any files or records
- **Minimal & Precise Changes:** Touch only what is strictly necessary. Keep edits surgical and scoped directly to the problem—do not refactor unrelated code or add bloat.
- **Detailed Explanation:** Clearly explain what needs to be changed, why it is necessary, and specify the exact file names and line numbers before and after modifications.
- **Post-Implementation Build & Test:** Verify every change with a post-implementation test/syntax check immediately after editing. Do not consider a task complete without proving 0 errors.

## Tech Stack & Architecture
- **Language:** PHP 8.x (vanilla, procedural/clean modular architecture)
- **Database:** MySQL on Aiven (`defaultdb`)
- **Frontend:** Vanilla HTML5, CSS3, JavaScript
- **AI Service:** Google Gemini API (`gemini-1.5-flash`) via `v1beta`
- **Rules:**
  - Always use prepared statements (`PDO` or `mysqli`) to prevent SQL injection.
  - Keep configuration and credentials inside environment variables / `config/db.php`. Never commit secrets.
  - Return clean JSON from API handlers: `{"success": true, "data": ...}` or `{"success": false, "error": "message"}`.

## Git & Safety
- **Branching:** Never push directly to `main`. Create task branches: `git checkout -b <task-name>`.
- **Review Before Commit:** Review `git diff` and `git status` before committing.
- **Safety:**
  - Never run destructive commands (`DROP TABLE`, `rm -rf`, `git reset --hard`) without explicit confirmation.
  - Never commit `.env` or files containing live database passwords / API keys.

## Communication Style
- Be concise, direct, and concrete. No fluff, no robotic filler.
- Reference exact files and line numbers (e.g., `api/schedule.php:42`).
- Report task completion with one status:
  - `DONE` (Ready, verified, and 0 errors confirmed)
  - `DONE_WITH_CONCERNS` (Works, but has known risks/trade-offs)
  - `BLOCKED` (Requires external input or missing credentials)