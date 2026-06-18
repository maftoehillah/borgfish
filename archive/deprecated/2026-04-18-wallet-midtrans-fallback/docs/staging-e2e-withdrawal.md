Staging E2E — Withdrawal / Payout flow
=====================================

Overview
--------
This guide explains how to run an end-to-end payout (withdrawal) test against a staging deployment that is configured to use a payment gateway sandbox (e.g. Midtrans/Xendit sandbox).

Prerequisites
-------------
- A staging deployment of the application reachable from the payment gateway (public webhook URL).
- Staging `.env` configured to use the gateway sandbox and `WALLET_MODE=REAL`.
- Queue worker running on staging (recommended) or be prepared to run the payment job manually using the artisan command below.

Required environment variables (examples)
----------------------------------------
Set these in staging `.env` (values depend on gateway vendor):

```bash
APP_ENV=staging
WALLET_MODE=REAL
PAYMENT_GATEWAY=midtrans
MIDTRANS_SERVER_KEY=sk_test_xxx
MIDTRANS_BASE_URL=https://api.sandbox.midtrans.com
MIDTRANS_PAYOUT_URL=https://api.sandbox.midtrans.com/v1/payouts

# for xendit example
# PAYMENT_GATEWAY=xendit
# XENDIT_SECRET=secret_xxx
# XENDIT_CALLBACK_TOKEN=...
```

Webhook
-------
Configure the gateway sandbox to send payout/webhook events to your staging app's webhook endpoint, typically:

```
https://staging.example.com/webhook/payment
```

Run the E2E command
-------------------
On the staging server (or in a shell with access to the staging codebase), run:

```bash
# optionally pass --force if APP_ENV is not set to 'staging'
php artisan e2e:withdrawal --force

# or choose a scenario (future scenarios may be added)
php artisan e2e:withdrawal success --force
```

What the command does
---------------------
- Creates (or re-uses) a test buyer and admin user.
- Credits the buyer wallet with test funds.
- Requests a withdrawal and approves it (admin action).
- Attempts to run the payout job synchronously (if a queue worker isn't running).
- Polls the withdrawal until it reaches a final state (PAID/FAILED) and reports results.
- Reports whether an outbox entry and in-app notification were created.

Post-checks
-----------
- Inspect the provider sandbox dashboard for the payout request and its status.
- Verify the `withdrawals` row has `status` = `PAID` and `payout_external_id` set.
- Verify `notification_outbox` and `in_app_notifications` entries exist for the test user.
- Confirm webhook delivery logs (if provider uses webhooks) and reconcile job output.

Notes and safety
----------------
- This command is intended for staging only. It will create test users and perform real sandbox payouts.
- Do not copy real production credentials into staging tests.
- If you prefer not to run jobs synchronously, run a queue worker on staging and omit the `--force` usage.

If you want, I can: generate CI job configuration to run this command automatically on staging, or help with wiring the gateway webhook to the staging URL.
