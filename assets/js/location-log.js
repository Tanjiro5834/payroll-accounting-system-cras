(function () {
  "use strict";

  const LABELS = {
    AM_IN: "TIME IN",
    PM_OUT: "TIME OUT",
    AM_OUT: "LUNCH OUT",
    PM_IN: "LUNCH IN",
    OT_IN: "OT IN",
    OT_OUT: "OT OUT",
  };

  const tbody = document.getElementById("loc-tbody");
  const employeeSelect = document.getElementById("filter-employee");
  const startInput = document.getElementById("filter-start");
  const endInput = document.getElementById("filter-end");
  const names = {};

  function esc(s) {
    const d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function ymd(d) {
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
  }

  function message(text) {
    tbody.innerHTML =
      '<tr><td colspan="8" class="px-4 py-10 text-center text-sm text-slate-400">' + esc(text) + "</td></tr>";
  }

  function row(r) {
    const type = String(r.punch_type || "");
    const isIn = type.indexOf("IN") !== -1;
    const time = String(r.punch_time || "").split(" ")[1] || "";
    const hasGps = r.gps_lat != null && r.gps_lng != null;

    const gps = hasGps
      ? '<a href="https://www.google.com/maps?q=' + encodeURIComponent(r.gps_lat + "," + r.gps_lng) +
        '" target="_blank" rel="noopener" class="font-mono text-xs text-emerald-700 hover:text-emerald-800 hover:underline">' +
        esc(Number(r.gps_lat).toFixed(5) + ", " + Number(r.gps_lng).toFixed(5)) + "</a>"
      : '<span class="font-mono text-xs text-slate-400">No location</span>';

    const acc = r.accuracy != null
      ? '<span class="' + (r.accuracy > 100 ? "text-red-600 font-semibold" : "text-slate-600") + '">±' + esc(r.accuracy) + "m</span>"
      : '<span class="text-slate-400">—</span>';

    const flag = r.is_flagged
      ? '<span class="inline-flex items-center px-2 py-0.5 rounded-full bg-red-100 text-red-800 text-[11px] font-semibold">' +
        esc(r.flag_reason || "Flagged") + "</span>"
      : '<span class="font-mono text-xs text-slate-400">—</span>';

    return (
      '<tr class="hover:bg-slate-50 transition-colors">' +
      '<td class="px-4 py-3 font-mono text-xs text-slate-700 whitespace-nowrap">' + esc(r.work_date) + "</td>" +
      '<td class="px-4 py-3 text-sm text-slate-800 whitespace-nowrap">' + esc(names[r.employee_id] || "#" + r.employee_id) + "</td>" +
      '<td class="px-4 py-3 whitespace-nowrap"><span class="inline-block px-2 py-0.5 rounded font-mono text-[10px] font-semibold ' +
      (isIn ? "bg-emerald-100 text-emerald-800" : "bg-slate-200 text-slate-700") + '">' + esc(LABELS[type] || type) + "</span></td>" +
      '<td class="px-4 py-3 font-mono text-xs text-slate-700 whitespace-nowrap">' + esc(time.substring(0, 5)) + "</td>" +
      '<td class="px-4 py-3 font-mono text-xs text-slate-500 whitespace-nowrap">' + esc(r.ip_address || "—") + "</td>" +
      '<td class="px-4 py-3 whitespace-nowrap">' + gps + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-xs whitespace-nowrap">' + acc + "</td>" +
      '<td class="px-4 py-3">' + flag + "</td>" +
      "</tr>"
    );
  }

  async function load() {
    const start = startInput.value;
    const end = endInput.value;
    if (!start || !end) return message("Pick a date range.");
    if (start > end) return message("Date From must be before Date To.");

    message("Loading…");
    const params = { start: start, end: end };
    if (employeeSelect.value) params.employee_id = employeeSelect.value;

    try {
      const rows = await api.get("location-log", "locationLog", { params: params });
      if (!Array.isArray(rows) || rows.length === 0) return message("No punches in this range.");
      rows.sort(function (a, b) { return String(b.punch_time).localeCompare(String(a.punch_time)); });
      tbody.innerHTML = rows.map(row).join("");
    } catch (err) {
      message(err.message || "Failed to load location logs.");
    }
  }

  async function loadEmployees() {
    try {
      const list = await api.get("employees", "index");
      (Array.isArray(list) ? list : []).forEach(function (e) {
        const name = e.name || e.full_name;
        if (e.id == null || !name) return;
        names[e.id] = name;
        const opt = document.createElement("option");
        opt.value = String(e.id);
        opt.textContent = name;
        employeeSelect.appendChild(opt);
      });
    } catch (err) {
      // names fall back to #id
    }
  }

  const today = new Date();
  const weekAgo = new Date(today);
  weekAgo.setDate(today.getDate() - 6);
  startInput.value = ymd(weekAgo);
  endInput.value = ymd(today);

  [employeeSelect, startInput, endInput].forEach(function (el) {
    el.addEventListener("change", load);
  });

  loadEmployees().then(load);
})();