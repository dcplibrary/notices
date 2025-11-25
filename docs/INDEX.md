# Notices Package Documentation Index

This document provides a complete index of all documentation for the DC Public Library Notices package.

## Quick Links

- [Main README](../README.md) - Package overview, installation, and basic usage
- [User Guide](help/USER_GUIDE.md) - How to use the dashboard
- [Changelog](../CHANGELOG.md) - Version history and release notes

## User Documentation

- [**User Guide**](help/USER_GUIDE.md) - Dashboard features and how to use them
  - Searching for notifications
  - Understanding dashboard pages
  - Common tasks and scenarios
  - Quick reference guide

## Architecture & Design

- [**Architecture Overview**](ARCHITECTURE.md) - Comprehensive system architecture, design patterns, and component relationships
- [**Data Source Field Mapping Matrix**](DATA_SOURCE_FIELD_MAPPING_MATRIX.md) - Complete mapping of data fields from Polaris ILS and Shoutbomb to the notices database

## Development Documentation

### Package Development
- [**Package Merge Guide**](PACKAGE_MERGE.md) - Guide for merging this package into another Laravel application
- [**Doctrine Annotations**](DOCTRINE_ANNOTATIONS.md) - Documentation on Doctrine annotations used in the codebase

### Scheduled Tasks
- [**Import Schedule**](IMPORT_SCHEDULE.md) - Comprehensive automated import schedule based on Polaris/Shoutbomb export times
  - 9 distinct scheduled tasks
  - Visual timeline of exports and imports
  - Configuration and troubleshooting

## Deployment & Configuration

- [**Proxy Configuration**](deployment/PROXY_CONFIGURATION.md) - Configure Laravel behind nginx-proxy with SSL termination
  - Fixes `https://domain:80` URL generation issues
  - OAuth/SSO redirect troubleshooting
  - Cloudflare + nginx-proxy setup
- [**Proxy Diagnostics**](deployment/PROXY_DIAGNOSTICS.md) - Step-by-step diagnostics for proxy configuration issues
  - Tinker commands to test configuration
  - Common mistakes and fixes
  - Header verification

## Integration Documentation

### Shoutbomb Integration

Complete documentation for Shoutbomb SMS/voice notification integration:

- [**Shoutbomb Documentation Index**](shoutbomb/SHOUTBOMB_DOCUMENTATION_INDEX.md) - Overview and links to all Shoutbomb docs
- **Data Export Documentation:**
  - [Polaris Phone Notices Export](shoutbomb/POLARIS_PHONE_NOTICES.md) - PhoneNotices.csv daily export
  - [Hold Notifications Export](shoutbomb/SHOUTBOMB_HOLDS_EXPORT.md) - 4 daily hold notification exports
  - [Overdue Notifications Export](shoutbomb/SHOUTBOMB_OVERDUE_EXPORT.md) - Daily overdue notice export
  - [Renewal Notifications Export](shoutbomb/SHOUTBOMB_RENEW_EXPORT.md) - Daily renewal notice export
  - [Voice Patron List](shoutbomb/SHOUTBOMB_VOICE_PATRONS.md) - voice_patrons.txt daily export
  - [Text Patron List](shoutbomb/SHOUTBOMB_TEXT_PATRONS.md) - text_patrons.txt daily export
- **Reporting Documentation:**
  - [Shoutbomb Incoming Reports](shoutbomb/SHOUTBOMB_REPORTS_INCOMING.md) - Email reports from Shoutbomb (failures, statistics)
- **Process Documentation:**
  - [Shoutbomb Process Explanation](shoutbomb/shoutbomb_process_explanation.md) - How the entire Shoutbomb integration works

### Notification Verification Package

- [Notification Verification Package Documentation](notification_verification_package/) - Timeline and audit trail system for patron notifications

## Documentation Organization

```
docs/
├── INDEX.md (this file)
├── ARCHITECTURE.md
├── DATA_SOURCE_FIELD_MAPPING_MATRIX.md
├── DOCTRINE_ANNOTATIONS.md
├── IMPORT_SCHEDULE.md
├── PACKAGE_MERGE.md
├── help/
│   └── USER_GUIDE.md
├── deployment/
│   ├── PROXY_CONFIGURATION.md
│   └── PROXY_DIAGNOSTICS.md
├── shoutbomb/
│   ├── SHOUTBOMB_DOCUMENTATION_INDEX.md
│   ├── POLARIS_PHONE_NOTICES.md
│   ├── SHOUTBOMB_HOLDS_EXPORT.md
│   ├── SHOUTBOMB_OVERDUE_EXPORT.md
│   ├── SHOUTBOMB_RENEW_EXPORT.md
│   ├── SHOUTBOMB_REPORTS_INCOMING.md
│   ├── SHOUTBOMB_TEXT_PATRONS.md
│   ├── SHOUTBOMB_VOICE_PATRONS.md
│   └── shoutbomb_process_explanation.md
└── notification_verification_package/
    └── (verification system documentation)
```

## Documentation Organization Rules

**⚠️ IMPORTANT FOR ALL CONTRIBUTORS AND AI AGENTS ⚠️**

When creating or modifying documentation for this package:

### File Placement Rules

1. **ALL documentation MUST go in the `docs/` folder** - Never create documentation files in the root directory

2. **Use appropriate subdirectories:**
   - `docs/help/` - End-user documentation (dashboard usage, features, common tasks)
   - `docs/deployment/` - Deployment, configuration, infrastructure, proxy setup
   - `docs/shoutbomb/` - Shoutbomb integration specifics
   - `docs/` (root) - Architecture, design, general development documentation

3. **Always update `docs/INDEX.md`** when adding new documentation

4. **Create subdirectory indexes** for integration-specific docs (like `SHOUTBOMB_DOCUMENTATION_INDEX.md`)

5. **Keep help docs separate from developer docs** - Help documentation focuses on using the interface and features, not technical implementation or code

### Naming Conventions

- Use clear, descriptive filenames in `SCREAMING_SNAKE_CASE.md`
- Examples: `PROXY_CONFIGURATION.md`, `IMPORT_SCHEDULE.md`, `SHOUTBOMB_HOLDS_EXPORT.md`

### Linking

- Link from the main `README.md` ONLY if the documentation is essential for getting started
- Use relative paths in all documentation links
- Keep the "Quick Links" section in README.md concise (max 4-5 links)

### Writing Style

- **Write for novices without dumbing down** - Assume readers are intelligent but may be unfamiliar with the specific technology
- **Keep it semi-casual and easy to read** - Use conversational tone, avoid unnecessary jargon, but maintain technical precision
- **Use progressive disclosure** - Start with simple explanations, then add technical details
- **Provide context** - Explain WHY something is needed, not just HOW to do it
- **Use real-world examples** - Show before/after scenarios and practical use cases
- **Add visual hierarchy** - Use emojis (sparingly), headings, code blocks, and formatting to make docs scannable
- **Include "Why would I use this?"** sections to help readers understand applicability
- **Avoid academic tone** - Write like you're explaining to a colleague, not writing a research paper

### Why This Matters

- Prevents merge conflicts from root-level documentation files
- Maintains organized, discoverable documentation structure
- Makes it easy for developers and AI agents to find and update docs
- Makes documentation accessible to users of all skill levels
