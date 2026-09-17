<?php
/** @var array $vendors */
/** @var string|null $dbError */
use EventCo\Core\View;
?>
<section style="padding-block: var(--ac-space-8) var(--ac-space-12);">
  <h1 style="font-size: 2.5rem; max-width: 32rem;">Book event vendors — one at a time, or all at once.</h1>
  <p style="max-width: var(--ac-measure); margin-block: var(--ac-space-4);">
    Photographers, caterers, venues, and more — book a single vendor directly, or assemble a full event bundle with one combined deposit.
  </p>
  <div style="display:flex; gap: var(--ac-space-3);">
    <a href="/vendors" class="btn btn--secondary">Browse vendors</a>
    <a href="/bundles/new" class="btn btn--primary">Build a bundle</a>
  </div>
</section>

<section style="padding-block: var(--ac-space-8); border-block: 1px solid var(--ac-paper-deep);">
  <h2>How it works</h2>
  <div style="display:flex; flex-wrap:wrap; gap: var(--ac-space-6); margin-top: var(--ac-space-4);">
    <div style="flex: 1 1 12rem;">
      <strong>1. Choose</strong>
      <p class="card__meta">Book a single vendor, or add several categories to one bundle.</p>
    </div>
    <div style="flex: 1 1 12rem;">
      <strong>2. Confirm</strong>
      <p class="card__meta">Pay one combined deposit; every vendor's date is locked in together.</p>
    </div>
    <div style="flex: 1 1 12rem;">
      <strong>3. Track</strong>
      <p class="card__meta">Follow every vendor's status right up to your event date.</p>
    </div>
  </div>
</section>

<section style="padding-block: var(--ac-space-8);">
  <h2>Recently listed vendors</h2>
  <?php if ($dbError): ?>
    <p class="card__meta"><?= View::e($dbError) ?></p>
  <?php elseif (empty($vendors)): ?>
    <p class="card__meta">No vendors are onboarded yet in this environment — see src/Controllers/VendorController.php to add one, or seed the <code>vendor_listings</code> table directly for a demo.</p>
  <?php else: ?>
    <div style="display:flex; flex-wrap:wrap; gap: var(--ac-space-4); margin-top: var(--ac-space-4);">
      <?php foreach ($vendors as $vendor): ?>
        <div class="card" style="width: 16rem;">
          <h3><?= View::e($vendor['business_name']) ?></h3>
          <div class="card__meta">
            <?= View::e(ucfirst($vendor['category'])) ?> · KES <?= number_format((float) $vendor['base_price']) ?> <?= View::e(str_replace('_', ' ', $vendor['pricing_model'])) ?>
          </div>
          <a href="/vendors/<?= (int) $vendor['id'] ?>" class="btn btn--secondary" style="margin-top: var(--ac-space-3);">View vendor</a>
        </div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>
</section>
