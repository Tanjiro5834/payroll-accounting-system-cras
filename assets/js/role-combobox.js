// Role field: pick from a list (common roles + roles already in use) or type a new one.
(function () {
  "use strict";

  const DEFAULT_ROLES = [
    "Admin", "Secretary", "Senior Technician", "Technician", "Driver",
    "Helper", "Construction Worker", "Warehouse Worker", "Web Developer",
  ];

  const input = document.getElementById("role");
  const toggle = document.getElementById("role-toggle");
  const menu = document.getElementById("role-menu");
  const combo = document.getElementById("role-combo");
  if (!input || !menu) return;

  let roles = DEFAULT_ROLES.slice();
  let active = -1;

  function esc(s) {
    const d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function addRoles(list) {
    const seen = new Set(roles.map(function (r) { return r.toLowerCase(); }));
    list.forEach(function (r) {
      const role = String(r || "").trim();
      if (role && !seen.has(role.toLowerCase())) {
        seen.add(role.toLowerCase());
        roles.push(role);
      }
    });
    roles.sort(function (a, b) { return a.localeCompare(b); });
  }

  function render(showAll) {
    const q = input.value.trim().toLowerCase();
    const matches = showAll || !q ? roles : roles.filter(function (r) { return r.toLowerCase().indexOf(q) !== -1; });
    const exact = roles.some(function (r) { return r.toLowerCase() === q; });

    let html = matches.map(function (r, i) {
      const selected = r.toLowerCase() === q;
      return '<li role="option" data-value="' + esc(r) + '" data-i="' + i + '" class="px-3 py-2 cursor-pointer flex items-center justify-between hover:bg-coolant-tint ' +
        (selected ? "font-semibold text-coolant" : "text-ink") + '">' + esc(r) +
        (selected ? '<svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>' : "") +
        "</li>";
    }).join("");

    if (q && !exact) {
      html += '<li role="option" data-value="' + esc(input.value.trim()) + '" class="px-3 py-2 cursor-pointer border-t border-line text-slate-600 hover:bg-coolant-tint">Use "<span class="font-semibold text-ink">' +
        esc(input.value.trim()) + '</span>" as a new role</li>';
    }

    menu.innerHTML = html || '<li class="px-3 py-2 text-slate-400">No roles yet. Type one.</li>';
    active = -1;
  }

  function open(showAll) {
    render(showAll);
    menu.classList.remove("hidden");
    input.setAttribute("aria-expanded", "true");
  }

  function close() {
    menu.classList.add("hidden");
    input.setAttribute("aria-expanded", "false");
  }

  function choose(value) {
    input.value = value;
    input.dispatchEvent(new Event("input", { bubbles: true }));
    close();
  }

  function highlight(items) {
    items.forEach(function (li, i) { li.classList.toggle("bg-coolant-tint", i === active); });
    if (items[active]) items[active].scrollIntoView({ block: "nearest" });
  }

  input.addEventListener("focus", function () { open(true); });
  input.addEventListener("input", function () {
    if (menu.classList.contains("hidden")) open(false); else render(false);
  });
  toggle.addEventListener("click", function () {
    if (menu.classList.contains("hidden")) { input.focus(); open(true); } else close();
  });

  menu.addEventListener("mousedown", function (e) {
    const li = e.target.closest("li[data-value]");
    if (!li) return;
    e.preventDefault();
    choose(li.dataset.value);
  });

  input.addEventListener("keydown", function (e) {
    const items = Array.from(menu.querySelectorAll("li[data-value]"));
    if (e.key === "ArrowDown") {
      e.preventDefault();
      if (menu.classList.contains("hidden")) open(true);
      active = Math.min(active + 1, items.length - 1);
      highlight(items);
    } else if (e.key === "ArrowUp") {
      e.preventDefault();
      active = Math.max(active - 1, 0);
      highlight(items);
    } else if (e.key === "Enter" && !menu.classList.contains("hidden") && items[active]) {
      e.preventDefault();
      choose(items[active].dataset.value);
    } else if (e.key === "Escape") {
      close();
    }
  });

  document.addEventListener("click", function (e) {
    if (!combo.contains(e.target)) close();
  });

  // Roles already used by employees show up in the list too.
  if (window.api) {
    api.get("employees", "index")
      .then(function (list) {
        addRoles((Array.isArray(list) ? list : []).map(function (e) { return e.role; }));
      })
      .catch(function () {});
  }
})();