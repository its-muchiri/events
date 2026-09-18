<?php
/**
 * One vendor card (directory + homepage). Expects $vendor (see
 * VendorDirectory::present), optional $bundleId, and $showCompare (bool).
 */
use EventCo\Core\View;

$query = isset($bundleId) && $bundleId ? '?bundle_id=' . urlencode((string) $bundleId) : '';
?>
<article class="card vendor-card">
  <a href="/vendors/<?= (int) $vendor['id'] . $query ?>" tabindex="-1" aria-hidden="true">
    <img class="photo" src="<?= View::e($vendor['cover_photo']) ?>" alt="" loading="lazy" width="800" height="600">
  </a>
  <h3><a href="/vendors/<?= (int) $vendor['id'] . $query ?>" style="text-decoration:none;color:inherit;"><?= View::e($vendor['business_name']) ?></a></h3>
  <div class="card__meta"><?= View::e($vendor['category_label']) ?> · <?= View::e($vendor['service_area']) ?></div>
  <div><?= View::ratingHtml($vendor['average_rating'], $vendor['review_count']) ?><?= $vendor['is_verified'] ? ' <span class="status-badge">Verified</span>' : '' ?></div>
  <div class="vendor-card__price"><?= View::e(View::priceLabel($vendor['base_price'], $vendor['pricing_model'])) ?></div>
  <a href="/vendors/<?= (int) $vendor['id'] . $query ?>" class="btn btn--secondary">View vendor</a>
  <?php if (!empty($showCompare)): ?>
    <label class="compare-toggle">
      <input type="checkbox" class="js-compare"
        data-id="<?= (int) $vendor['id'] ?>"
        data-name="<?= View::e($vendor['business_name']) ?>"
        data-category="<?= View::e($vendor['category_label']) ?>"
        data-price="<?= View::e(View::priceLabel($vendor['base_price'], $vendor['pricing_model'])) ?>"
        data-rating="<?= View::e($vendor['review_count'] > 0 ? number_format($vendor['average_rating'], 1) . ' (' . $vendor['review_count'] . ' reviews)' : 'No reviews yet') ?>"
        data-events="<?= (int) $vendor['completed_events_count'] ?>"
        data-area="<?= View::e($vendor['service_area']) ?>"
        data-verified="<?= $vendor['is_verified'] ? 'Yes' : 'Not yet' ?>"
        data-photo="<?= View::e($vendor['cover_photo']) ?>">
      Compare
    </label>
  <?php endif; ?>
</article>
