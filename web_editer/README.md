# Web Editor

Browser-based code editor with file browser, powered by CodeMirror.

Consolidated from web_editer (v1), web_editer2, and web_editer3.

## Features

- Syntax highlighting with auto-detected language modes
- File browser with directory navigation
- Create, load, and save files
- Tab-to-space conversion and trailing space removal
- Unsaved changes detection
- CSRF protection
- Ctrl+S keyboard shortcut for saving
- **Classic mode**: click the "Classic" button to switch to the original web_editer2 visual style

## Files

| File | Description |
|------|-------------|
| `index.php` | Main editor UI (with classic style toggle) |
| `api.php` | Backend API (load/save/list) |
| `common.php` | Shared config, session, CSRF, and path utilities |
| `login.php` | Login page |
| `logout.php` | Logout handler |

## Authentication

Login credentials are defined in `/var/www/admin_credentials.php`.
