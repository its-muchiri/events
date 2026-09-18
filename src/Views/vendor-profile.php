<?php
/** @var array|null $vendor */
/** @var list<array> $reviews */
/** @var bool $isSaved */
/** @var int $vendorId */
/** @var string|null $bundleId */
/** @var string|null $dbError */
/** @var array|null $currentUser */
use EventCo\Core\View;
?>
<?php if ($dbError): ?>
  <p class="card__meta"><?= View::e($dbError) ?></p>
<?php elseif (!$vendor): ?>
  <h1>Vendor #<?= $vendorId ?></h1>
  <p class="card__meta">No vendor found with this ID.</p>
  <p><a href="/vendors" class="btn btn--secondary">Browse all vendors</a></p>
<?php else: ?>
  <p class="card__meta"><a href="/vendors?category=<?= urlencode($vendor['category']) ?>">← <?= View::e($vendor['category_label']) ?></a></p>
  <h1><?= View::e($vendor['business_name']) ?></h1>
  <p class="card__meta"><?= View::e($vendor['category_label']) ?> · <?= View::e($vendor['service_area']) ?></p>
  <div style="display:flex; flex-wrap:wrap; align-items:center; gap: var(--ac-space-3); margin: var(--ac-space-2) 0 var(--ac-space-4);">
    <?= View::ratingHtml($vendor['average_rating'], $vendor['review_count']) ?>
    <?= $vendor['is_verified'] ? '<span class="status-badge">Verified</span>' : '' ?>
    <span id="standing-badge"></span>
    <span class="card__meta"><?= (int) $vendor['completed_events_count'] ?> events completed</span>
  </div>

  <div class="gallery" aria-label="Portfolio">
    <?php foreach ($vendor['photos'] as $i => $photo): ?>
      <img class="photo" src="<?= View::e($photo) ?>" alt="<?= View::e($vendor['business_name'] . ' — ' . $vendor['category_label'] . ' photo ' . ($i + 1)) ?>"
        <?= $i === 0 ? 'fetchpriority="high"' : 'loading="lazy"' ?> width="800" height="600">
    <?php endforeach; ?>
  </div>
  <?php if (!$vendor['has_own_portfolio']): ?>
    <p class="card__meta">Sample <?= View::e(strtolower($vendor['category_label'])) ?> photos — this vendor hasn't uploaded their own portfolio yet.</p>
  <?php endif; ?>

  <p style="font-size:1.25rem; font-weight:700; margin-top: var(--ac-space-4);">
    <?= View::e(View::priceLabel($vendor['base_price'], $vendor['pricing_model'])) ?>
  </p>

  <section aria-labelledby="availability-heading" style="margin-block: var(--ac-space-6);">
    <h2 id="availability-heading" style="font-size:1.1rem;">Check your date</h2>
    <form id="availability-form" style="display:flex; flex-wrap:wrap; gap: var(--ac-space-2); align-items:end; margin-top: var(--ac-space-2);">
      <label style="display:flex; flex-direction:column; gap: var(--ac-space-1); font-size:0.8125rem; font-weight:600;">
        Event date
        <input type="date" id="availability-date" min="<?= date('Y-m-d') ?>" required
          style="padding: var(--ac-space-2) var(--ac-space-3); border:1px solid var(--ac-ink); border-radius: var(--ac-radius-control); background: var(--ac-paper); color: var(--ac-ink); font: inherit; min-height:2.75rem;">
      </label>
      <button type="submit" class="btn btn--secondary">Check availability</button>
      <button type="button" id="save-vendor" class="btn btn--secondary" data-saved="<?= $isSaved ? '1' : '0' ?>"><?= $isSaved ? 'Saved ✓' : 'Save vendor' ?></button>
    </form>
    <p id="availability-result" class="card__meta" role="status" style="margin-top: var(--ac-space-2);"></p>
  </section>

  <section aria-labelledby="reviews-heading" style="margin-block: var(--ac-space-6);">
    <h2 id="reviews-heading" style="font-size:1.1rem;">Reviews</h2>
    <?php if (empty($reviews)): ?>
      <p class="card__meta">No reviews yet — this vendor's first completed event will earn the first one.</p>
    <?php else: foreach ($reviews as $review): ?>
      <div class="review">
        <?= View::ratingHtml((float) $review['rating'], 0) ?>
        <strong><?= View::e($review['reviewer']) ?></strong>
        <span class="card__meta" style="display:inline;"><?= View::e(date('M Y', strtotime($review['created_at']))) ?></span>
        <?php if (!empty($review['comment'])): ?><p><?= View::e($review['comment']) ?></p><?php endif; ?>
      </div>
    <?php endforeach; endif; ?>
  </section>

  <div id="sticky-cta-container"></div>

  <script type="module">
    import { createStatusBadge } from "/assets/js/components/status-badge.js";
    import { createStickyCta } from "/assets/js/components/sticky-cta.js";

    const vendorId = <?= (int) $vendorId ?>;
    const category = <?= json_encode($vendor['category']) ?>;
    const pricingModel = <?= json_encode($vendor['pricing_model']) ?>;
    const bundleId = <?= json_encode($bundleId ? (string) $bundleId : null) ?>;

    const standing = <?= json_encode($vendor['standing_status'] ?? null) ?>;
    if (standing) {
      const tone = { good_standing: "success", warning: "warning", suspended: "danger" }[standing] ?? "neutral";
      document.getElementById("standing-badge").appendChild(
        createStatusBadge({ label: standing.replace(/_/g, " "), tone })
      );
    }

    const dateInput = document.getElementById("availability-date");
    const result = document.getElementById("availability-result");

    function bookingUrl() {
      const params = new URLSearchParams({ vendor_id: vendorId, category, pricing_model: pricingModel });
      if (bundleId) params.set("bundle_id", bundleId);
      if (dateInput.value) params.set("event_date", dateInput.value);
      return "/bookings/new?" + params;
    }

    document.getElementById("availability-form").addEventListener("submit", async (event) => {
      event.preventDefault();
      result.textContent = "Checking…";
      try {
        const res = await fetch(`/api/v1/vendors/${vendorId}/availability?date=${encodeURIComponent(dateInput.value)}`);
        const data = await res.json();
        if (!res.ok) {
          result.textContent = data.error || "Couldn't check that date — please try again.";
          return;
        }
        result.textContent = data.available
          ? "Good news — this vendor is free on that date."
          : `Not available on that date. ${data.reason ?? ""}`;
      } catch (e) {
        result.textContent = "Couldn't check that date — please check your connection and try again.";
      }
    });

    const saveButton = document.getElementById("save-vendor");
    saveButton.addEventListener("click", async () => {
      try {
        const res = await fetch(`/api/v1/vendors/${vendorId}/save`, { method: "POST" });
        if (res.status === 401) {
          window.location.href = "/login";
          return;
        }
        if (!res.ok) throw new Error(String(res.status));
        saveButton.textContent = "Saved ✓";
      } catch (e) {
        result.textContent = "Couldn't save this vendor — please try again.";
      }
    });

    document.getElementById("sticky-cta-container").appendChild(
      createStickyCta({
        label: bundleId ? `Add to bundle #${bundleId}` : "Book this vendor",
        onClick: () => { window.location.href = bookingUrl(); },
      })
    );
  </script>
<?php endif; ?>
