# WTG Spain — Property Offers API

A Laravel REST API that asynchronously imports accommodation offers from suppliers,
returns the cheapest currently valid offer for each property, and allows an offer to
be booked safely.

## Repository

Git repository with commit history: `https://github.com/IvanParkhomchuk/wtg-spain`

## Installation & running

```bash
# 1. Copy the environment file
cp .env.example .env

# 2. Start the containers (app, nginx, mysql, queue)
docker compose up -d --build

# 3. Install dependencies
docker compose exec app composer install

# 4. Generate the application key
docker compose exec app php artisan key:generate
```

The API is available at `http://localhost:8080`.

## Commands

```bash
# Migrations
docker compose exec app php artisan migrate

# Seeders (creates suppliers supplier-a and supplier-b)
docker compose exec app php artisan db:seed

# Queue worker (processes the import Job).
# Runs automatically in the dedicated wtg-spain-queue container; manually:
docker compose exec app php artisan queue:work --tries=3

# Tests
docker compose exec app php artisan test
```

## Import idempotency

The `supplier + external_import_id` combination is unique at the database level. On a
`POST /api/imports` request the import record is created via `firstOrCreate` for that
pair: if the import is new, a Job is dispatched to the queue; if the import already
exists, the Job is not dispatched again. Therefore re-sending the same import creates
no duplicates and does not trigger processing a second time. The offers themselves are
stored via `updateOrCreate` on the unique `supplier + offer.external_id` pair, so an
offer with an already known `external_id` is updated rather than duplicated.

## Protection against two concurrent bookings of the last unit

A booking runs inside a transaction with a pessimistic row lock — `Offer::lockForUpdate()`
issues a `SELECT ... FOR UPDATE` that locks the offer row for the duration of the
transaction. If two requests arrive at the same time for the last unit, the second one
waits for the first transaction to finish, then sees `available_units = 0` and receives
a `422`. This prevents selling the same unit twice.

## .env.example

The `.env.example` file in the project root contains all required environment variables
without any secrets.
