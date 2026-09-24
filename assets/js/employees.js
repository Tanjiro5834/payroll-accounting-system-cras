(function () {
  "use strict";

  /* ---------- Drawer ---------- */
  const sidebar = document.getElementById("sidebar");
  const overlay = document.getElementById("drawer-overlay");
  const hamburgerBtn = document.getElementById("hamburger-btn");
  const iconMenu = document.getElementById("icon-menu");
  const iconClose = document.getElementById("icon-close");

  function openDrawer() {
    sidebar.classList.remove("-translate-x-full");
    overlay.classList.remove("opacity-0", "pointer-events-none");
    iconMenu.classList.add("hidden");
    iconClose.classList.remove("hidden");
    hamburgerBtn.setAttribute("aria-expanded", "true");
  }
  function closeDrawer() {
    sidebar.classList.add("-translate-x-full");
    overlay.classList.add("opacity-0", "pointer-events-none");
    iconMenu.classList.remove("hidden");
    iconClose.classList.add("hidden");
    hamburgerBtn.setAttribute("aria-expanded", "false");
  }
  hamburgerBtn.addEventListener("click", function () {
    if (hamburgerBtn.getAttribute("aria-expanded") === "true") closeDrawer();
    else openDrawer();
  });
  overlay.addEventListener("click", closeDrawer);
  document.addEventListener("keydown", function (e) {
    if (
      e.key === "Escape" &&
      hamburgerBtn.getAttribute("aria-expanded") === "true"
    ) {
      closeDrawer();
      hamburgerBtn.focus();
    }
  });
  sidebar.querySelectorAll("a").forEach(function (link) {
    link.addEventListener("click", function () {
      if (window.innerWidth < 1024) closeDrawer();
    });
  });

  /* ---------- Helpers ---------- */
  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = String(str);
    return div.innerHTML;
  }
  function getInitials(name) {
    return name
      .split(" ")
      .filter(Boolean)
      .slice(0, 2)
      .map(function (w) {
        return w[0];
      })
      .join("")
      .toUpperCase();
  }
  function formatRate(emp) {
    if (emp.freq === "Monthly")
      return (
        "\u20B1" +
        emp.monthly.toLocaleString("en-PH", {
          minimumFractionDigits: 2,
          maximumFractionDigits: 2,
        })
      );
    return "\u20B1" + emp.hourly.toFixed(2) + "/hr";
  }

  /* ---------- Data (mock — replaced by PHP) ---------- */
  const employees = [
    {
      id: 1,
      name: "Juan Dela Cruz",
      role: "Technician",
      contact: "0917 555 0101",
      freq: "Weekly",
      hourly: 120,
      monthly: 0,
      status: "Active",
    },
    {
      id: 2,
      name: "Maria Santos",
      role: "Admin",
      contact: "0917 555 0102",
      freq: "Monthly",
      hourly: 0,
      monthly: 32000,
      status: "Active",
    },
    {
      id: 3,
      name: "Pedro Reyes",
      role: "Technician",
      contact: "0917 555 0103",
      freq: "Kinsenas",
      hourly: 130,
      monthly: 0,
      status: "Active",
    },
    {
      id: 4,
      name: "Ana Villanueva",
      role: "Secretary",
      contact: "0917 555 0104",
      freq: "Monthly",
      hourly: 0,
      monthly: 25000,
      status: "Active",
    },
    {
      id: 5,
      name: "Carlos Mendoza",
      role: "Driver",
      contact: "0917 555 0105",
      freq: "Weekly",
      hourly: 100,
      monthly: 0,
      status: "Active",
    },
    {
      id: 6,
      name: "Rosa Bautista",
      role: "Helper",
      contact: "0917 555 0106",
      freq: "Weekly",
      hourly: 90,
      monthly: 0,
      status: "Active",
    },
    {
      id: 7,
      name: "Miguel Torres",
      role: "Construction Worker",
      contact: "0917 555 0107",
      freq: "Kinsenas",
      hourly: 110,
      monthly: 0,
      status: "Active",
    },
    {
      id: 8,
      name: "Elena Garcia",
      role: "Developer",
      contact: "0917 555 0108",
      freq: "Monthly",
      hourly: 0,
      monthly: 55000,
      status: "Active",
    },
    {
      id: 9,
      name: "Ramon Flores",
      role: "Technician",
      contact: "0917 555 0109",
      freq: "Weekly",
      hourly: 120,
      monthly: 0,
      status: "Inactive",
    },
    {
      id: 10,
      name: "Luz Ramos",
      role: "Admin",
      contact: "0917 555 0110",
      freq: "Monthly",
      hourly: 0,
      monthly: 30000,
      status: "Active",
    },
    {
      id: 11,
      name: "Jose Aquino",
      role: "Technician",
      contact: "0917 555 0111",
      freq: "Weekly",
      hourly: 125,
      monthly: 0,
      status: "Active",
    },
    {
      id: 12,
      name: "Carmen Lim",
      role: "Secretary",
      contact: "0917 555 0112",
      freq: "Monthly",
      hourly: 0,
      monthly: 26000,
      status: "Inactive",
    },
  ];

  let filtered = employees.slice();
  let sortKey = null;
  let sortDir = "asc";

  /* ---------- Render ---------- */
  const tbody = document.getElementById("employees-tbody");
  const cardsWrap = document.getElementById("employees-cards");
  const emptyState = document.getElementById("employees-empty");

  function statusBadge(status) {
    const active = status === "Active";
    return (
      '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold font-mono uppercase tracking-wider ' +
      (active
        ? "bg-emerald-100 text-emerald-800"
        : "bg-slate-200 text-slate-600") +
      '">' +
      '<span class="w-1.5 h-1.5 rounded-full ' +
      (active ? "bg-emerald-600" : "bg-slate-400") +
      '"></span>' +
      escapeHtml(status) +
      "</span>"
    );
  }

  function avatarHtml(emp, size) {
    const cls = size === "lg" ? "w-10 h-10" : "w-9 h-9";
    return (
      '<div class="' +
      cls +
      ' rounded-full bg-emerald-700 flex items-center justify-center flex-shrink-0">' +
      '<span class="text-white font-bold text-xs">' +
      getInitials(emp.name) +
      "</span></div>"
    );
  }

  function renderTable() {
    tbody.innerHTML = "";
    if (filtered.length === 0) {
      emptyState.classList.remove("hidden");
      return;
    }
    emptyState.classList.add("hidden");

    filtered.forEach(function (emp) {
      const tr = document.createElement("tr");
      tr.className = "hover:bg-slate-50 transition-colors";
      tr.innerHTML =
        '<td class="px-4 py-3">' +
        '<div class="flex items-center gap-3">' +
        avatarHtml(emp, "lg") +
        '<div class="min-w-0">' +
        '<p class="font-semibold text-slate-900 truncate">' +
        escapeHtml(emp.name) +
        "</p>" +
        '<p class="font-mono text-[11px] text-slate-400">EMP-' +
        String(emp.id).padStart(4, "0") +
        "</p>" +
        "</div>" +
        "</div>" +
        "</td>" +
        '<td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded-full bg-slate-100 text-slate-700 text-[11px] font-semibold uppercase tracking-wider">' +
        escapeHtml(emp.role) +
        "</span></td>" +
        '<td class="px-4 py-3 font-mono text-xs text-slate-600 whitespace-nowrap">' +
        escapeHtml(emp.contact) +
        "</td>" +
        '<td class="px-4 py-3 text-xs text-slate-600">' +
        escapeHtml(emp.freq) +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-xs text-slate-900 whitespace-nowrap">' +
        formatRate(emp) +
        "</td>" +
        '<td class="px-4 py-3">' +
        statusBadge(emp.status) +
        "</td>" +
        '<td class="px-4 py-3 text-right">' +
        '<a href="index.php?page=employee-form&id=' +
        emp.id +
        '" class="inline-flex items-center gap-1 px-3 py-1.5 rounded-md text-xs font-semibold text-emerald-700 hover:bg-emerald-50 focus:outline-none focus:ring-2 focus:ring-emerald-500 transition-colors min-h-[36px]">' +
        '<svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L10.582 16.07a4.5 4.5 0 0 1-1.897 1.13L6 18l.8-2.685a4.5 4.5 0 0 1 1.13-1.897l8.932-8.931Zm0 0L19.5 7.125M18 14v4.75A2.25 2.25 0 0 1 15.75 21H5.25A2.25 2.25 0 0 1 3 18.75V8.25A2.25 2.25 0 0 1 5.25 6H10" /></svg>' +
        "Edit" +
        "</a>" +
        "</td>";
      tbody.appendChild(tr);
    });
  }

  function renderCards() {
    cardsWrap.innerHTML = "";
    if (filtered.length === 0) {
      cardsWrap.innerHTML =
        '<div class="bg-white rounded-lg border border-slate-200 shadow-sm py-16 text-center"><p class="text-sm font-medium text-slate-500">No employees match your filters.</p></div>';
      return;
    }

    filtered.forEach(function (emp) {
      const card = document.createElement("a");
      card.href = "index.php?page=employee-form&id=" + emp.id;
      card.className =
        "block bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:border-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all p-4";
      card.innerHTML =
        '<div class="flex items-start gap-3">' +
        avatarHtml(emp, "lg") +
        '<div class="min-w-0 flex-1">' +
        '<div class="flex items-start justify-between gap-2">' +
        '<p class="font-semibold text-slate-900 truncate">' +
        escapeHtml(emp.name) +
        "</p>" +
        statusBadge(emp.status) +
        "</div>" +
        '<p class="text-xs text-slate-500 mt-0.5">' +
        escapeHtml(emp.role) +
        "</p>" +
        '<div class="flex items-center justify-between mt-2 gap-2">' +
        '<p class="font-mono text-[11px] text-slate-400 truncate">' +
        escapeHtml(emp.contact) +
        "</p>" +
        '<p class="font-mono text-xs font-semibold text-slate-900 whitespace-nowrap">' +
        formatRate(emp) +
        "</p>" +
        "</div>" +
        '<p class="font-mono text-[10px] text-slate-400 mt-1">EMP-' +
        String(emp.id).padStart(4, "0") +
        " · " +
        escapeHtml(emp.freq) +
        "</p>" +
        "</div>" +
        '<svg class="w-4 h-4 text-slate-300 flex-shrink-0 mt-1" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>' +
        "</div>";
      cardsWrap.appendChild(card);
    });
  }

  /* ---------- Filter & Sort ---------- */
  const searchInput = document.getElementById("search-input");
  const roleFilter = document.getElementById("role-filter");

  function applyFilters() {
    const q = searchInput.value.trim().toLowerCase();
    const role = roleFilter.value;

    filtered = employees.filter(function (emp) {
      const matchesQuery = !q || emp.name.toLowerCase().indexOf(q) !== -1;
      const matchesRole = !role || emp.role === role;
      return matchesQuery && matchesRole;
    });

    if (sortKey) {
      filtered.sort(function (a, b) {
        let va, vb;
        if (sortKey === "name") {
          va = a.name.toLowerCase();
          vb = b.name.toLowerCase();
        } else if (sortKey === "role") {
          va = a.role.toLowerCase();
          vb = b.role.toLowerCase();
        } else if (sortKey === "rate") {
          va = a.monthly || a.hourly;
          vb = b.monthly || b.hourly;
        } else {
          va = a[sortKey];
          vb = b[sortKey];
        }
        if (va < vb) return sortDir === "asc" ? -1 : 1;
        if (va > vb) return sortDir === "asc" ? 1 : -1;
        return 0;
      });
    }

    renderTable();
    renderCards();
  }

  searchInput.addEventListener("input", applyFilters);
  roleFilter.addEventListener("change", applyFilters);

  document.querySelectorAll("th[data-sort]").forEach(function (th) {
    th.addEventListener("click", function () {
      const key = th.getAttribute("data-sort");
      if (sortKey === key) {
        sortDir = sortDir === "asc" ? "desc" : "asc";
      } else {
        sortKey = key;
        sortDir = "asc";
      }
      applyFilters();
    });
  });

  /* ---------- Init ---------- */
  applyFilters();
})();
