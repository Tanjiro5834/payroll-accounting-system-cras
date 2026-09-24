(function () {
  "use strict";

  /* ============================================================
               Mobile Drawer
               ============================================================ */
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
    const isOpen = hamburgerBtn.getAttribute("aria-expanded") === "true";
    if (isOpen) closeDrawer();
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

  /* ============================================================
               Helpers
               ============================================================ */
  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = String(str);
    return div.innerHTML;
  }

  /* ============================================================
               Today's Activity Data (mock — replaced by PHP)
               ============================================================ */
  const activity = [
    {
      name: "Juan Dela Cruz",
      punch: "AM IN",
      time: "7:32 AM",
      ip: "192.168.1.24",
      gps: "14.5995,120.9842",
      flagged: false,
    },
    {
      name: "Maria Santos",
      punch: "AM IN",
      time: "7:45 AM",
      ip: "192.168.1.31",
      gps: "14.5995,120.9842",
      flagged: false,
    },
    {
      name: "Pedro Reyes",
      punch: "AM IN",
      time: "8:15 AM",
      ip: "192.168.1.42",
      gps: "14.6091,120.9822",
      flagged: true,
    },
    {
      name: "Ana Villanueva",
      punch: "AM IN",
      time: "7:58 AM",
      ip: "192.168.1.18",
      gps: "14.5995,120.9842",
      flagged: false,
    },
    {
      name: "Carlos Mendoza",
      punch: "AM OUT",
      time: "12:03 PM",
      ip: "192.168.1.55",
      gps: "14.5891,120.9761",
      flagged: false,
    },
    {
      name: "Rosa Bautista",
      punch: "AM OUT",
      time: "12:01 PM",
      ip: "192.168.1.61",
      gps: "14.5995,120.9842",
      flagged: false,
    },
    {
      name: "Miguel Torres",
      punch: "PM IN",
      time: "1:05 PM",
      ip: "192.168.1.72",
      gps: "14.6091,120.9822",
      flagged: true,
    },
  ];

  const activityTbody = document.getElementById("activity-tbody");
  const activityMobile = document.getElementById("activity-mobile");

  activity.forEach(function (row) {
    const flaggedBadge = row.flagged
      ? '<span class="ml-1.5 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-red-100 text-red-700 text-[10px] font-bold font-mono uppercase tracking-wider" title="Flagged">' +
        '<svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>' +
        "FLAG</span>"
      : "";

    // Desktop row
    const tr = document.createElement("tr");
    tr.className = "hover:bg-slate-50 transition-colors";
    tr.innerHTML =
      '<td class="px-4 py-3 text-slate-900 font-medium whitespace-nowrap">' +
      escapeHtml(row.name) +
      flaggedBadge +
      "</td>" +
      '<td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded font-mono text-[11px] font-semibold ' +
      (row.punch.indexOf("IN") !== -1
        ? "bg-emerald-100 text-emerald-800"
        : "bg-slate-200 text-slate-700") +
      '">' +
      escapeHtml(row.punch) +
      "</span></td>" +
      '<td class="px-4 py-3 font-mono text-xs text-slate-600 whitespace-nowrap">' +
      escapeHtml(row.time) +
      "</td>" +
      '<td class="px-4 py-3 font-mono text-xs text-slate-500 whitespace-nowrap">' +
      escapeHtml(row.ip) +
      "</td>" +
      '<td class="px-4 py-3 text-right"><a href="https://www.google.com/maps?q=' +
      encodeURIComponent(row.gps) +
      '" target="_blank" rel="noopener" class="font-mono text-xs text-emerald-700 hover:text-emerald-800 hover:underline whitespace-nowrap">' +
      escapeHtml(row.gps) +
      "</a></td>";
    activityTbody.appendChild(tr);

    // Mobile list item
    const li = document.createElement("li");
    li.className = "px-4 py-3 flex items-start justify-between gap-3";
    li.innerHTML =
      '<div class="min-w-0 flex-1">' +
      '<p class="text-sm font-semibold text-slate-900 truncate">' +
      escapeHtml(row.name) +
      "</p>" +
      '<div class="flex items-center gap-2 mt-1">' +
      '<span class="inline-block px-1.5 py-0.5 rounded font-mono text-[10px] font-semibold ' +
      (row.punch.indexOf("IN") !== -1
        ? "bg-emerald-100 text-emerald-800"
        : "bg-slate-200 text-slate-700") +
      '">' +
      escapeHtml(row.punch) +
      "</span>" +
      '<span class="font-mono text-xs text-slate-500">' +
      escapeHtml(row.time) +
      "</span>" +
      (row.flagged
        ? '<span class="text-red-600 text-[10px] font-bold font-mono uppercase">⚠ FLAG</span>'
        : "") +
      "</div>" +
      '<p class="font-mono text-[10px] text-slate-400 mt-1">' +
      escapeHtml(row.ip) +
      "</p>" +
      "</div>" +
      '<a href="https://www.google.com/maps?q=' +
      encodeURIComponent(row.gps) +
      '" target="_blank" rel="noopener" aria-label="View location" class="flex items-center justify-center w-10 h-10 -mr-1 rounded-lg text-emerald-700 hover:bg-emerald-50 flex-shrink-0">' +
      '<svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>' +
      "</a>";
    activityMobile.appendChild(li);
  });

  /* ============================================================
               Flagged Anomalies Data
               ============================================================ */
  const flagged = [
    {
      name: "Pedro Reyes",
      reason: "Late punch (45 min after shift start)",
      date: "2026-04-08",
      time: "8:15 AM",
    },
    {
      name: "Miguel Torres",
      reason: "GPS location outside site radius",
      date: "2026-04-08",
      time: "1:05 PM",
    },
    {
      name: "Jose Aquino",
      reason: "Duplicate punch within 2 minutes",
      date: "2026-04-07",
      time: "5:03 PM",
    },
  ];

  const flaggedList = document.getElementById("flagged-list");

  flagged.forEach(function (item) {
    const li = document.createElement("li");
    li.className = "px-4 sm:px-5 py-4";
    li.innerHTML =
      '<div class="flex items-start gap-3">' +
      '<div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0">' +
      '<svg class="w-5 h-5 text-red-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>' +
      "</div>" +
      '<div class="min-w-0 flex-1">' +
      '<p class="text-sm font-semibold text-slate-900">' +
      escapeHtml(item.name) +
      "</p>" +
      '<p class="text-xs text-slate-600 mt-0.5">' +
      escapeHtml(item.reason) +
      "</p>" +
      '<p class="font-mono text-[11px] text-slate-400 mt-1.5">' +
      escapeHtml(item.date) +
      " · " +
      escapeHtml(item.time) +
      "</p>" +
      '<a href="flagged-punches.html" class="inline-flex items-center gap-1 mt-2 text-xs font-semibold text-emerald-700 hover:text-emerald-800 transition-colors">Review &rarr;</a>' +
      "</div>" +
      "</div>";
    flaggedList.appendChild(li);
  });
})();
