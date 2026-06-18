# Borgfish — Auction Marketplace (Laravel + Filament)

Borgfish is an auction-first marketplace for fresh seafood. This repository demonstrates a production-minded Laravel application combining real-time auction workflows, payment integrations, and notification delivery — packaged as a portfolio project for easy evaluation.

**Recruiter Quick Overview**

# Borgfish — Auction Marketplace (Laravel + Filament)

Borgfish is an auction-first marketplace for fresh seafood. This repository contains a production-oriented Laravel application combining real-time auction workflows, payment integrations, and reliable notification delivery — prepared for evaluation and deployment.

## Live Demo

https://borgfish.my.id/

**Recruiter Quick Overview**

- **Project Type:** Marketplace / Auction platform (seller → buyer)
- **Tech:** PHP 8.3 | Laravel 13 | Filament | MySQL | Vite | Tailwind
- **Auth:** Google OAuth (Socialite) + WhatsApp OTP
- **Payments:** TriPay — idempotent attempts, HMAC-verified callbacks, reconciliation
- **Notifications:** WhatsApp (Fonnte / Wablas) via queued outbox
- **Production Readiness:** Production-capable architecture (scheduler & queues configured); rotate secrets and build `public/build` via CI before publishing.

## Problem Statement

Local seafood sellers need a simple, reliable way to auction lots to buyers with transparent payment handling and delivery coordination. Common pain points: inconsistent payment flows, manual reconciliation, and unreliable notification delivery.

## Solution

Borgfish provides a full-stack Laravel solution that automates auction lifecycles, integrates a payment provider (TriPay) with HMAC-verified callbacks, and delivers WhatsApp notifications through an outbox/queue system so sellers and buyers receive reliable updates.

## Feature Highlights

- Google OAuth sign-in with admin whitelist
- WhatsApp OTP for verification and transactional notifications
- Forward & reverse auction engine with anti-sniping support
- TriPay payment attempts, webhook verification and reconciliation
- Notification outbox and queued workers for resilient delivery
- Filament admin UI for managing auctions, settlements, and disputes

## Business Flow (Seller → Completion)

1. **Seller** creates a lot (item) and schedules an auction.
2. **Auction** opens (or scheduled to start) and buyers place bids.
3. **Bid** activity is tracked; leader/winner is determined at close.
4. **Payment**: winner pays via TriPay; system records payment attempts and verifies callbacks.
5. **Packing**: seller prepares item for pickup after payment confirmation.
6. **Pickup**: logistics partner collects the lot and updates fulfillment state.
7. **Completion**: buyer confirms receipt; funds are released to the seller and transaction is closed.

## Technical Highlights

- **Laravel 13**: application core and routing, Eloquent models, jobs, and scheduling.
- **Filament**: admin interface for operational tasks and monitoring.
- **MySQL / SQLite**: primary relational storage (MySQL recommended in production).
- **Google OAuth (Socialite)**: user sign-in and admin whitelist.
- **TriPay Integration**: create payment, verify HMAC callback, reconcile pending attempts.
- **WhatsApp OTP (Fonnte / Wablas)**: OTP verification and transactional messages.
- **Queue Processing**: background workers handle notifications, reconciliation, and long-running tasks.
- **Scheduler**: cron-driven scheduler manages auction automation and maintenance jobs.

## Architecture (simple)

```mermaid
flowchart LR
	U[User (Browser / Mobile)] -->|HTTP| F[Frontend (Vite + Tailwind)]
	F -->|API| B[Backend (Laravel 13)]
	B --> DB[(MySQL / SQLite)]
	B --> Q[Queue & Workers]
	B --> S[Storage (public/ S3)]
	B --> TP[TriPay (Payments)]
	B --> WA[WhatsApp Provider (Fonnte / Wablas)]
	F -->|OAuth| G[Google OAuth]
	Q --> TP
	Q --> WA
```

## Why This Project Matters

Borgfish targets a real operational gap for small-to-medium seafood sellers who need a reliable auction platform that ties bids to verifiable payments and delivery. By combining payment HMAC verification, queued notifications, and an admin UI for dispute resolution, the project demonstrates practical solutions to trust, automation, and operational scale.

## Future Improvements

- Add GitHub Actions CI workflow: run tests, static analysis (PHPStan) and build assets.
- Add end-to-end tests (Laravel Dusk / Playwright) and expand unit coverage.
- Harden security: secrets rotation guide, add `SECURITY.md`, and automated secret scanning in CI.
- Improve observability: structured logging, metrics, and error reporting (Sentry/Prometheus).
- Add Docker Compose for consistent local development and a lightweight production image.
- Consider role-based ACL in Filament and stricter mass-assignment guards on models.


## Quick Install (Developer / Local)
1. Clone the repo:

```bash
git clone <your-repo-url>
cd Borgfish
```

2. Ensure PHP, Composer, Node.js are installed (PHP 8.3 recommended).

3. Install PHP dependencies and frontend packages:

```bash
composer install --prefer-dist --no-interaction
cp .env.example .env
php artisan key:generate
npm ci
npm run build
```

4. Create local database and run migrations + seed (local/test only):

```bash
# SQLite example
php artisan migrate --seed

# or for MySQL
php artisan migrate --seed
```

5. Run local server:

```bash
php artisan serve
```

Demo user seeded (local/testing only): `test@example.com` (created by `DatabaseSeeder`). To exercise Google OAuth flows, configure `GOOGLE_CLIENT_*` values (see Environment section).

## Environment Setup
Edit `.env` (never commit `.env` to git). Minimum values to set:

- `APP_URL` — application URL used for redirects
- `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET`, `GOOGLE_REDIRECT_URI`
- `TRIPAY_*` keys: `TRIPAY_API_KEY`, `TRIPAY_PRIVATE_KEY`, `TRIPAY_MERCHANT_CODE`, and sandbox values
- `QUEUE_CONNECTION` — recommended `database` for local
- `FILESYSTEM_DISK=public`

See `.env.example` for full list. IMPORTANT: Remove or rotate any secrets if they were committed accidentally.

## Queue Setup
This app uses queued jobs for notifications and background tasks. Recommended local setup:

```bash
php artisan queue:table && php artisan migrate
php artisan queue:work --sleep=3 --tries=3
```

Production: configure Supervisor or systemd to run `php artisan queue:work` as a service.

Example Supervisor unit (Ubuntu):

```ini
[program:borgfish-queue]
command=php /path/to/borgfish/artisan queue:work --sleep=3 --tries=3
process_name=%(program_name)s_%(process_num)02d
numprocs=1
autostart=true
autorestart=true
user=www-data
stdout_logfile=/var/log/borgfish-queue.log
redirect_stderr=true
environment=APP_ENV="production",APP_DEBUG="false"
```

## Scheduler Setup
Add a cron entry on the host that runs every minute:

```cron
* * * * * cd /path/to/borgfish && php artisan schedule:run >> /dev/null 2>&1
```

Note: scheduled jobs are currently registered in `bootstrap/app.php`.

## Payment Gateway (TriPay) Setup
1. Set TriPay credentials in `.env` (sandbox credentials for testing).
2. Configure `TRIPAY_CALLBACK_URL` to `https://your-domain/api/tripay/callback` and ensure route is reachable.
3. Verify callback signature handling: the app expects HMAC SHA256 callback signature.

## Deployment Guide
- Use CI to run tests, static analysis, and build assets (see `.github/workflows/` suggestion in docs).
- Do not commit `.env`, `public/build`, `vendor/`, or `node_modules/`.
- Ensure `storage/` and `bootstrap/cache` are writable by web server.

## Demo / Seeded Accounts
- Local seed: `test@example.com` (created by `DatabaseSeeder` in `local`/`testing` environments).
- Admin panel: sign in with an admin Google account listed in `ADMIN_GOOGLE_WHITELIST` or create admin via tinker in local.

## Cleaning before publishing (must do)
1. Remove database dumps and secret files from repo history (CRITICAL):

```bash
git rm --cached borg_fish.sql .phpunit.result.cache .env public/build
echo ".env" >> .gitignore
git add .gitignore
git commit -m "Remove sensitive artifacts before publishing"
```

If secrets were previously committed, purge them from history (backup & coordinate with any collaborators):

```bash
# Make a backup branch
git branch backup-main
pip install git-filter-repo
git filter-repo --invert-paths --path borg_fish.sql --path .env --path .phpunit.result.cache --path public/build
git push --force origin --all
git push --force origin --tags
```

2. Rotate any API keys that were exposed.

## Useful Commands
- Run tests: `php artisan test`
- Static analysis (recommended): `vendor/bin/phpstan analyse`
- Database migrations: `php artisan migrate`
- Seed db (local): `php artisan db:seed`

## Docs
- Production queue + scheduler notes: `docs/production-queue-scheduler.md`
- QA & E2E checklist: `docs/qa-end-to-end-auction.md`

---
If you want, I can also:
- Add a CI workflow that runs tests and static analysis.
- Add `SECURITY.md` and `CONTRIBUTING.md` templates.
- Remove sensitive files from git history (I can prepare the exact `git-filter-repo` command sequence and perform the edits if you confirm).

---
License: add a license file (MIT recommended) before publishing to GitHub.

