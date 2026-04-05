# Timer Web (PHP + MySQL + JS)

Minimal port of the iOS Timer app to a PHP/MySQL web app.

Setup

1. Create a MySQL database and import `db.sql`:

```sql
CREATE DATABASE timer_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE timer_db;
-- then import db.sql
```

2. Configure DB connection via environment variables or edit `api/db.php`:

- `DB_HOST` (default: 127.0.0.1)
- `DB_NAME` (default: timer_db)
- `DB_USER` (default: root)
- `DB_PASS` (default: empty)

3. Serve the `public/` folder with your webserver (DocumentRoot -> `panel/timer_web/public`). API endpoints are in `panel/timer_web/api/`.

Tips

- This is scaffolding. Customize authentication and UI to match your iOS app visuals.
