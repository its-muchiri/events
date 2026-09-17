# Shared Modules — Integration Point

Placeholder directory. event.co.ke is sequenced third (see `planning/00-portfolio/build-sequencing-roadmap.md`), specifically **after** rider.co.ke, because it depends on the Booking/Availability Engine's v2 multi-vendor bundling capability — that capability must not be scheduled before the shared core is proven on rider.co.ke's real-time dispatch extension.

Once shared modules exist, `src/Controllers/BundleController.php`'s inline `TODO`s (atomic multi-vendor availability locking, replacement-vendor matching) should delegate here instead of reimplementing per platform. The last-minute price-differential policy and Event Coordinator unilateral-action authority (open-questions.md #1-#2) are business/policy decisions, not shared-module concerns — they need to be resolved before this controller's cancellation-handling logic can be completed regardless of which module owns the code.
