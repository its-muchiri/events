/**
 * Status Timeline — shared component. This platform's per-booking lifecycle
 * is defined in planning/05-event-co-ke/database-schema.md's
 * event_bookings.status enum.
 * @param {{ steps: string[], currentIndex: number }} props
 * @returns {HTMLElement}
 */
export function createStatusTimeline({ steps, currentIndex }) {
  const list = document.createElement("ol");
  list.className = "status-timeline";

  steps.forEach((label, index) => {
    const item = document.createElement("li");
    item.className = "status-timeline__step" + (index <= currentIndex ? " status-timeline__step--done" : "");
    item.textContent = label;
    list.appendChild(item);
  });

  return list;
}

export const EVENT_BOOKING_STEPS = ["Requested", "Confirmed", "Fulfilled", "Completed"];
