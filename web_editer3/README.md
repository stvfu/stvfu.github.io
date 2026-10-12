# Web Editor 3

Browser-based code editor with file browser, powered by CodeMirror.

## Features

- Syntax highlighting with auto-detected language modes
- File browser with directory navigation
- Create, load, and save files
- Tab-to-space conversion and trailing space removal
- Unsaved changes detection
- CSRF protection
- Ctrl+S keyboard shortcut for saving

## Files

| File | Description |
|------|-------------|
| `index.php` | Main editor UI |
| `api.php` | Backend API (load/save/list) |
| `common.php` | Shared config, session, CSRF, and path utilities |
| `login.php` | Login page |
| `logout.php` | Logout handler |

## Authentication

Login credentials are defined in `/var/www/admin_credentials.php`.
