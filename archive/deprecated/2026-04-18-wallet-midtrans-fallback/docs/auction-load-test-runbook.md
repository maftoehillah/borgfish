# Auction Lifecycle Load Test Runbook

This runbook provides staged load scenarios for marketplace browsing, authenticated bidding pressure, and webhook out-of-order simulation.

## Prerequisites

- k6 installed on runner machine
- Staging environment with realistic auction data
- Dedicated non-production bidder account/session for bid scenario
- Dedicated non-production Midtrans order ids for webhook scenario

## Scripts

- `tests/load/k6-auction-lifecycle.js`
- `tests/load/k6-authenticated-bid.js`
- `tests/load/k6-webhook-out-of-order.js`

## Scenario 1: Marketplace Baseline

Environment variables:

- `BASE_URL` (required), example `https://staging.example.com`
- `LOT_ID` (optional), active lot id for state polling checks

Run:

```bash
k6 run tests/load/k6-auction-lifecycle.js -e BASE_URL=https://staging.example.com
```

With lot state polling:

```bash
k6 run tests/load/k6-auction-lifecycle.js -e BASE_URL=https://staging.example.com -e LOT_ID=123
```

Acceptance baseline:

- `http_req_failed < 1%`
- `p95 http_req_duration < 1200ms`

## Scenario 2: Authenticated Bid Pressure

Use this only with staging bidder credentials and disposable lot ids.

Environment variables:

- `BASE_URL` (required)
- `LOT_IDS` (required), comma-separated lot ids, example `101,102,103`
- `AUTH_COOKIE` (required), full cookie header value for logged-in bidder session, example `laravel_session=...`
- `CSRF_TOKEN` (required), CSRF token from the same bidder session
- `BID_AMOUNT_BASE` (optional), default `100000`
- `RETURN_URL` (optional), default `/ikans`

Run:

```bash
k6 run tests/load/k6-authenticated-bid.js \
	-e BASE_URL=https://staging.example.com \
	-e LOT_IDS=101,102,103 \
	-e AUTH_COOKIE="laravel_session=..." \
	-e CSRF_TOKEN="..."
```

Acceptance baseline:

- `http_req_failed < 2%`
- `p95 http_req_duration < 1500ms`
- `bid_request_ok > 95%` (accepted statuses: `302` or `429`)

## Scenario 3: Webhook Out-of-Order

Use dedicated staging transactions only. This scenario replays webhook events in non-linear order to verify lifecycle robustness.

Environment variables:

- `BASE_URL` (required)
- `ORDER_IDS` (required), comma-separated Midtrans order ids in staging
- `SERVER_KEY` (required), staging Midtrans server key for valid signatures
- `GROSS_AMOUNT` (optional), default `150000.00`
- `PAYMENT_TYPE` (optional), default `bank_transfer`
- `EVENT_SEQUENCE` (optional), default `pending,settlement,pending,expire`

Run:

```bash
k6 run tests/load/k6-webhook-out-of-order.js \
	-e BASE_URL=https://staging.example.com \
	-e ORDER_IDS=BORGFISH-ORDER-1,BORGFISH-ORDER-2 \
	-e SERVER_KEY=SB-Mid-server-xxxx
```

Acceptance baseline:

- `http_req_failed < 2%`
- `p95 http_req_duration < 1000ms`
- `webhook_accepted > 98%` (accepted statuses: `200` or `422`)

## Safety Notes

- Never run these scripts on production.
- Isolate lots/orders used for load test to avoid impacting real operational metrics.
- Rotate bidder session and Midtrans test artifacts after each test cycle.
