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

  /* Mock */
  const PUNCHES = ["AM IN", "AM OUT", "PM IN", "PM OUT", "OT IN", "OT OUT"];
  const FLAGS = [
    null,
    null,
    null,
    null,
    { reason: "GPS outside site radius", code: "GPS_OUT_OF_SITE" },
    { reason: "Low GPS accuracy (>100m)", code: "LOW_ACCURACY" },
    { reason: "IP address changed mid-shift", code: "IP_CHANGE" },
  ];
  const rows = [];
  for (let i = 0; i < 24; i++) {
    const d = new Date(2026, 3, 8);
    d.setDate(d.getDate() - Math.floor(i / 6));
    d.setHours(7 + (i % 10), (i * 7) % 60, (i * 13) % 60);
    rows.push({
      date: d.toISOString().substring(0, 10),
      punch: PUNCHES[i % PUNCHES.length],
      time:
        String(d.getHours()).padStart(2, "0") +
        ":" +
        String(d.getMinutes()).padStart(2, "0"),
      ip: "192.168.1." + (10 + (i % 40)),
      lat: (14.5995 + (Math.random() - 0.5) * 0.1).toFixed(5),
      lng: (120.9842 + (Math.random() - 0.5) * 0.1).toFixed(5),
      accuracy: Math.floor(5 + Math.random() * 120),
      flag: FLAGS[i % FLAGS.length],
    });
  }

  const tbody = document.getElementById("loc-tbody");
  rows.forEach(function (r) {
    const tr = document.createElement("tr");
    tr.className = "hover:bg-slate-50 transition-colors";
    const flagCell = r.flag
      ? '<span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-[10px] font-bold font-mono uppercase tracking-wider" title="' +
        escapeHtml(r.flag.reason) +
        '">' +
        '<svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>' +
        escapeHtml(r.flag.code) +
        "</span>"
      : '<span class="font-mono text-xs text-slate-400">—</span>';

    const mapsUrl = "https://www.google.com/maps?q=" + r.lat + "," + r.lng;
    tr.innerHTML =
      '<td class="px-4 py-3 font-mono text-xs text-slate-700 whitespace-nowrap">' +
      escapeHtml(r.date) +
      "</td>" +
      '<td class="px-4 py-3 whitespace-nowrap"><span class="inline-block px-2 py-0.5 rounded font-mono text-[10px] font-semibold ' +
      (r.punch.indexOf("IN") !== -1
        ? "bg-emerald-100 text-emerald-800"
        : "bg-slate-200 text-slate-700") +
      '">' +
      escapeHtml(r.punch) +
      "</span></td>" +
      '<td class="px-4 py-3 font-mono text-xs text-slate-700 whitespace-nowrap">' +
      escapeHtml(r.time) +
      "</td>" +
      '<td class="px-4 py-3 font-mono text-xs text-slate-500 whitespace-nowrap">' +
      escapeHtml(r.ip) +
      "</td>" +
      '<td class="px-4 py-3 whitespace-nowrap"><a href="' +
      mapsUrl +
      '" target="_blank" rel="noopener" class="font-mono text-xs text-emerald-700 hover:text-emerald-800 hover:underline">' +
      r.lat +
      ", " +
      r.lng +
      "</a></td>" +
      '<td class="px-4 py-3 text-right font-mono text-xs whitespace-nowrap ' +
      (r.accuracy > 100 ? "text-red-600 font-semibold" : "text-slate-600") +
      '">±' +
      r.accuracy +
      "m</td>" +
      '<td class="px-4 py-3 whitespace-nowrap">' +
      flagCell +
      "</td>";
    tbody.appendChild(tr);
  });
})();
