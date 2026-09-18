# Tests

No test runner is wired up yet. `tests/vendor_directory_test.php` is a standalone assertion script for the vendor directory (`php database/seed_demo.php && php tests/vendor_directory_test.php`). Recommended: PHPUnit for `src/`, a small assertion runner (or Vitest) for `public/assets/js/`.

Priority areas once real business logic lands:

- **Atomic multi-vendor availability locking on bundle confirm** — the highest-priority test in this platform: verify a bundle can never be confirmed with one vendor's date already taken by another booking (see `BundleController::confirm`'s TODO)
- `bundle_cancellation_incidents.days_until_event` calculation and urgency routing
- Replacement-vendor matching logic once implemented (same category, area, date availability)
- M-Pesa/card callback idempotency
