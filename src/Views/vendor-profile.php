<?php
/** @var array|null $vendor */
/** @var int $vendorId */
/** @var string|null $bundleId */
/** @var string|null $dbError */
use EventCo\Core\View;
?>
<?php if ($dbError): ?>
  <p class="card__meta"><?= View::e($dbError) ?></p>
<?php elseif (!$vendor): ?>
  <h1>Vendor #<?= $vendorId ?></h1>
  <p class="card__meta">No vendor found with this ID.</p>
<?php else: ?>
  <h1><?= View::e($vendor['business_name']) ?></h1>
  <p class="card__meta"><?= View::e(ucfirst($vendor['category'])) ?> · <?= View::e($vendor['service_area']) ?></p>
  <div id="standing-badge" style="margin: var(--ac-space-2) 0 var(--ac-space-4);"></div>

  <p>KES <?= number_format((float) $vendor['base_price']) ?> (<?= View::e(str_replace('_', ' ', $vendor['pricing_model'])) ?>)</p>

  <div id="sticky-cta-container"></div>

  <script type="module">
    import { createStatusBadge } from "/assets/js/components/status-badge.js";
    import { createStickyCta } from "/assets/js/components/sticky-cta.js";

    const standing = <?= json_encode($vendor['standing']['standing_status'] ?? null) ?>;
    const tone = { good_standing: "success", warning: "warning", suspended: "danger" }[standing] ?? "neutral";
    document.getElementById("standing-badge").appendChild(
      createStatusBadge({ label: standing ? standing.replace(/_/g, " ") : "No standing record yet", tone })
    );

    document.getElementById("sticky-cta-container").appendChild(
      createStickyCta({
        label: <?= json_encode($bundleId ? 'Add to bundle #' . $bundleId : 'Book this vendor') ?>,
        onClick: () => { window.location.href = "/bookings/new?vendor_id=<?= $vendorId ?>&category=<?= urlencode($vendor['category']) ?><?= $bundleId ? '&bundle_id=' . urlencode($bundleId) : '' ?>"; },
      })
    );
  </script>
<?php endif; ?>
