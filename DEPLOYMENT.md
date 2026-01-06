# Deployment notes

## API (PHP)
- **Env vars**
  - `DATABASE_DSN` (optional): defaults to `sqlite:/absolute/path/to/data/expenses.sqlite`. Examples:  
    - SQLite: `sqlite:/var/app/data/expenses.sqlite`  
    - Postgres: `pgsql:host=localhost;port=5432;dbname=mini_fin` (+ `DATABASE_USER`, `DATABASE_PASSWORD`)
  - `API_CORS_ORIGIN` (optional): allowed origin for browsers (e.g., `https://yourpages.github.io`). Defaults to `*`.
- **Schema**
  - SQLite: auto-applies `schema.sql` on first run.
  - Postgres/others: run your own migration (convert AUTOINCREMENT to SERIAL/IDENTITY).
- **Run**
  - `php -S 0.0.0.0:8000 -t api` (or behind nginx/Apache/PHP-FPM).
- **Seed (demo-only)**
  - `php scripts/seed.php` (clears expenses, keeps categories).

## Frontend (Vite/React)
- **Env**: copy `web/.env.example` to `web/.env` and set `VITE_API_BASE` to your API URL.
- **Build**: `cd web && npm install && npm run build` → static assets in `web/dist/`.
- **Deploy to GitHub Pages**: push the `dist/` contents to your `gh-pages` branch (or a deployment repo) and point `VITE_API_BASE` at your hosted API.

## Notes
- GitHub Pages can only host the static frontend; the PHP API must be hosted elsewhere (VPS/shared host/docker).
- SQLite is fine for single-user/demo. Use Postgres/MySQL for multi-user; add proper migrations and backups.
