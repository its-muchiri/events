<h1>Build an event bundle</h1>

<p class="card__meta">Start your bundle with the event details, then add a vendor per category (photographer, caterer, venue, and more) — one combined deposit covers the whole bundle.</p>

<form id="bundle-form" style="max-width: 32rem; display:flex; flex-direction:column; gap: var(--ac-space-4); margin-top: var(--ac-space-4);">
  <label>
    Event type
    <select name="event_type" style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
      <option value="wedding">Wedding</option>
      <option value="corporate">Corporate</option>
      <option value="birthday">Birthday</option>
      <option value="memorial">Memorial</option>
      <option value="other">Other</option>
    </select>
  </label>

  <label>
    Event date
    <input type="date" name="event_date" required style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
  </label>

  <label>
    Event location
    <input type="text" name="event_location_address" required style="display:block; width:100%; padding: var(--ac-space-2); margin-top: var(--ac-space-1);">
  </label>

  <button type="submit" class="btn btn--primary">Start bundle</button>
</form>

<p id="bundle-result" class="card__meta" style="margin-top: var(--ac-space-4);"></p>

<script type="module">
  document.getElementById("bundle-form").addEventListener("submit", async (event) => {
    event.preventDefault();
    const formData = new FormData(event.target);
    const resultEl = document.getElementById("bundle-result");

    try {
      const res = await fetch("/api/v1/event-bundles", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          event_type: formData.get("event_type"),
          event_date: formData.get("event_date"),
          event_location_address: formData.get("event_location_address"),
          event_location_lat: 0,
          event_location_lng: 0,
        }),
      });
      const data = await res.json();

      if (!res.ok) {
        // Expected right now: no auth middleware exists yet, so customer_id
        // resolves to null and the database rejects the insert. See
        // src/Controllers/BundleController.php.
        resultEl.textContent = "Bundle creation failed: " + (data.error || "unknown error") + " — expected until auth middleware and a live database are wired up.";
        return;
      }

      window.location.href = `/bundles/${data.id}`;
    } catch (e) {
      resultEl.textContent = "Network error: " + e.message;
    }
  });
</script>
