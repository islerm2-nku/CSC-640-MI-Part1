# CSC-640-MI

Lightweight telemetry ingestion and analysis API for iRacing-style IBT files. Upload telemetry, query sessions, extract lap ranges and compute per-lap metrics.

Quick pointers
- API collection: `PostmanCollection.json` (import into Postman / Insomnia to explore endpoints)
- Example telemetry files for the upload endpoint: the `telemetry/` folder contains sample `.ibt` files you can use to test uploads

Getting started (Docker)

Run the app and DB with Docker Compose and then run the migration:

```bash
docker-compose up --build -d
docker-compose run --rm migrate
```

If you prefer, you can exec into the `web` container and run the migration directly:

```bash
docker-compose exec web php /var/www/html/db/create_db.php
```

## Migrations

This project includes a small migration script at `db/create_db.php` that creates the database schema (idempotent). The repository runs Composer during image build so you don't need Composer on your host.

To build and start the app and database containers:

```bash
docker-compose up --build -d
```

Run the migration (wait a few seconds for MySQL to become healthy):

```bash
docker-compose run --rm migrate
```

You can also exec into the running `web` container and run the migration manually:

```bash
docker-compose exec web php /var/www/html/db/create_db.php
```

If you change migrations, re-run the migrate command. For production or more advanced workflows consider a proper migration tool (Phinx, Doctrine Migrations, or framework-provided tooling).

