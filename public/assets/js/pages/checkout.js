/**
 * Booking / bundle deposit payment page logic.
 *
 * ENFORCED RULE (per artcollect-design-system.md §7 and this platform's
 * README): this file, and the vendor-cancellation-incident admin surface,
 * must NEVER import components/scrap.js or any other decorative module.
 */
import { createStatusTimeline, EVENT_BOOKING_STEPS } from "../components/status-timeline.js";

// import { createTornEdge } from "../components/scrap.js"; // <- NEVER do this here.

export function renderBookingStatus(container, currentIndex) {
  container.classList.add("critical-flow");
  container.appendChild(createStatusTimeline({ steps: EVENT_BOOKING_STEPS, currentIndex }));
}
