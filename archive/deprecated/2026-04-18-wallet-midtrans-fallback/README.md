Deprecated artifacts archived during the auction marketplace refactor.

Reason:
- Internal balance, top up, withdraw, wallet, Midtrans, and fallback-winner flows were removed from the active system.
- Active payment flow now uses ThreePay/Tripay-style payment attempts with internal order/payment IDs and provider transaction IDs.
- Failed winner payment now records a violation instead of reassigning payment to fallback bidders.

These files are kept for historical reference only and are intentionally outside the active Laravel app paths.
