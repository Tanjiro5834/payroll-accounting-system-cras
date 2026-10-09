(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);

  const STATUS = {
    worked:         { label: "Worked",          cls: "bg-emerald-50 text-emerald-800" },
    sunday_duty:    { label: "Sunday duty",     cls: "bg-sky-50 text-sky-800" },
    holiday_worked: { label: "Worked holiday",  cls: "bg-sky-50 text-sky-800" },
    holiday:        { label: "Holiday",         cls: "bg-slate-100 text-slate-600" },
    rest_day:       { label: "Rest day",        cls: "bg-slate-100 text-slate-500" },
    missed_duty:    { label: "Missed Sunday duty", cls: "bg-red-50 text-red-800" },
    absent:         { label: "Absent",          cls: "bg-red-50 text-red-800" },
    today:          { label: "Today",           cls: "bg-coolant-tint text-coolant" },
  };
  const SLOTS = [["AM_IN", "Time in"], ["AM_OUT", "Lunch out"], ["PM_IN", "Lunch in"], ["PM_OUT", "Time out"], ["OT_IN", "OT in"], ["OT_OUT", "OT out"]];
  const PUNCH = { AM_IN: "Time in", AM_OUT: "Lunch out", PM_IN: "Lunch in", PM_OUT: "Time out", OT_IN: "OT in", OT_OUT: "OT out" };

  const now = new Date();
  const thisMonth = now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0");
  let data = null;
  let filter = "all";

  const shiftMonth = (ym, n) => {
    const [y, m] = ym.split("-").map(Number);
    const d = new Date(y, m - 1 + n, 1);
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0");
  };
  const clock = (hm) => {
    if (!hm) return null;
    const [h, m] = hm.split(":").map(Number);
    return (h % 12 || 12) + ":" + String(m).padStart(2, "0") + " " + (h < 12 ? "AM" : "PM");
  };
  const dayLabel = (ymd) => new Date(ymd + "T00:00").toLocaleDateString("en-PH", { weekday: "short", month: "short", day: "numeric" });
  const hrs = (min) => {
    if (!min) return "0";
    const h = Math.floor(min / 60), m = min % 60;
    return (h ? h + " hr" + (h === 1 ? "" : "s") : "") + (h && m ? " " : "") + (m ? m + " min" : "");
  };
  const num = (n) => Number(n).toFixed(2).replace(/\.00$/, "");

  function isIssue(d) {
    return d.late_charged > 0 || d.status === "absent" || d.status === "missed_duty" || d.flags.length > 0 || d.undertime > 0;
  }

  function renderTotals(t, s) {
    $("schedule").textContent = "Schedule " + clock(s.work_start) + " – " + clock(s.work_end) + ", lunch " + clock(s.lunch_start) + " – " + clock(s.lunch_end) +
      ". Late from " + s.threshold + " min, deducted in whole hours.";
    $("t-days").textContent = t.days_worked;
    $("t-days-sub").textContent = t.absent ? t.absent + " absent" : "No absences";
    $("t-late").textContent = hrs(t.late_charged);
    $("t-late-sub").textContent = t.late_days ? t.late_days + " day" + (t.late_days === 1 ? "" : "s") + " late" : "On time all month";
    $("t-ot").textContent = num(t.ot_hours);
    $("t-ut").textContent = hrs(t.undertime);
    $("t-flags").textContent = t.open_flags ? t.open_flags + " flag" + (t.open_flags === 1 ? "" : "s") + " under review" : "No open flags";
  }

  function slot(type, label, d) {
    const t = d.times[type];
    // OT and lunch punches only show when they exist (lunch is no longer punched).
    if (!t && (type.startsWith("OT") || type === "AM_OUT" || type === "PM_IN")) return "";
    const flagged = d.flags.some((f) => f.punch === type && !f.reviewed);
    return '<div class="rounded-lg px-2 py-1.5 ' + (flagged ? "bg-amber-50 ring-1 ring-amber-200" : "bg-ink-50") + '">' +
      '<p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500">' + label + "</p>" +
      '<p class="num text-sm ' + (t ? "text-ink font-semibold" : "text-slate-300") + '">' + (t ? clock(t) : "—") + "</p></div>";
  }

  function dayCard(d) {
    const st = STATUS[d.status] || STATUS.worked;
    const worked = Object.values(d.times).some(Boolean);
    const chips = '<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold ' + st.cls + '">' + st.label + "</span>" +
      (d.holiday ? '<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-amber-50 text-amber-800">' + esc(d.holiday.name) + "</span>" : "") +
      (d.on_duty && d.status !== "missed_duty" ? '<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold bg-sky-50 text-sky-800">Rostered</span>' : "");

    const stats = worked && d.status !== "today"
      ? '<div class="flex flex-wrap gap-x-4 gap-y-1 text-xs text-slate-600 mt-3">' +
          '<span><span class="num font-semibold text-ink">' + num(d.regular_hours) + "</span> regular hrs</span>" +
          (d.ot_hours ? '<span><span class="num font-semibold text-coolant">' + num(d.ot_hours) + "</span> OT hrs</span>" : "") +
          (d.late_charged ? '<span class="text-warning font-semibold">' + hrs(d.late_charged) + " late deducted</span>" : "") +
        "</div>"
      : "";

    const notes = d.notes.length
      ? '<ul class="mt-2 space-y-1">' + d.notes.map((n) => '<li class="text-xs ' + (n === "On time." ? "text-positive" : "text-slate-600") + '">' + esc(n) + "</li>").join("") + "</ul>"
      : "";

    const flags = d.flags.length
      ? '<ul class="mt-2 space-y-1">' + d.flags.map((f) =>
          '<li class="text-xs ' + (f.reviewed ? "text-slate-400" : "text-amber-800") + '">⚑ ' + esc(PUNCH[f.punch] || f.punch) + ": " + esc(f.reason) +
          (f.reviewed ? " · reviewed" : " · waiting for review") + "</li>").join("") + "</ul>"
      : "";

        const empty =
          !worked && d.status === "absent"
            ? '<p class="text-xs text-slate-500 mt-2">No punches recorded. A work day with no punches is unpaid.</p>'
            : !worked && d.status === "missed_duty"
              ? '<p class="text-xs text-slate-500 mt-2">You were on the Sunday duty roster but no punches were recorded.</p>'
              : !worked && d.status === "today"
                ? '<p class="text-xs text-slate-500 mt-2">No punches yet today.</p>'
                : "";

    return '<li class="bg-white rounded-2xl border border-line shadow-sm p-4">' +
      '<div class="flex flex-wrap items-center justify-between gap-2">' +
        '<p class="num text-sm font-bold text-ink">' + esc(dayLabel(d.date)) + "</p>" +
        '<div class="flex flex-wrap gap-1">' + chips + "</div>" +
      "</div>" +
      (worked ? '<div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-6 gap-2 mt-3">' + SLOTS.map(([t, l]) => slot(t, l, d)).join("") + "</div>" : "") +
      stats + notes + flags + empty +
    "</li>";
  }

  function renderDays() {
    const rows = data.days.filter((d) => filter === "all" || isIssue(d));
    $("days").innerHTML = rows.length
      ? rows.map(dayCard).join("")
      : '<li class="bg-white rounded-2xl border border-line p-8 text-center text-sm text-slate-500">' +
          (filter === "all" ? "Nothing recorded for this month." : "No late, absent or flagged days this month. Nice.") + "</li>";
  }

  async function load(month) {
    $("month").value = month;
    $("next-month").disabled = month >= thisMonth;
    try {
      data = await api.get("my-time-logs", "month", { params: { month } });
      renderTotals(data.totals, data.schedule);
      renderDays();
    } catch (e) {
      $("days").innerHTML = '<li class="bg-white rounded-2xl border border-line p-6 text-sm text-negative">' + esc(e.message) + "</li>";
    }
  }

  function paintFilters() {
    document.querySelectorAll(".filter-btn").forEach((b) => {
      const on = b.dataset.filter === filter;
      b.setAttribute("aria-pressed", String(on));
      b.className = "filter-btn px-3 min-h-[36px] rounded-full border text-xs font-semibold transition-colors " +
        (on ? "bg-ink text-white border-ink" : "bg-white text-slate-600 border-line hover:bg-ink-50");
    });
  }

  document.querySelectorAll(".filter-btn").forEach((b) => b.addEventListener("click", () => {
    filter = b.dataset.filter;
    paintFilters();
    if (data) renderDays();
  }));
  $("prev-month").addEventListener("click", () => load(shiftMonth($("month").value, -1)));
  $("next-month").addEventListener("click", () => load(shiftMonth($("month").value, 1)));
  $("month").addEventListener("change", (e) => { if (e.target.value) load(e.target.value > thisMonth ? thisMonth : e.target.value); });

  paintFilters();
  load(thisMonth);
})();
