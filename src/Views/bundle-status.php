<?php
/** @var array|null $bundle */
/** @var int $bundleId */
/** @var array $incidents */
/** @var string|null $dbError */
use EventCo\Core\View;
?>
<h1>Bundle #<?= $bundleId ?></h1>

<?php if ($dbError): ?>
  <p class="card__meta"><?= View::e($dbError) ?></p>
<?php elseif (!$bundle): ?>
  <p class="card__meta">No bundle found with this ID.</p>
<?php else: ?>
  <p class="card__meta">
    <?= View::e(ucfirst($bundle['event_type'])) ?> · <?= View::e($bundle['event_date']) ?> · <?= View::e($bundle['event_location_address']) ?>
  </p>
  <p>Total: KES <?= number_format((float) $bundle['total_bundle_amount']) ?> · Combined deposit: KES <?= number_format((float) $bundle['combined_deposit_amount']) ?></p>

  <div id="bundle-status-badge" style="margin: var(--ac-space-2) 0 var(--ac-space-4);"></div>

  <?php if ($bundle['status'] === 'at_risk' && !empty($incidents)): ?>
    <div class="card" style="border-color: var(--ac-warning); margin-bottom: var(--ac-space-4);">
      <strong>A vendor in this bundle cancelled</strong>
      <p class="card__meta">The coordinator team has been notified and is finding a replacement. This bundle stays "at risk" until resolved.</p>
    </div>
  <?php endif; ?>

  <h2>Vendors in this bundle</h2>
  <div id="bundle-builder-container" style="max-width: 32rem;"></div>

  <script type="module">
    import { createStatusBadge } from "/assets/js/components/status-badge.js";
    import { createBundleBuilder, markSlotFilled } from "/assets/js/components/bundle-builder.js";

    const bundleStatus = <?= json_encode($bundle['status']) ?>;
    const BADGE_TONE = { assembling: "neutral", confirmed: "accent", at_risk: "warning", completed: "success", cancelled: "danger" };
    document.getElementById("bundle-status-badge").appendChild(
      createStatusBadge({ label: bundleStatus.replace(/_/g, " "), tone: BADGE_TONE[bundleStatus] ?? "neutral" })
    );

    const bookings = <?= json_encode($bundle['bookings']) ?>;
    const allCategories = ["photographer", "caterer", "venue", "decor", "entertainment"];
    const filledCategories = new Map(bookings.map((b) => [b.category, b]));

    const builder = createBundleBuilder({
      categories: allCategories,
      onSelectVendor: (category) => { window.location.href = `/vendors?category=${encodeURIComponent(category)}&bundle_id=<?= $bundleId ?>`; },
    });
    document.getElementById("bundle-builder-container").appendChild(builder);

    for (const [category, booking] of filledCategories) {
      markSlotFilled(builder, category, `Booking #${booking.id} (${booking.status})`);
    }
  </script>
<?php endif; ?>
