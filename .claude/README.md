# Claude Documentation

This directory contains documentation to help maintain context across Claude sessions.

## Files

### SESSION_CONTEXT.md
**Purpose**: Current project state and quick reference

Contains:
- Project overview and purpose
- Current configuration (package name, commands, etc.)
- Architecture overview
- File structure
- Common tasks and how to perform them
- Important notes for future sessions

**When to read**: Start of every session to understand current state

**When to update**: When project state changes (new features, refactoring, config changes)

---

### DEVELOPMENT_TIMELINE.md
**Purpose**: Complete project history with chronological development notes

Contains:
- Phase-by-phase development history
- Session notes with dates and details
- Decisions made and rationale
- Challenges encountered and solutions
- Lessons learned
- Future planned work

**When to read**: To understand how we got to the current state, or when planning new features

**When to update**: After every session with session notes

---

## Usage Pattern

### At the Start of a Session
1. Read `SESSION_CONTEXT.md` to understand:
   - What the project is
   - Current state and configuration
   - Key conventions and standards

2. Skim `DEVELOPMENT_TIMELINE.md` (especially recent sessions) to understand:
   - Recent changes
   - Context for current branch
   - Any ongoing work

### During a Session
- Reference `SESSION_CONTEXT.md` for:
  - File paths and structure
  - Naming conventions
  - Common code patterns
  - Configuration keys

### At the End of a Session
1. Update `SESSION_CONTEXT.md` if:
   - Project state changed
   - New features added
   - Configuration changed
   - File structure changed

2. Update `DEVELOPMENT_TIMELINE.md` with:
   - Session date and summary
   - What was accomplished
   - Key decisions made
   - Challenges encountered
   - Next steps or TODOs

3. Commit both documentation files

---

## Documentation Standards

### Writing Style
- Clear and concise
- Use bullet points for lists
- Use code blocks for examples
- Use headers for organization
- Include dates on session notes

### Code Examples
Always include working code examples with proper syntax:

```php
// Good example with context
config('notices.polaris_connection')
```

```bash
# Good example with description
php artisan notifications:test-connections
```

### Maintenance
- Keep documentation in sync with code
- Remove outdated information
- Update dates when making changes
- Commit documentation with related code changes

---

## Quick Start for New Sessions

```bash
# 1. Navigate to project
cd /home/user/notifications

# 2. Check current branch
git status

# 3. Read current state
cat .claude/SESSION_CONTEXT.md

# 4. Review recent changes
git log -5 --oneline

# 5. Check for uncommitted changes
git diff
```

---

## Project Quick Facts

**Package**: dcplibrary/notices
**Branch**: claude/composer-package-refactor-011CUuQNMm5JBJfjP7hfrTc3
**Commands**: notifications:*
**Config**: config/notices.php
**Key File**: src/NotificationsServiceProvider.php

See SESSION_CONTEXT.md for complete details.
