(function () {
  "use strict";

  /* Drawer */
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
  sidebar.querySelectorAll("a").forEach(function (l) {
    l.addEventListener("click", function () {
      if (window.innerWidth < 1024) closeDrawer();
    });
  });

  /* Helpers */
  function escapeHtml(s) {
    const d = document.createElement("div");
    d.textContent = String(s);
    return d.innerHTML;
  }

  /* Mock data */
  const NAMES = [
    "Juan Dela Cruz",
    "Maria Santos",
    "Pedro Reyes",
    "Ana Villanueva",
    "Carlos Mendoza",
  ];
  const ACTIONS = ["PUNCH", "LOGIN", "LOGOUT", "EDIT", "PAYROLL_VIEW"];
  const DETAILS = {
    PUNCH: [
      "AM IN recorded via GPS",
      "AM OUT recorded",
      "PM IN recorded",
      "PM OUT recorded",
    ],
    LOGIN: ["Successful login from web"],
    LOGOUT: ["Session ended by user"],
    EDIT: ["Employee record updated", "Rate changed", "Role changed"],
    PAYROLL_VIEW: [
      "Viewed payroll for 2026-04-01..2026-04-15",
      "Downloaded payroll CSV",
    ],
  };

  const allRows = [];
  for (let i = 0; i < 137; i++) {
    const d = new Date(2026, 3, 8, 8, 0, 0);
    d.setMinutes(d.getMinutes() - i * 37);
    const name = NAMES[i % NAMES.length];
    const action = ACTIONS[i % ACTIONS.length];
    const detail = DETAILS[action][i % DETAILS[action].length];
    allRows.push({
      ts: d,
      employee: name,
      action: action,
      details: detail,
      ip: "192.168.1." + (10 + (i % 50)),
      json: {
        action: action,
        employee: name,
        detail: detail,
        agent: navigator.userAgent.substring(0, 80),
      },
    });
  }

  function fmtTs(d) {
    const yyyy = d.getFullYear();
    const mm = String(d.getMonth() + 1).padStart(2, "0");
    const dd = String(d.getDate()).padStart(2, "0");
    let h = d.getHours();
    const ampm = h >= 12 ? "PM" : "AM";
    h = h % 12 || 12;
    return (
      yyyy +
      "-" +
      mm +
      "-" +
      dd +
      " " +
      String(h).padStart(2, "0") +
      ":" +
      String(d.getMinutes()).padStart(2, "0") +
      ":" +
      String(d.getSeconds()).padStart(2, "0") +
      " " +
      ampm
    );
  }

  function actionBadge(a) {
    const map = {
      PUNCH: "bg-emerald-100 text-emerald-800",
      LOGIN: "bg-blue-100 text-blue-800",
      LOGOUT: "bg-slate-200 text-slate-700",
      EDIT: "bg-amber-100 text-amber-800",
      PAYROLL_VIEW: "bg-purple-100 text-purple-800",
    };
    return (
      '<span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold font-mono uppercase tracking-wider ' +
      (map[a] || "bg-slate-100 text-slate-700") +
      '">' +
      escapeHtml(a) +
      "</span>"
    );
  }

  let page = 0;
  const PAGE_SIZE = 50;
  const tbody = document.getElementById("audit-tbody");
  const loadMoreBtn = document.getElementById("load-more");
  const loadedCount = document.getElementById("loaded-count");

  function renderPage() {
    const start = page * PAGE_SIZE;
    const slice = allRows.slice(start, start + PAGE_SIZE);
    slice.forEach(function (r) {
      const tr = document.createElement("tr");
      tr.className = "hover:bg-slate-50 transition-colors cursor-pointer";
      tr.dataset.json = JSON.stringify(r.json);
      tr.innerHTML =
        '<td class="px-4 py-3 text-slate-400">' +
        '<svg class="w-4 h-4 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg>' +
        "</td>" +
        '<td class="px-4 py-3 font-mono text-xs text-slate-700 whitespace-nowrap">' +
        fmtTs(r.ts) +
        "</td>" +
        '<td class="px-4 py-3 text-sm text-slate-900 whitespace-nowrap">' +
        escapeHtml(r.employee) +
        "</td>" +
        '<td class="px-4 py-3 whitespace-nowrap">' +
        actionBadge(r.action) +
        "</td>" +
        '<td class="px-4 py-3 text-xs text-slate-600 max-w-md truncate">' +
        escapeHtml(r.details) +
        "</td>" +
        '<td class="px-4 py-3 font-mono text-xs text-slate-500 whitespace-nowrap">' +
        escapeHtml(r.ip) +
        "</td>";

      tr.addEventListener("click", function () {
        // Toggle JSON details row
        const next = tr.nextElementSibling;
        if (next && next.dataset.detailsFor === "1") {
          next.parentNode.removeChild(next);
          tr.querySelector("svg").style.transform = "";
          return;
        }
        const detailTr = document.createElement("tr");
        detailTr.dataset.detailsFor = "1";
        detailTr.innerHTML =
          '<td colspan="6" class="bg-slate-50 px-4 py-4">' +
          '<p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">JSON details</p>' +
          '<pre class="font-mono text-[11px] text-slate-700 bg-white border border-slate-200 rounded-md p-3 overflow-x-auto whitespace-pre-wrap">' +
          escapeHtml(JSON.stringify(r.json, null, 2)) +
          "</pre>" +
          "</td>";
        tr.parentNode.insertBefore(detailTr, tr.nextSibling);
        tr.querySelector("svg").style.transform = "rotate(90deg)";
      });

      tbody.appendChild(tr);
    });

    page++;
    const total = tbody.querySelectorAll("tr").length;
    loadedCount.textContent =
      "Showing " + total + " of " + allRows.length + " entries";
    if (total >= allRows.length) {
      loadMoreBtn.disabled = true;
      loadMoreBtn.classList.add("opacity-50", "cursor-not-allowed");
      loadMoreBtn.textContent = "All entries loaded";
    }
  }

  loadMoreBtn.addEventListener("click", renderPage);
  renderPage();
})();
