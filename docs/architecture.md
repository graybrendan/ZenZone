# ZenZone Architecture

## Overview
ZenZone is a PHP/MySQL web app structured around simple PHP pages, backend handlers, and shared include files.

The project currently follows a lightweight structure:
- public pages for UI entry points
- api handlers for processing actions
- includes for reusable shared logic
- sql files for schema/setup
- docs for project understanding

## High-level flow
Typical request flow:

1. User opens a page in `public/`
2. Page renders UI and/or submits a form
3. Form/action goes to a related file in `api/`
4. API file uses shared logic from `includes/`
5. DB interaction happens through shared DB helpers/config
6. User is redirected or shown updated UI

## Main directories

### `public/`
Purpose:
- Browser-accessible pages
- UI entry points
- Page-level rendering

Examples:
- login/signup
- dashboard
- baseline page
- check-in page
- goals pages
- content pages
- coach page

### `api/`
Purpose:
- Handle form submissions and state changes
- Create/update/delete records
- Process backend logic after user actions

Examples:
- auth handlers
- baseline save
- goals create/update/delete/check-in
- content save/progress (currently disabled handlers)
- reflections save (currently disabled handler)

### `includes/`
Purpose:
- Shared project logic reused across pages and handlers

Important areas:
- `config.php` - local config values
- `db.php` - DB connection logic
- `session.php` - session start / login checks
- `auth.php` - authentication helpers
- `validation.php` - input validation
- `helpers.php` - utility helpers
- `zenscore.php` - score-related logic
- `checkin_functions.php` - check-in-related shared logic

### `sql/`
Purpose:
- database schema
- seed/setup support

### `docs/`
Purpose:
- architecture notes
- database notes
- future developer onboarding

## Current feature architecture

### Authentication
Primary responsibility:
- register user
- log in user
- create/maintain session
- log out user

Likely files involved:
- `public/login.php`
- `public/signup.php`
- `public/logout.php`
- `api/auth/login.php`
- `api/auth/register.php`
- `api/auth/logout.php`
- session/auth-related include files

### Baseline assessment
Primary responsibility:
- collect initial assessment data
- establish initial user baseline

Likely files involved:
- `public/baseline.php`
- `api/baseline/save.php`
- shared score/check-in logic in includes

### Check-ins / ZenScore
Primary responsibility:
- track how the user is doing
- support daily or ongoing score-related logic
- connect check-ins to trend or state interpretation

Likely files involved:
- `public/checkin.php`
- `includes/checkin_functions.php`
- `includes/zenscore.php`

### Goals
Primary responsibility:
- create goals
- edit goals
- delete goals
- mark goal completion/check-ins
- support priority goal logic

Likely files involved:
- `public/goals/index.php`
- `public/goals/create.php`
- `public/goals/edit.php`
- `public/goals/details.php`
- `api/goals/create.php`
- `api/goals/update.php`
- `api/goals/delete.php`
- `api/goals/checkin.php`
- `api/goals/make_priority.php`
- `api/goals/remove_priority.php`

### Coach
Primary responsibility:
- guided support or future AI-assisted flow

Likely files involved:
- `public/coach/index.php`
- `api/coach/submit.php`

### Content / lessons
Primary responsibility:
- present content
- save user progress or saved items

Likely files involved:
- `public/content/index.php`
- `public/content/view.php`
- `api/content/save.php`
- `api/content/progress.php`

### Reflections
Primary responsibility:
- store user reflections tied to experiences or actions

Likely files involved:
- `api/reflections/save.php` (currently disabled handler)

## Architectural intent
The project should stay:
- beginner-readable
- modular enough to grow
- simple enough to debug
- compatible with local XAMPP workflow

## Current design rule
Prefer:
- reusing existing includes
- small targeted fixes
- beginner-friendly PHP
- explicit validation before DB writes
- prepared statements over raw query building

Avoid:
- unnecessary frameworks
- large refactors without a clear reason
- logic duplication across many files

## Future documentation upgrades
This file should later include:
- route-by-route flow maps
- auth sequence diagram
- goal lifecycle diagram
- check-in / ZenScore calculation notes
