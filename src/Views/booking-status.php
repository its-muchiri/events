<?php
/** @var array|null $booking */
/** @var int $bookingId */
/** @var string|null $dbError */
use EventCo\Core\View;
?>
<h1>Booking #<?= $bookingId ?></h1>

<?php if ($dbError): ?>
  <p class="card__meta"><?= View::e($dbError) ?></p>
<?php elseif (!$booking): ?>
  <p class="card__meta">No booking found with this ID.</p>
<?php else: ?>
  <p class="card__meta">
    <?= View::e(ucfirst($booking['category'])) ?> · Event date <?= View::e($booking['event_date']) ?>
  </p>
  <p>Total: KES <?= number_format((float) $booking['total_amount']) ?> · Deposit: KES <?= number_format((float) $booking['deposit_amount']) ?></p>

  <div id="status-badge-container" style="margin-top: var(--ac-space-2);"></div>
  <div id="status-timeline-container" style="max-width: 40rem; margin-top: var(--ac-space-4);"></div>

  <script type="module">
    import { createStatusTimeline, EVENT_BOOKING_STEPS } from "/assets/js/components/status-timeline.js";
    import { createStatusBadge } from "/assets/js/components/status-badge.js";

    const status = <?= json_encode($booking['status']) ?>;
    const STEP_INDEX = { requested: 0, confirmed: 1, fulfilled: 2, completed: 3 };
    const BADGE_TONE = {
      requested: "neutral", confirmed: "accent", fulfilled: "accent", completed: "success",
      at_risk: "warning", replaced: "warning", cancelled: "danger", disputed: "danger",
    };

    document.getElementById("status-badge-container").appendChild(
      createStatusBadge({ label: status.replace(/_/g, " "), tone: BADGE_TONE[status] ?? "neutral" })
    );

    if (STEP_INDEX[status] !== undefined) {
      document.getElementById("status-timeline-container").appendChild(
        createStatusTimeline({ steps: EVENT_BOOKING_STEPS, currentIndex: STEP_INDEX[status] })
      );
    }
  </script>
<?php endif; ?>
