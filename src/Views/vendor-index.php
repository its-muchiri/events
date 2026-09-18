<?php
/** @var array $vendors */
/** @var array $filters */
/** @var int $page */
/** @var bool $hasMore */
/** @var string|null $bundleId */
/** @var string|null $dbError */
use EventCo\Core\Photos;
use EventCo\Core\View;

// Keep every active filter when switching category or page.
$keep = static function (array $override) use ($filters, $bundleId): string {
    $params = array_merge($filters, ['bundle_id' => $bundleId], $override, ['page' => $override['page'] ?? null]);
    if (($params['sort'] ?? '') === 'rating') {
        unset($params['sort']); // the default
    }
    if (($params['page'] ?? 1) <= 1) {
        unset($params['page']);
    }
    $params = array_filter($params, static fn ($v) => $v !== null && $v !== '');

    return '/vendors' . ($params ? '?' . http_build_query($params) : '');
};
$hasFilters = (bool) array_filter(
    array_diff_key($filters, ['page' => 1, 'sort' => 1]),
    static fn ($v) => $v !== null && $v !== ''
);
?>
<h1>Browse vendors</h1>
<?php if ($bundleId): ?>
  <p class="card__meta">Adding a vendor to bundle #<?= View::e($bundleId) ?>.</p>
<?php endif; ?>

<nav class="pill-row" aria-label="Vendor categories" style="margin-top: var(--ac-space-4);">
  <a href="<?= View::e($keep(['category' => null])) ?>" class="btn <?= $filters['category'] === '' ? 'btn--primary' : 'btn--secondary' ?>">All</a>
  <?php foreach (Photos::CATEGORIES as $slug => $label): ?>
    <a href="<?= View::e($keep(['category' => $slug])) ?>" class="btn <?= $filters['category'] === $slug ? 'btn--primary' : 'btn--secondary' ?>"><?= View::e($label) ?></a>
  <?php endforeach; ?>
</nav>

<form class="filters" method="get" action="/vendors" role="search">
  <?php if ($filters['category'] !== ''): ?><input type="hidden" name="category" value="<?= View::e($filters['category']) ?>"><?php endif; ?>
  <?php if ($bundleId): ?><input type="hidden" name="bundle_id" value="<?= View::e($bundleId) ?>"><?php endif; ?>
  <label>Search
    <input type="search" name="q" value="<?= View::e($filters['q']) ?>" placeholder="Name, area or category">
  </label>
  <label>Area
    <input type="text" name="area" value="<?= View::e($filters['area']) ?>" placeholder="e.g. Nairobi">
  </label>
  <label>Event date
    <input type="date" name="date" value="<?= View::e($filters['date']) ?>" min="<?= date('Y-m-d') ?>">
  </label>
  <label>Pricing
    <select name="pricing_model">
      <option value="">Any</option>
      <?php foreach (['flat_fee' => 'Flat fee', 'per_head' => 'Per head', 'per_hour' => 'Per hour'] as $value => $label): ?>
        <option value="<?= $value ?>" <?= $filters['pricing_model'] === $value ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Max price (KES)
    <input type="number" name="max_price" min="0" step="100" inputmode="numeric" value="<?= $filters['max_price'] !== null ? View::e((string) (int) $filters['max_price']) : '' ?>">
  </label>
  <label>Min rating
    <select name="min_rating">
      <option value="">Any</option>
      <?php foreach (['3' => '3+ stars', '4' => '4+ stars', '4.5' => '4.5+ stars'] as $value => $label): ?>
        <option value="<?= $value ?>" <?= $filters['min_rating'] !== null && (float) $filters['min_rating'] === (float) $value ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <label>Sort by
    <select name="sort">
      <?php foreach (['rating' => 'Top rated', 'popular' => 'Most events', 'price_asc' => 'Price: low to high', 'price_desc' => 'Price: high to low', 'newest' => 'Newest'] as $value => $label): ?>
        <option value="<?= $value ?>" <?= $filters['sort'] === $value ? 'selected' : '' ?>><?= $label ?></option>
      <?php endforeach; ?>
    </select>
  </label>
  <button type="submit" class="btn btn--primary">Search</button>
  <?php if ($hasFilters || $filters['sort'] !== 'rating'): ?>
    <a href="<?= View::e('/vendors' . ($bundleId ? '?bundle_id=' . urlencode($bundleId) : '')) ?>" class="btn btn--secondary">Clear</a>
  <?php endif; ?>
</form>

<?php if ($filters['date'] !== ''): ?>
  <p class="card__meta">Showing vendors free on <?= View::e(date('j F Y', strtotime($filters['date']))) ?>.</p>
<?php endif; ?>

<?php if ($dbError): ?>
  <p class="card__meta"><?= View::e($dbError) ?></p>
<?php elseif (empty($vendors)): ?>
  <p>No vendors match these filters<?= $hasFilters ? '. Try widening your search or clearing the filters.' : ' yet.' ?></p>
<?php else: ?>
  <p class="card__meta" style="margin-bottom: var(--ac-space-3);">Tick “Compare” on up to three vendors to see them side by side.</p>
  <div class="grid">
    <?php foreach ($vendors as $vendor): $showCompare = true; include __DIR__ . '/partials/vendor-card.php'; endforeach; ?>
  </div>

  <?php if ($page > 1 || $hasMore): ?>
    <nav class="pill-row" aria-label="Pagination" style="margin-top: var(--ac-space-6); justify-content:center;">
      <?php if ($page > 1): ?><a class="btn btn--secondary" href="<?= View::e($keep(['page' => $page - 1])) ?>">Previous</a><?php endif; ?>
      <span class="card__meta" style="align-self:center;">Page <?= (int) $page ?></span>
      <?php if ($hasMore): ?><a class="btn btn--secondary" href="<?= View::e($keep(['page' => $page + 1])) ?>">Next</a><?php endif; ?>
    </nav>
  <?php endif; ?>

  <section id="compare-panel" class="compare-panel" aria-live="polite" hidden></section>
  <div id="compare-bar" class="compare-bar" hidden>
    <span id="compare-count"></span>
    <span style="display:flex; gap: var(--ac-space-2);">
      <button type="button" id="compare-clear" class="btn btn--secondary" style="color:#fff;border-color:#fff;">Clear</button>
      <button type="button" id="compare-show" class="btn btn--primary">Compare</button>
    </span>
  </div>
  <script type="module" src="/assets/js/pages/vendor-compare.js"></script>
<?php endif; ?>
