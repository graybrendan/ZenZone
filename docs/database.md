# ZenZone Database Notes

## Overview
ZenZone uses MySQL in local development and is managed through XAMPP/phpMyAdmin.

This document is meant to describe the intended purpose of the database structure, even if the schema continues evolving.

## Local database
Expected local database name:
- `zenzone`

Local config currently points to:
- host: `127.0.0.1`
- user: `root`
- password: empty for local development

## Main database goals
The database should support:
- user accounts
- baseline assessment data
- check-ins / score-related data
- goals and goal progress
- content progress/saves
- reflections
- possible coach-related history

## Likely table groups

### 1. Users
Purpose:
- identify each user
- support authentication/session ownership
- store core profile-level data if needed

Typical fields:
- id
- first_name
- full_name
- sport
- email
- password hash
- created_at
- updated_at

### 2. Baseline assessments
Purpose:
- store the user’s initial baseline assessment
- support later comparison against check-ins

Typical fields:
- id
- user_id
- baseline-related values
- created_at

### 3. Check-ins / ZenScore-related entries
Purpose:
- store ongoing state/check-in data
- support score and trend interpretation

Typical fields:
- id
- user_id
- date or timestamp
- check-in values
- derived score fields if used
- created_at

### 4. Goals
Purpose:
- store user goals
- define cadence and status
- support priority vs non-priority distinction

Typical fields:
- id
- user_id
- title
- description
- cadence_type
- is_priority
- status
- created_at
- updated_at

### 5. Goal check-ins or completions
Purpose:
- track whether a goal was completed/check-marked for a period
- support progress history

Typical fields:
- id
- goal_id
- user_id
- completed flag or completion state
- check-in date
- created_at

### 6. Reflections
Purpose:
- store user reflection text and related metadata

Typical fields:
- id
- user_id
- related feature reference if needed
- reflection text
- created_at

### 7. Content saves/progress
Purpose:
- track what content the user saved or completed

Typical fields:
- id
- user_id
- content identifier
- saved flag
- progress flag or progress state
- created_at
- updated_at

### 8. Coach history
Purpose:
- optional support for coach interactions, threads, outcomes, or guidance history

Typical fields:
- id
- user_id
- prompt/input
- response/outcome
- created_at

## Important relationships
Main expected relationships:
- one user -> many baseline/check-in/goal/reflection/content records
- one goal -> many goal check-in/completion records
- score-related logic should remain tied to the correct user and time context

## Design principles
- every user-owned record should clearly link to `user_id`
- timestamps should be used consistently
- nullable fields should be intentional
- avoid storing the same meaning in multiple places unless there is a good reason
- schema changes should be reflected in `sql/schema.sql`

## Confirmed active tables (as used by current PHP code)
- `users`
- `baseline_assessments`
- `check_ins`
- `daily_zenscore_summary`
- `goals`
- `goal_checkins`


## Import troubleshooting (phpMyAdmin / XAMPP)
- If you see `#1064` with text like `@@ -70,25 +73,65 @@`, the file being imported is a **git diff/patch**, not raw SQL. Re-download or open `sql/schema.sql` directly from the repo and import that exact file.
- If you see `#1451 - Cannot delete or update a parent row`, run the full `sql/schema.sql` file (from the top) so the database reset executes before table creation.
- Confirm the filename is `sql/schema.sql` (not `sql/shema.sql`).

## Current source of truth
The actual implementation should be checked against:
- `sql/schema.sql`
- current PHP DB queries in `api/`
- current shared DB logic in `includes/`

## Next upgrade for this document
Later, replace the “likely” sections with:
- confirmed table names
- confirmed field names
- PK/FK relationships
- indexes
- notes about which PHP files read/write each table
