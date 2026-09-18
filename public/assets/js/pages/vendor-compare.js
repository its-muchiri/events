/*
 * Side-by-side vendor comparison for /vendors (prd.md Core Feature 1: "compare
 * pricing/ratings/portfolio"). Progressive enhancement over the server-rendered
 * cards: each card's "Compare" checkbox carries the vendor's facts as data-*
 * attributes. Everything is written with textContent, never innerHTML, since
 * vendor names and areas are vendor-supplied.
 */

const MAX_COMPARE = 3;

const checkboxes = [...document.querySelectorAll(".js-compare")];
const bar = document.getElementById("compare-bar");
const count = document.getElementById("compare-count");
const panel = document.getElementById("compare-panel");

const ROWS = [
  ["Category", "category"],
  ["Price", "price"],
  ["Rating", "rating"],
  ["Events completed", "events"],
  ["Service area", "area"],
  ["Verified", "verified"],
];

function selected() {
  return checkboxes.filter((box) => box.checked);
}

function cell(tag, text) {
  const el = document.createElement(tag);
  el.textContent = text;
  return el;
}

function renderTable(boxes) {
  const table = document.createElement("table");
  table.className = "compare-table";

  const head = document.createElement("tr");
  head.appendChild(cell("th", ""));
  for (const box of boxes) {
    const th = document.createElement("th");
    const img = document.createElement("img");
    img.src = box.dataset.photo;
    img.alt = "";
    const link = document.createElement("a");
    link.href = `/vendors/${encodeURIComponent(box.dataset.id)}`;
    link.textContent = box.dataset.name;
    th.append(img, document.createElement("br"), link);
    head.appendChild(th);
  }
  table.appendChild(head);

  for (const [label, key] of ROWS) {
    const row = document.createElement("tr");
    row.appendChild(cell("th", label));
    for (const box of boxes) {
      row.appendChild(cell("td", box.dataset[key]));
    }
    table.appendChild(row);
  }
  return table;
}

function update() {
  const boxes = selected();
  const atLimit = boxes.length >= MAX_COMPARE;
  for (const box of checkboxes) {
    box.disabled = atLimit && !box.checked;
  }

  bar.hidden = boxes.length === 0;
  count.textContent = `${boxes.length} of ${MAX_COMPARE} selected`;
  document.getElementById("compare-show").disabled = boxes.length < 2;

  // Keep an open comparison in step with the selection; close it if fewer than two remain.
  if (!panel.hidden) {
    if (boxes.length < 2) {
      panel.hidden = true;
      panel.replaceChildren();
    } else {
      panel.replaceChildren(renderTable(boxes));
    }
  }
}

for (const box of checkboxes) {
  box.addEventListener("change", update);
}

document.getElementById("compare-show").addEventListener("click", () => {
  const boxes = selected();
  if (boxes.length < 2) return;
  panel.replaceChildren(renderTable(boxes));
  panel.hidden = false;
  panel.scrollIntoView({ behavior: window.matchMedia("(prefers-reduced-motion: reduce)").matches ? "auto" : "smooth" });
});

document.getElementById("compare-clear").addEventListener("click", () => {
  for (const box of checkboxes) box.checked = false;
  panel.hidden = true;
  panel.replaceChildren();
  update();
});

update();
