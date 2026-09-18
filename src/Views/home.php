<?php
/** @var array $vendors */
/** @var string|null $dbError */
use EventCo\Core\Photos;
use EventCo\Core\View;
?>
<section class="hero" style="background-image: url('<?= View::e(Photos::hero()) ?>');">
  <h1>Book event vendors — one at a time, or all at once.</h1>
  <p>
    Photographers, caterers, venues, and more — book a single vendor directly and pay your deposit with M-Pesa, or assemble a full event bundle with one combined deposit.
  </p>
  <div style="display:flex; flex-wrap:wrap; gap: var(--ac-space-3);">
    <a href="/vendors" class="btn btn--primary">Browse vendors</a>
    <a href="/bundles/new" class="btn btn--secondary">Build a bundle</a>
  </div>
</section>

<section style="padding-block: var(--ac-space-8) 0;">
  <h2>Find the right vendor</h2>
  <div class="grid" style="margin-top: var(--ac-space-4);">
    <?php foreach (Photos::CATEGORIES as $slug => $label): ?>
      <a class="category-tile" href="/vendors?category=<?= urlencode($slug) ?>">
        <img src="<?= View::e(Photos::categoryCover($slug)) ?>" alt="" loading="lazy" width="640" height="427">
        <span><?= View::e($label) ?></span>
      </a>
    <?php endforeach; ?>
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
    <p class="card__meta">No vendors are listed yet — check back soon.</p>
  <?php else: ?>
    <div class="grid" style="margin-top: var(--ac-space-4);">
      <?php foreach ($vendors as $vendor): include __DIR__ . '/partials/vendor-card.php'; endforeach; ?>
    </div>
  <?php endif; ?>
</section>
