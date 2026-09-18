# MVP Status — Events (event.co.ke)

STATE: IN_PROGRESS

## Planning references
- Product spec: ../planning/05-event-co-ke/prd.md
- Data model: ../planning/05-event-co-ke/database-schema.md
- API contract: ../planning/05-event-co-ke/api-endpoints.md
- User flows: ../planning/05-event-co-ke/user-flows.md
- Open questions (stakeholder-pending decisions): ../planning/05-event-co-ke/open-questions.md
- Shared modules (auth, payments/escrow, booking engine, KYC, reviews): ../planning/00-portfolio/shared-architecture.md
- Authoritative MVP feature cut & build order: ../planning/00-portfolio/build-sequencing-roadmap.md

## What "MVP complete" means for this project
Per build-sequencing-roadmap.md, event.co.ke is third in the build order. Its MVP is: single-vendor booking (book one photographer/caterer/etc. independently), M-Pesa payment, and a basic e-commerce store (event supplies/decor) — built on shared modules 1–4 plus module 6 (Review & Dispute Console v1). **Multi-vendor bundles and date-locking are explicitly V2, and the roadmap says this must not be scheduled before rider.co.ke has launched** (module 8, Booking/Availability Engine v2, depends on module 3 being stable in production). This project's code already has BundleController.php and bundle-new.php/bundle-status.php views — that's fine to keep, but do not treat bundle features as required for DONE, and flag it as an open question if bundle work is progressing ahead of rider.co.ke's booking-engine v2 readiness.

- Customer can sign up / log in
- Customer can browse/search vendors by category (venue, catering, photography, decor, entertainment, etc.) and compare pricing/ratings/portfolio
- Customer can book a single vendor for their event date and pay a deposit via M-Pesa
- Customer can submit a review after the event
- Customer can buy event supplies/decor from the store
- Vendor can accept/decline a booking request and update its status
- Admin/coordinator can view bookings and triage disputes (vendor no-show/cancellation)
- Every image slot (homepage hero, vendor directory/profile portfolio photos, store product photos for decor/supplies) shows a real, topically relevant photo sourced from Unsplash — not a placeholder box or broken image
- App builds and runs with zero errors, works on mobile width
- Core flow (browse vendors → book one → pay deposit → track → review) covered by a smoke test

## Checklist
Controllers already exist for most of this (src/Controllers/*) — verify against the spec above and the planning docs rather than assuming they're complete, and rather than rebuilding from scratch.

- [x] Identity/auth (customer, vendor, admin) — session-cookie auth (src/Core/Auth.php, src/Controllers/AuthController.php), mirrored from laundry.co.ke/rider.co.ke's reference implementation. Signup/login/logout at /signup, /login, /logout (HTML) and /api/v1/auth/{register,login,logout,me} (JSON). All customer_id/vendor_id-writing controller actions now guard with `Auth::requireUser()` instead of trusting a spoofable `$request->user['id'] ?? null`. Admin accounts are seeded directly in the DB (no self-service admin signup), consistent with the sibling platforms.
- [ ] Vendor directory browse/search by category (verify VendorController.php, vendor-index.php, vendor-profile.php)
- [ ] Single-vendor booking + M-Pesa deposit payment (verify BookingController.php, PaymentController.php)
- [ ] Store for event supplies/decor (verify StoreController.php against prd.md's E-Commerce Store Scope — note the flagged ambiguity there about date-bound rental items like tents/chairs being booking-engine items, not plain store SKUs)
- [ ] Review submission (verify ReviewController.php)
- [ ] Dispute path for vendor no-show/cancellation (verify DisputeController.php)
- [ ] Real Unsplash photography (verified resolving URLs) for hero imagery on home.php, vendor-index.php/vendor-profile.php portfolio photos per category (venue/catering/photography/decor), and store product photos — no placeholders or broken images
- [ ] Error handling for the core single-vendor flow (vendor unavailable, failed payment)
- [ ] Smoke test / manual run-through of the full core flow passes
- [ ] Remove stubs, TODOs, placeholder data
- [ ] Cross-check against open-questions.md — where it conflicts with an assumption made here, note the assumption taken and continue (don't stop to ask)

Not MVP per the roadmap, and explicitly sequence-gated on rider.co.ke — don't block DONE on these even though BundleController.php and the bundle-*.php views already exist: multi-vendor event bundles, date-locking across vendors, multi-vendor cancellation/replacement flow, event planning dashboard, loyalty/referral program.

## Known issues / open questions
- Multi-vendor bundling (BundleController.php) is built ahead of its stated shared-module dependency (Booking/Availability Engine v2, gated on rider.co.ke's booking engine v1 being stable in production per build-sequencing-roadmap.md). Verify rider.co.ke's status before extending bundle features further; note here rather than stopping to ask.
- The rental-equipment-as-store-vs-booking question (tents/chairs/sound systems) is explicitly unresolved in prd.md — pick a reasonable default (booking-engine mechanism, per prd.md's own reasoning), log it here, and continue.
- There is no vendor-onboarding *page* (only the JSON POST /api/v1/vendors/onboard endpoint) — AuthController redirects a new vendor signup to /vendors rather than a dedicated onboarding form, since that view doesn't exist yet. A later pass should build src/Views/vendor-onboard.php + a PageController route so vendors can submit KYC docs/listing details through the browser, not just the API.
- PaymentController::stkPush/card are still stubs (return a canned 202 with no real Daraja/card-gateway call and no `payments`/`escrow_transactions` row) — now correctly gated behind `Auth::requireUser()`, but the M-Pesa STK push integration itself (checklist item 3) is unbuilt. Do that next.
- Local dev smoke-tested this pass against a MySQL instance already listening on 127.0.0.1:3306 (matches .env) with the schema.sql tables present — signup, login, session cookie, /api/v1/auth/me, authenticated vs. unauthenticated booking creation, and logout were all verified working end-to-end via curl.

## Changelog
- 2026-09-17: Initial checklist created (assumed generic scope, not sourced from planning/)
- 2026-09-18: Rewritten against planning/05-event-co-ke and shared-architecture.md; checklist now reflects the roadmap's authoritative MVP cut (single-vendor only) and flags that bundle work already exists ahead of its sequencing dependency
- 2026-09-18: Implemented Identity/auth module end-to-end (src/Core/Auth.php, src/Controllers/AuthController.php, signup/login views, session-user injection in View/Request/layout nav) — mirrored laundry.co.ke's and rider.co.ke's reference pattern for portfolio consistency. Replaced every unguarded `$request->user['id'] ?? null` write (bookings, bundles, reviews, disputes, store orders, vendor onboarding/save/business-account, payment earnings) with an `Auth::requireUser()` guard, closing the "anyone can book/review/dispute as customer_id=null (or spoof any id)" gap the app previously shipped with. Verified end-to-end against a live local MySQL instance: register, duplicate-phone rejection, login (JSON + HTML form), session cookie, /api/v1/auth/me, 401 on unauthenticated booking, successful authenticated booking with correct customer_id, logout.
