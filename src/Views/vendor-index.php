<?php
/** @var array $vendors */
/** @var string|null $category */
/** @var string|null $bundleId */
/** @var string|null $dbError */
use EventCo\Core\View;

$categories = ['photographer', 'caterer', 'venue', 'decor', 'entertainment', 'other'];
$bundleSuffix = $bundleId ? '&bundle_id=' . urlencode($bundleId) : '';
?>
<h1>Browse vendors</h1>
<?php if ($bundleId): ?>
  <p class="card__meta">Adding a vendor to bundle #<?= View::e($bundleId) ?>.</p>
<?php endif; ?>

<div style="display:flex; flex-wrap:wrap; gap: var(--ac-space-2); margin: var(--ac-space-4) 0;">
  <a href="/vendors?<?= ltrim($bundleSuffix, '&') ?>" class="btn <?= !$category ? 'btn--primary' : 'btn--secondary' ?>">All</a>
  <?php foreach ($categories as $c): ?>
    <a href="/vendors?category=<?= urlencode($c) . $bundleSuffix ?>" class="btn <?= $category === $c ? 'btn--primary' : 'btn--secondary' ?>"><?= View::e(ucfirst($c)) ?></a>
  <?php endforeach; ?>
</div>

<?php if ($dbError): ?>
  <p class="card__meta"><?= View::e($dbError) ?></p>
<?php elseif (empty($vendors)): ?>
  <p class="card__meta">No vendors match this filter yet in this environment.</p>
<?php else: ?>
  <div style="display:flex; flex-wrap:wrap; gap: var(--ac-space-4);">
    <?php foreach ($vendors as $vendor): ?>
      <div class="card" style="width: 16rem;">
        <h3><?= View::e($vendor['business_name']) ?></h3>
        <div class="card__meta">
          <?= View::e(ucfirst($vendor['category'])) ?> · KES <?= number_format((float) $vendor['base_price']) ?> <?= View::e(str_replace('_', ' ', $vendor['pricing_model'])) ?>
        </div>
        <a href="/vendors/<?= (int) $vendor['vendor_id'] ?><?= $bundleId ? '?bundle_id=' . urlencode($bundleId) : '' ?>" class="btn btn--secondary" style="margin-top: var(--ac-space-3);">View vendor</a>
      </div>
    <?php endforeach; ?>
  </div>
<?php endif; ?>
