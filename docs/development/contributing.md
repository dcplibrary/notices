# Contributing to dcplibrary/notices

Thank you for contributing! This document provides guidelines for contributing to the project.

---

## Commit Message Format

This project uses [Conventional Commits](https://www.conventionalcommits.org/) for automated versioning and changelog generation.

### Format

```
<type>: <description>

[optional body]

[optional footer(s)]
```

**Important:** **Space required after colon!**

### Types

| Type | Description | Version Bump | Example |
|---|---|---|---|
| `feat:` | New feature | **Minor** (1.x.0) | `feat: add slow query logging` |
| `fix:` | Bug fix | **Patch** (1.0.x) | `fix: correct pagination offset` |
| `perf:` | Performance improvement | **Patch** (1.0.x) | `perf: optimize notifications list query` |
| `docs:` | Documentation only | **None** | `docs: update API examples` |
| `refactor:` | Code refactoring | **None** | `refactor: split service into modules` |
| `test:` | Test updates | **None** | `test: add pagination test` |
| `chore:` | Maintenance tasks | **None** | `chore: update dependencies` |

### Examples

#### ✅ Correct Format

```bash
# Feature (triggers minor release: 1.8.0 → 1.9.0)
git commit -m "feat: add Percona monitoring support"

# Bug fix (triggers patch release: 1.9.0 → 1.9.1)
git commit -m "fix: resolve slow query log permissions issue"

# Performance (triggers patch release: 1.9.1 → 1.9.2)
git commit -m "perf: add composite index for notifications list"

# Breaking change (triggers major release: 1.9.2 → 2.0.0)
git commit -m "feat!: require PHP 8.2 minimum"

# Documentation (no release)
git commit -m "docs: add performance optimization guide"

# Refactor (no release)
git commit -m "refactor: extract query logic to service"
```

#### ❌ Incorrect Format

```bash
# Missing space after colon (won't trigger release!)
git commit -m "feat:add slow query logging"

# No type prefix (won't trigger release)
git commit -m "add slow query logging"

# Wrong type (won't trigger release)
git commit -m "feature: add slow query logging"
```

### Multi-line Commits

```bash
git commit -m "feat: add Redis caching support" -m "
This adds Redis caching for:
- Dashboard statistics (2min TTL)
- Lookup tables (1hr TTL)
- Import status (2min TTL)

Closes #42
"
```

---

## Release Process

Releases are **fully automated** using [semantic-release](https://github.com/semantic-release/semantic-release).

### How It Works

1. **Commit with conventional format** → Push to `main` branch
2. **GitHub Actions runs** → Analyzes commits since last release
3. **Determines version bump:**
   - `feat:` commits → Minor version (1.8.0 → 1.9.0)
   - `fix:` / `perf:` commits → Patch version (1.9.0 → 1.9.1)
   - `feat!:` or `BREAKING CHANGE:` → Major version (1.9.1 → 2.0.0)
4. **Updates `CHANGELOG.md`** and commits it back to `main`
5. **Tags the commit** (e.g., `v1.9.0`)
6. **Creates a GitHub release** with the same notes

The version lives in the git tag only. Do not add a version number to `composer.json` or `package.json`, and do not edit `CHANGELOG.md` by hand.

### Manual Release (if needed)

If automation fails (usually because recent commits were not conventional — e.g. Cursor titles like `Fix foo` instead of `fix: foo`), or you need to tag code already on `main`:

```bash
# Amend last commit to fix format (only if not yet relied on by others)
git commit --amend -m "feat: add slow query logging"
git push --force

# Preferred: empty conventional commit so semantic-release publishes
# Use fix:/feat:/perf: — chore: does NOT create a release
git commit --allow-empty -m "fix: release missed changes from #41 and #42"
git push origin main

# Or manually tag + create a GitHub Release in the UI
git tag -a v1.10.0 -m "Release v1.10.0"
git push origin v1.10.0
```

You can also re-run **Actions → Release → Run workflow** after a conventional commit lands on `main`.

---

## Development Workflow

### 1. Clone and Setup

```bash
git clone https://github.com/dcplibrary/notices.git
cd notices
composer install
npm install
```

`npm install` is only needed if you change styles. The compiled stylesheet, `resources/dist/notices.css`, is committed so the package works without a build step.

After changing Tailwind classes in a view, or editing `resources/css/notices.css`, rebuild it and commit the result:

```bash
npm run build
```

Run `composer install` first. The build scans `vendor/dcplibrary/ui` as well as this package, so classes used only by the shared layout would be dropped without it.

### 2. Create Feature Branch

```bash
git checkout -b feature/my-feature
```

### 3. Make Changes

- Write code
- Add tests
- Update documentation

### 4. Run Tests

```bash
# Run all tests
composer test

# Run specific test
vendor/bin/pest tests/Feature/MyTest.php

# Run with coverage
vendor/bin/pest --coverage
```

### 5. Commit with Conventional Format

```bash
# Stage changes
git add .

# Commit with proper format (space after colon!)
git commit -m "feat: add my awesome feature"

# Push to your branch
git push origin feature/my-feature
```

### 6. Create Pull Request

- PR title **must** follow conventional commits format (enforced by the `PR Title` workflow)
- Example: `feat: add slow query logging support`
- ❌ `Fix slow query logging` will merge fine but **will not** cut a release tag

### Cursor / cloud agents

Cursor PRs are the most common source of missed releases. Before opening or merging:

1. Commit subject: `fix: …` / `feat: …` / `perf: …` (space after colon)
2. Matching PR title
3. After merge, confirm a new tag appears under [Releases](https://github.com/dcplibrary/notices/releases). A green **Release** job that says *"no new version is released"* means the subjects were not conventional — see [docs/development/contributing.md](contributing.md).

### 7. Merge to Main

Once PR is approved and merged to `main`:
- ✅ CI/CD runs tests
- ✅ Semantic-release analyzes commits since the last tag
- ✅ New version is automatically released **only if** at least one analyzed commit is `feat:` / `fix:` / `perf:` (or breaking)

---

## Fixing a Mistake

### Incorrect Commit Message

If you committed with wrong format:

```bash
# Option 1: Amend last commit (if not pushed)
git commit --amend -m "feat: correct commit message"

# Option 2: Amend and force push (if already pushed)
git commit --amend -m "feat: correct commit message"
git push --force

# Option 3: Create new commit to trigger release
git commit --allow-empty -m "feat: add feature (previous commit)"
git push
```

### Release Didn't Trigger

**Check:**
1. ✅ Commit format is correct (space after colon!)
2. ✅ Pushed to `main` branch
3. ✅ GitHub Actions workflow succeeded
4. ✅ At least one `feat:` or `fix:` commit since last release

**View Actions:**
https://github.com/dcplibrary/notices/actions

**Common Issues:**
- ❌ Missing space after colon: `feat:message`
- ✅ Correct format: `feat: message`

---

## Code Style

### PHP

```bash
# Check code style
composer cs-check

# Fix code style
composer cs-fix
```

### Commit Messages

```bash
# Check commit message format
git log --oneline -1

# Should look like:
# abc1234 feat: add new feature
```

---

## Testing

### Unit Tests

```bash
vendor/bin/pest tests/Unit/
```

### Feature Tests

```bash
vendor/bin/pest tests/Feature/
```

### Database Tests

```bash
# SQLite (fast)
vendor/bin/pest --ci

# Percona 8 (comprehensive)
NOTICES_TEST_DB=mysql vendor/bin/pest
```

---

## Class reference

The PHP class reference is generated from the docblocks in `src/` by [phpDocumentor](https://www.phpdoc.org/), configured in `phpdoc.xml`. It is not committed. The "Class reference" workflow rebuilds it and publishes it to GitHub Pages whenever `src/` changes on `main`.

To build it locally, with Docker:

```bash
docker run --rm -v "$PWD":/data phpdoc/phpdoc:3.10 --config phpdoc.xml
```

Then open `build/phpdoc/index.html`.

Because the reference comes from the code, keep docblocks accurate: describe what a class is for, and document model attributes with `@property` lines.

---

## Documentation

When adding features, update:

- [ ] Code comments (PHPDoc)
- [ ] README.md (if user-facing)
- [ ] `docs/openapi.yaml` (if you add or change an API route; a test enforces this)
- [ ] CHANGELOG.md (automated by semantic-release)

---

## Questions?

- 🐛 Report issues: [GitHub Issues](https://github.com/dcplibrary/notices/issues)
- 💬 Discuss: [GitHub Discussions](https://github.com/dcplibrary/notices/discussions)

---

## Quick Reference

**Commit Format:**
```bash
<type>: <description>     # Space after colon!
```

**Types that trigger release:**
- `feat:` → Minor version
- `fix:` → Patch version
- `perf:` → Patch version
- `feat!:` → Major version

**Types that don't trigger release:**
- `docs:` `refactor:` `test:` `chore:`

---

**Last Updated:** 2026-02-13
**Release Automation:** semantic-release
**Commit Standard:** Conventional Commits
