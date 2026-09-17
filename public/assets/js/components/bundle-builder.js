/**
 * Bundle Builder — this platform's distinctive UI need beyond the shared
 * component set: lets a customer assemble multiple vendor categories into
 * one event bundle (see planning/05-event-co-ke/prd.md Core Feature 2 and
 * user-flows.md's bundle path). Purely a selection UI; the atomic
 * multi-vendor availability lock happens server-side on confirm (see
 * src/Controllers/BundleController.php::confirm, currently a TODO).
 *
 * @param {{ categories: string[], onSelectVendor: (category: string) => void }} props
 * @returns {HTMLElement}
 */
export function createBundleBuilder({ categories, onSelectVendor }) {
  const wrapper = document.createElement("div");
  wrapper.className = "bundle-builder";

  categories.forEach((category) => {
    const slot = document.createElement("div");
    slot.className = "bundle-builder__slot";
    slot.dataset.filled = "false";
    slot.dataset.category = category;

    const label = document.createElement("span");
    label.textContent = category;

    const button = document.createElement("button");
    button.type = "button";
    button.className = "btn btn--secondary";
    button.textContent = "Choose vendor";
    button.addEventListener("click", () => onSelectVendor(category));

    slot.append(label, button);
    wrapper.appendChild(slot);
  });

  return wrapper;
}

/**
 * Marks a bundle-builder slot as filled once a vendor has been chosen for
 * that category, updating its visual state.
 * @param {HTMLElement} wrapper
 * @param {string} category
 * @param {string} vendorName
 */
export function markSlotFilled(wrapper, category, vendorName) {
  const slot = wrapper.querySelector(`[data-category="${category}"]`);
  if (!slot) return;
  slot.dataset.filled = "true";
  slot.querySelector("span").textContent = `${category}: ${vendorName}`;
}
