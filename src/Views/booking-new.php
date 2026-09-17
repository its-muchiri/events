<?php
/** @var string $prefillVendorId */
/** @var string $prefillCategory */
/** @var string $prefillBundleId */
use EventCo\Core\View;
?>
<h1>Book a vendor</h1>

<form id="booking-form" style="max-width: 32rem; display:flex; flex-direction:column; gap: var(--ac-space-4);">
  <input type="hidden" name="vendor_id" value="<?= View::e($prefillVendorId) ?>">
  <input type="hidden" name="bundle_id" value="<?= View::e($prefillBundleId) ?>">

  <label>
    Category
    <input type="text" name="category" value="<?= View::e($prefillCategory) ?>" required style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
  </label>

  <label>
    Event date
    <input type="date" name="event_date" required style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
  </label>

  <label>
    Pricing model
    <select name="pricing_model" style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
      <option value="flat_fee">Flat fee</option>
      <option value="per_head">Per head</option>
      <option value="per_hour">Per hour</option>
    </select>
  </label>

  <label>
    Head count (if per-head)
    <input type="number" name="head_count" min="0" style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
  </label>

  <label>
    Hours booked (if per-hour)
    <input type="number" name="hours_booked" min="0" step="0.5" style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
  </label>

  <button type="submit" class="btn btn--primary">Request booking</button>
</form>

<p id="booking-result" class="card__meta" style="margin-top: var(--ac-space-4);"></p>

<script type="module">
  document.getElementById("booking-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);
    const resultEl = document.getElementById("booking-result");

    try {
      const res = await fetch("/api/v1/bookings", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          vendor_id: formData.get("vendor_id") || null,
          bundle_id: formData.get("bundle_id") || null,
          category: formData.get("category"),
          event_date: formData.get("event_date"),
          pricing_model: formData.get("pricing_model"),
          head_count: formData.get("head_count") || null,
          hours_booked: formData.get("hours_booked") || null,
        }),
      });
      const data = await res.json();

      if (!res.ok) {
        // Expected right now: no auth middleware exists yet, so customer_id
        // resolves to null and the database rejects the insert — the real,
        // current state of the app. See src/Controllers/BookingController.php.
        resultEl.textContent = "Booking failed: " + (data.error || "unknown error") + " — expected until auth middleware and a live database are wired up.";
        return;
      }

      resultEl.innerHTML = `Booking #${data.id} requested (status: ${data.status}). <a href="/bookings/${data.id}">Track it</a>`;
    } catch (e) {
      resultEl.textContent = "Network error: " + e.message;
    }
  });
</script>
