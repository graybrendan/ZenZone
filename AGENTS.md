# ZenZone agent instructions

## Project overview
ZenZone is a PHP/MySQL web app running locally in XAMPP.
Primary repo root: C:\xampp\htdocs\ZenZone

## Stack
- PHP
- MySQL
- XAMPP
- phpMyAdmin
- GitHub
- VS Code

## Important project structure
- `public/` = user-facing pages and route entry points
- `api/` = backend handlers/endpoints
- `includes/` = shared PHP utilities, config, auth, DB, validation, session logic
- `sql/` = schema and seed files
- `docs/` = architecture notes and developer docs

## Working rules
- Do not invent new frameworks.
- Prefer simple PHP/MySQL solutions that fit the current project structure.
- Reuse existing includes and route patterns before introducing new files.
- Keep changes minimal and local unless a broader refactor is clearly necessary.
- Explain cross-file effects before making large edits.
- Preserve compatibility with local XAMPP development.
- Assume phpMyAdmin is used for manual DB inspection.
- Avoid editing secrets or hardcoding credentials.
- When changing schema-related logic, also note whether `sql/schema.sql` should be updated.
- When fixing bugs, identify root cause first, then propose the smallest correct fix.

## Coding preferences
- Favor beginner-readable PHP.
- Use clear variable names.
- Add short comments only where logic is non-obvious.
- Keep HTML/PHP mixed templates readable.
- Validate request input before DB operations.
- Prefer prepared statements and existing DB helpers.

## Workflow preferences
- Ask for confirmation before broad refactors.
- For narrow bug fixes or focused feature work, propose exact file changes.
- When useful, provide copy-paste-ready replacements.
- Mention which files changed and why.
- Flag anything that should be tested in XAMPP after code changes.

## Common goals
- Improve auth flow
- Improve goal creation/check-ins/priority logic
- Improve ZenScore/check-in logic
- Keep architecture understandable for a solo student developer