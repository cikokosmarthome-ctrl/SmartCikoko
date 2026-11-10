(() => {
  "use strict";
  const controls = document.querySelectorAll("[data-filter]");
  const cards = document.querySelectorAll("#catalog .module");
  for (const button of controls) {
    button.addEventListener("click", () => {
      const group = button.dataset.filter;
      for (const control of controls) {
        const selected = control === button;
        control.classList.toggle("active", selected);
        control.setAttribute("aria-pressed", String(selected));
      }
      for (const card of cards) card.hidden = group !== "semua" && card.dataset.group !== group;
    });
  }
  for (const control of controls) control.setAttribute("aria-pressed", String(control.classList.contains("active")));
})();
