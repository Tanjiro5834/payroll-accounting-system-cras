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
  const REFRESH_MS = 60000;
  const FLAG_ICON =
    '<svg class="w-3 h-3" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>';
  const PIN_ICON =
    '<svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>';

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str == null ? "" : String(str);
    return div.innerHTML;
  }

  function setText(id, text) {
    document.getElementById(id).textContent = text;
  }

  // "2026-09-28 07:32:10" → "7:32 AM". Parsed by hand so the browser's timezone
  // can't shift it: the server already returns Manila time.
  function formatTime(timestamp) {
    const [h, m] = String(timestamp).split(" ")[1].split(":").map(Number);
    const suffix = h < 12 ? "AM" : "PM";
    return ((h % 12) || 12) + ":" + String(m).padStart(2, "0") + " " + suffix;
  }

  function punchLabel(type) {
    return String(type).replace("_", " ");
  }

  function punchBadgeClass(type) {
    return type.endsWith("_IN") ? "bg-emerald-100 text-emerald-800" : "bg-slate-200 text-slate-700";
  }

  function gpsOf(row) {
    return row.gps_lat != null && row.gps_lng != null ? row.gps_lat + "," + row.gps_lng : null;
  }

  function mapsUrl(gps) {
    return "https://www.google.com/maps?q=" + encodeURIComponent(gps);
  }

  function emptyState(message) {
    return '<p class="px-4 sm:px-5 py-10 text-center text-sm text-slate-500">' + escapeHtml(message) + "</p>";
  }

  /* ============================================================
               KPI cards
               ============================================================ */
  function renderKpis(kpi, pendingPayroll) {
    setText("kpi-active", kpi.total_active);
    setText("kpi-active-sub", kpi.on_leave + " on leave today");

    setText("kpi-present", kpi.present);
    setText(
      "kpi-present-sub",
      kpi.is_work_day
        ? (kpi.attendance_rate ?? 0) + "% attendance · " + kpi.late + " late"
        : "Rest day"
    );

    setText("kpi-flagged", kpi.flagged);
    setText("kpi-payroll", pendingPayroll.length);
  }

  /* ============================================================
               Today's activity
               ============================================================ */
  const activityTbody = document.getElementById("activity-tbody");
  const activityMobile = document.getElementById("activity-mobile");

  function activityRow(row) {
    const gps = gpsOf(row);
    const flag = row.is_flagged == 1
      ? '<span class="ml-1.5 inline-flex items-center gap-0.5 px-1.5 py-0.5 rounded bg-red-100 text-red-700 text-[10px] font-bold font-mono uppercase tracking-wider" title="' +
        escapeHtml(row.flag_reason || "Flagged") + '">' + FLAG_ICON + "FLAG</span>"
      : "";

    const tr = document.createElement("tr");
    tr.className = "hover:bg-slate-50 transition-colors";
    tr.innerHTML =
      '<td class="px-4 py-3 text-slate-900 font-medium whitespace-nowrap">' + escapeHtml(row.full_name) + flag + "</td>" +
      '<td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded font-mono text-[11px] font-semibold ' +
        punchBadgeClass(row.punch_type) + '">' + escapeHtml(punchLabel(row.punch_type)) + "</span></td>" +
      '<td class="px-4 py-3 font-mono text-xs text-slate-600 whitespace-nowrap">' + escapeHtml(formatTime(row.punch_time)) + "</td>" +
      '<td class="px-4 py-3 font-mono text-xs text-slate-500 whitespace-nowrap">' + escapeHtml(row.ip_address || "—") + "</td>" +
      '<td class="px-4 py-3 text-right">' +
        (gps
          ? '<a href="' + mapsUrl(gps) + '" target="_blank" rel="noopener" class="font-mono text-xs text-emerald-700 hover:text-emerald-800 hover:underline whitespace-nowrap">' + escapeHtml(gps) + "</a>"
          : '<span class="font-mono text-xs text-slate-400">—</span>') +
      "</td>";
    return tr;
  }

  function activityItem(row) {
    const gps = gpsOf(row);
    const li = document.createElement("li");
    li.className = "px-4 py-3 flex items-start justify-between gap-3";
    li.innerHTML =
      '<div class="min-w-0 flex-1">' +
        '<p class="text-sm font-semibold text-slate-900 truncate">' + escapeHtml(row.full_name) + "</p>" +
        '<div class="flex items-center gap-2 mt-1">' +
          '<span class="inline-block px-1.5 py-0.5 rounded font-mono text-[10px] font-semibold ' +
            punchBadgeClass(row.punch_type) + '">' + escapeHtml(punchLabel(row.punch_type)) + "</span>" +
          '<span class="font-mono text-xs text-slate-500">' + escapeHtml(formatTime(row.punch_time)) + "</span>" +
          (row.is_flagged == 1 ? '<span class="text-red-600 text-[10px] font-bold font-mono uppercase">⚠ FLAG</span>' : "") +
        "</div>" +
        '<p class="font-mono text-[10px] text-slate-400 mt-1">' + escapeHtml(row.ip_address || "—") + "</p>" +
      "</div>" +
      (gps
        ? '<a href="' + mapsUrl(gps) + '" target="_blank" rel="noopener" aria-label="View location" class="flex items-center justify-center w-10 h-10 -mr-1 rounded-lg text-emerald-700 hover:bg-emerald-50 flex-shrink-0">' + PIN_ICON + "</a>"
        : "");
    return li;
  }

  function renderActivity(activity) {
    activityTbody.replaceChildren();
    activityMobile.replaceChildren();
    setText("activity-sub", activity.total + " punch" + (activity.total === 1 ? "" : "es") + " today");

    if (!activity.latest.length) {
      activityTbody.innerHTML = '<tr><td colspan="5">' + emptyState("No punches yet today.") + "</td></tr>";
      activityMobile.innerHTML = "<li>" + emptyState("No punches yet today.") + "</li>";
      return;
    }

    activity.latest.forEach(function (row) {
      activityTbody.appendChild(activityRow(row));
      activityMobile.appendChild(activityItem(row));
    });
  }

  /* ============================================================
               Flagged anomalies
               ============================================================ */
  const flaggedList = document.getElementById("flagged-list");

  function flaggedItem(item) {
    const li = document.createElement("li");
    li.className = "px-4 sm:px-5 py-4";
    li.innerHTML =
      '<div class="flex items-start gap-3">' +
        '<div class="w-9 h-9 rounded-lg bg-red-50 flex items-center justify-center flex-shrink-0 text-red-600">' + FLAG_ICON.replace("w-3 h-3", "w-5 h-5") + "</div>" +
        '<div class="min-w-0 flex-1">' +
          '<p class="text-sm font-semibold text-slate-900">' + escapeHtml(item.full_name) + "</p>" +
          '<p class="text-xs text-slate-600 mt-0.5">' + escapeHtml(item.flag_reason || "Flagged for review") + "</p>" +
          '<p class="font-mono text-[11px] text-slate-400 mt-1.5">' +
            escapeHtml(punchLabel(item.punch_type)) + " · " + escapeHtml(formatTime(item.punch_time)) +
          "</p>" +
        "</div>" +
      "</div>";
    return li;
  }

  function renderFlagged(items) {
    flaggedList.replaceChildren();
    if (!items.length) {
      flaggedList.innerHTML = "<li>" + emptyState("No flagged punches today.") + "</li>";
      return;
    }
    items.forEach(function (item) {
      flaggedList.appendChild(flaggedItem(item));
    });
  }

  /* ============================================================
               Load + auto-refresh
               ============================================================ */
  function showLoadError(error) {
    setText("activity-sub", "Couldn't load: " + error.message);
  }

  // All four requests run in parallel; the page renders once they're all back.
  async function load() {
    try {
      const [kpi, activity, flagged, pendingPayroll] = await Promise.all([
        api.get("dashboard", "kpiSummary"),
        api.get("dashboard", "todayActivity", { params: { limit: 20 } }),
        api.get("dashboard", "flaggedPunches"),
        api.get("dashboard", "payrollPending"),
      ]);
      renderKpis(kpi, pendingPayroll);
      renderActivity(activity);
      renderFlagged(flagged);
    } catch (error) {
      showLoadError(error);
    }
  }

  load();
  setInterval(function () {
    if (!document.hidden) load();
  }, REFRESH_MS);
})();
