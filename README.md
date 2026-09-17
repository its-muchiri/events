# event.co.ke

Scaffold for the event.co.ke marketplace platform. See `/planning/05-event-co-ke/` for the full PRD, user flows, database schema, API spec, and open questions this scaffold implements a starting skeleton of.

## Stack
PHP (no framework, PSR-4 autoloaded) + vanilla JS/CSS + relational SQL (MySQL/MariaDB), per `/planning/00-portfolio/shared-architecture.md`.

## Design system
Tokens in `public/assets/css/tokens.css` are ported from the real artcollect.co.ke system documented in `/planning/00-portfolio/artcollect-design-system.md`, with the **platform accent set to hot-pink** (artcollect's "graffiti" lane accent) — chosen for its celebratory association, a natural fit for events. Only the token architecture, the collage/pixel decorative primitives, and the motion/accessibility governance rules are adopted; the heavier graffiti *texture/filter* treatment and 3D/diorama treatments are intentionally **not** ported here — this platform uses hot-pink only as a flat accent color, not the graffiti spray-filter effect itself.

This platform does **not** use a FAB (see `planning/00-portfolio/design-system.md`'s assumption that construction/solar/event use a sticky in-page CTA instead) — see `public/assets/js/components/sticky-cta.js`.

**Critical-flow rule (enforced, not just documented):** `public/assets/js/pages/checkout.js` (deposit/booking payment) and the vendor-cancellation-incident admin surface must never import `components/scrap.js` or any decorative module — a date-locked, high-stress cancellation flow deserves the calmest possible UI, not decoration.

## Structure

```
public/                 Web root — front controller, static assets
  index.php             Front controller: bootstraps Router, dispatches request
  assets/css/           tokens.css, reset.css, main.css
  assets/js/            main.js, components/, pages/
src/
  Config/               Database connection (PDO)
  Core/                 Router, Request, Response
  Controllers/          BookingController, BundleController, PaymentController, ReviewController, DisputeController
  Models/               Data-access classes
  Modules/              Placeholder for shared-module integration points (Booking Engine v2 multi-vendor, Escrow, KYC)
database/
  schema.sql            Shared core tables + this platform's extension tables (event_bookings, event_bundle, bundle_cancellation_incidents, ...)
routes/
  api.php               Route table — mirrors planning/05-event-co-ke/api-endpoints.md
```

## Getting started

1. Copy `.env.example` to `.env` and fill in database + M-Pesa Daraja + card-gateway credentials.
2. Create the database and run `database/schema.sql` against it.
3. Point your web server's document root at `public/`, with all requests rewritten to `public/index.php`.
4. `composer install` if/when shared-module packages are added as dependencies.

## What this scaffold is (and isn't)

This is a **starting skeleton**: `BundleController` wires up the request/response shape for assembling and confirming a multi-vendor bundle and for reporting a vendor cancellation, but does not implement the actual replacement-vendor matching, the last-minute price-differential policy (unresolved — see `open-questions.md` #1), or the Event Coordinator's unilateral-action authority for time-critical cancellations (also unresolved — see `open-questions.md` #2). It also does not yet implement the atomic multi-vendor availability lock on bundle confirmation — see `BundleController::confirm`'s TODO, and `tests/README.md` for why this is the highest-priority gap to close before this platform can be trusted with real bookings. Every stub references the planning doc section it should eventually implement.

**Implemented in this scaffold:** single-vendor bookings, bundle assembly/confirmation, vendor-cancellation incident reporting, payments (stub), reviews, disputes, vendor onboarding, vendor directory browsing/profile/favorites, business accounts, and the e-commerce store.
