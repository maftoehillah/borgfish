# Go-Live Checklist: Auction Lifecycle Engine

## 1. Schema and Data

- [ ] `php artisan migrate --force` successfully applies `2026_04_09_000014_add_auction_lifecycle_and_fallback_support`.
- [ ] Existing lots have `auction_state` backfilled (`AKTIF`, `SELESAI`, `DIBAYAR`) as expected.
- [ ] Indexes exist on critical tables:
  - [ ] `auction_rankings (ikan_id, rank)`
  - [ ] `payment_attempts (status, bayar_sebelum)`
  - [ ] `auction_state_logs (ikan_id, created_at)`
  - [ ] `users (is_blacklisted, auction_cooldown_until)`

## 2. Scheduler and Jobs

- [ ] Scheduler is active every minute (`lelang:cek`) in production runtime.
- [ ] Monitor confirms `lelang:cek` runs continuously without drift.
- [ ] Alerting configured for failed scheduler runs.

## 3. Lifecycle and Fairness Checks

- [ ] Auction close freezes ranking snapshot (`auction_rankings`) once (immutable behavior).
- [ ] Winner assignment sets:
  - [ ] `auction_state = MENUNGGU_PEMBAYARAN`
  - [ ] `current_winner_rank`
  - [ ] `bayar_sebelum` based on lot `payment_deadline_minutes`
- [ ] Expired winner triggers:
  - [ ] penalty + cooldown + reputation drop
  - [ ] fallback to next rank (if eligible)
  - [ ] hard stop when fallback limit/reserve/all-bidder condition hits

## 4. Payment and Webhook Safety

- [ ] Midtrans signature validation is enforced.
- [ ] Duplicate webhook does not create inconsistent state.
- [ ] Paid webhook updates:
  - [ ] `auction_state = DIBAYAR`
  - [ ] current `payment_attempt` status to `dibayar`
- [ ] Expired webhook updates:
  - [ ] triggers fallback engine once (idempotent behavior)

## 5. Admin and Operations

- [ ] Admin can see lifecycle fields in transaction panel (`auction_state`, winner rank, fallback count).
- [ ] Admin override action requires reason and writes audit log (`auction_state_logs`).
- [ ] User panel exposes controls for `is_blacklisted`, `auction_cooldown_until`, and `reputation_score`.
- [ ] Dispute panel available and functional (`TransactionDisputeResource`) for open/resolved cases.
- [ ] Transaction state audit panel available (`TransactionStateLogResource`) with actor/reason trace.

## 6. Seller UX Verification

- [ ] Seller upload form accepts `reserve_price` and `payment_deadline_minutes`.
- [ ] Seller detail page shows lifecycle fields (state, active rank, fallback count, hard stop reason).
- [ ] Seller dashboard cards show reserve/deadline and fallback/hard-stop badges.
- [ ] Seller receives in-app notification when payment settles and when dispute opens.

## 6b. Buyer UX Verification

- [ ] Buyer sees fulfillment state (`DIBAYAR`, `DIPROSES_PENJUAL`, `DIKIRIM`, `SELESAI`, `GAGAL`, `DISENGKETAKAN`) clearly on activity pages.
- [ ] Buyer can submit complaint only on active fulfillment states (`DIBAYAR`, `DIPROSES_PENJUAL`, `DIKIRIM`).
- [ ] Buyer receives in-app notification for payment settled, shipment update, and dispute updates.

## 7. Concurrency Hardening Test Set

Run before release:

```bash
php artisan test --filter=AuctionLifecycleEngineTest
php artisan test --filter=PaymentWebhookTest
php artisan test --filter=ReturnUrlFlowTest
```

Expected:

- Expiry handler is idempotent (no double fallback).
- Webhook and scheduler paths remain consistent.
- Return-url and payment flows still valid.

## 8. Full Regression Gate

- [ ] `php artisan test` passes in CI and staging.
- [ ] Lightweight load baseline executed with k6:
  - [ ] `k6 run tests/load/k6-auction-lifecycle.js -e BASE_URL=https://staging.example.com`
  - [ ] Optional lot polling: add `-e LOT_ID=<active_lot_id>`
  - [ ] Verify thresholds pass (`http_req_failed < 1%`, `p95 < 1200ms`)
- [ ] Advanced load scenarios executed (staging only):
  - [ ] Authenticated bid pressure: `k6 run tests/load/k6-authenticated-bid.js ...`
  - [ ] Webhook out-of-order replay: `k6 run tests/load/k6-webhook-out-of-order.js ...`
- [ ] Load-test procedure documented and accessible: `docs/auction-load-test-runbook.md`
- [ ] Staging smoke test for end-to-end scenario:
  1. create lot
  2. bids from >=2 users
  3. close lot
  4. let first winner expire
  5. verify fallback rank moves correctly
  6. settle payment of fallback winner

## 9. Rollback Plan

- [ ] Backup database snapshot before deployment.
- [ ] Rollback command prepared:
  - [ ] `php artisan migrate:rollback --step=2`
- [ ] Feature-flag or emergency switch prepared to disable bidder actions if needed.
