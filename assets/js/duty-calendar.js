(function () {
  "use strict";

  const WEEKS = 8;
  const TYPE_LABEL = {
    regular: "Regular holiday",
    special_non_working: "Special non-working",
    special_working: "Special working",
  };
  const TYPE_BADGE = {
    regular: "bg-red-50 text-red-800",
    special_non_working: "bg-amber-50 text-amber-800",
    special_working: "bg-slate-100 text-slate-600",
  };

  const $ = (id) => document.getElementById(id);
  const esc = (s) => String(s == null ? "" : s).replace(/[&<>"']/g, (c) => ({ "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#39;" })[c]);

  // Dates are handled as YYYY-MM-DD strings in UTC so the browser's timezone never shifts a day.
  const toDate = (ymd) => new Date(ymd + "T00:00:00Z");
  const ymd = (d) => d.toISOString().slice(0, 10);
  const addDays = (s, n) => { const d = toDate(s); d.setUTCDate(d.getUTCDate() + n); return ymd(d); };
  const pretty = (s, opts) => toDate(s).toLocaleDateString("en-PH", Object.assign({ timeZone: "UTC", month: "short", day: "numeric" }, opts));
  const today = (() => { const n = new Date(); return ymd(new Date(Date.UTC(n.getFullYear(), n.getMonth(), n.getDate()))); })();
  const upcomingSunday = (() => { const d = toDate(today); return addDays(today, (7 - d.getUTCDay()) % 7); })();

  const state = {
    start: addDays(upcomingSunday, -7),
    employees: [],
    duties: {},
    holidays: {},       // year -> rows
    editing: null,      // duty being edited, or { duty_date } for a new one
  };

  function toast(message, ok) {
    const t = document.createElement("div");
    t.className = "pointer-events-auto w-full max-w-sm rounded-xl shadow-lg px-4 py-3 text-sm font-medium " + (ok ? "bg-emerald-700 text-white" : "bg-red-600 text-white");
    t.setAttribute("role", ok ? "status" : "alert");
    t.textContent = message;
    $("toast-container").appendChild(t);
    setTimeout(() => { t.style.transition = "opacity 200ms"; t.style.opacity = "0"; setTimeout(() => t.remove(), 220); }, 3200);
  }

  // ---------- Roster ----------

  function sundays() {
    return Array.from({ length: WEEKS }, (_, i) => addDays(state.start, i * 7));
  }

  async function holidaysFor(year) {
    if (!state.holidays[year]) state.holidays[year] = await api.get("duty-calendar", "holidays", { params: { year } });
    return state.holidays[year];
  }

  async function loadRoster() {
    const days = sundays();
    const end = days[days.length - 1];
    $("roster-range").textContent = pretty(days[0], { year: "numeric" }) + " – " + pretty(end, { year: "numeric" });
    try {
      const [rows] = await Promise.all([
        api.get("duty-calendar", "roster", { params: { start: days[0], end } }),
        holidaysFor(days[0].slice(0, 4)),
        holidaysFor(end.slice(0, 4)),
      ]);
      state.duties = {};
      rows.forEach((d) => { state.duties[d.duty_date] = d; });
      renderRoster(days);
    } catch (e) {
      $("roster-list").innerHTML = '<li class="p-6 text-sm text-negative">' + esc(e.message) + "</li>";
    }
  }

  function holidayOn(date) {
    return (state.holidays[date.slice(0, 4)] || []).find((h) => h.holiday_date === date);
  }

  function renderRoster(days) {
    $("roster-list").innerHTML = days.map((date) => {
      const duty = state.duties[date];
      const holiday = holidayOn(date);
      const past = date < today;
      const tag = date === today
        ? '<span class="px-2 py-0.5 rounded-full bg-coolant-tint text-coolant text-[11px] font-bold">Today</span>'
        : date === upcomingSunday ? '<span class="px-2 py-0.5 rounded-full bg-coolant-tint text-coolant text-[11px] font-bold">Next</span>' : "";
      const hol = holiday
        ? '<span class="px-2 py-0.5 rounded-full text-[11px] font-semibold ' + (TYPE_BADGE[holiday.type] || "") + '">' + esc(holiday.name) + "</span>"
        : "";

      const body = duty
        ? '<p class="text-sm text-ink"><span class="text-slate-500">Lead:</span> <strong>' + esc(duty.lead_name) + "</strong></p>" +
          '<p class="text-xs text-slate-600 mt-1 leading-relaxed">' +
            duty.members.filter((m) => m.id !== duty.lead_employee_id).map((m) => esc(m.name)).join(", ") +
          "</p>" +
          (duty.notes ? '<p class="text-xs text-slate-400 mt-1">' + esc(duty.notes) + "</p>" : "")
        : '<p class="text-sm ' + (past ? "text-slate-400" : "text-amber-700") + '">No team assigned</p>';

      const actions = duty
        ? '<button type="button" data-edit="' + date + '" class="ui-btn px-3 min-h-[40px] rounded-lg border border-line text-xs font-semibold text-coolant hover:bg-coolant-tint focus:outline-none focus:ring-2 focus:ring-frost">Edit</button>' +
          '<button type="button" data-remove="' + duty.id + '" class="ui-btn px-3 min-h-[40px] rounded-lg border border-line text-xs font-semibold text-slate-500 hover:text-negative hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-frost">Remove</button>'
        : '<button type="button" data-edit="' + date + '" class="ui-btn px-3 min-h-[40px] rounded-lg bg-coolant text-white text-xs font-semibold hover:bg-coolant-hover focus:outline-none focus:ring-2 focus:ring-frost">Assign team</button>';

      return '<li class="flex flex-col sm:flex-row sm:items-start gap-3 px-4 sm:px-5 py-4' + (past ? " bg-ink-50/50" : "") + '">' +
        '<div class="sm:w-32 flex-shrink-0">' +
          '<p class="num text-sm font-bold ' + (past ? "text-slate-400" : "text-ink") + '">' + esc(pretty(date, { weekday: "short" })) + "</p>" +
          '<div class="flex flex-wrap gap-1 mt-1">' + tag + hol + "</div>" +
        "</div>" +
        '<div class="flex-1 min-w-0">' + body + (duty ? '<p class="text-[11px] text-slate-400 mt-1 num">' + duty.members.length + " on duty</p>" : "") + "</div>" +
        '<div class="flex gap-2 flex-shrink-0">' + actions + "</div>" +
      "</li>";
    }).join("");
  }

  $("roster-list").addEventListener("click", async (e) => {
    const edit = e.target.closest("[data-edit]");
    if (edit) return openDialog(edit.dataset.edit);

    const remove = e.target.closest("[data-remove]");
    if (!remove || !confirm("Remove this Sunday's duty team?")) return;
    remove.disabled = true;
    try {
      await api.post("duty-calendar", "deleteDuty", { id: remove.dataset.remove });
      toast("Duty team removed.", true);
      loadRoster();
    } catch (err) {
      remove.disabled = false;
      toast(err.message, false);
    }
  });

  $("roster-prev").addEventListener("click", () => { state.start = addDays(state.start, -7 * WEEKS); loadRoster(); });
  $("roster-next").addEventListener("click", () => { state.start = addDays(state.start, 7 * WEEKS); loadRoster(); });
  $("roster-today").addEventListener("click", () => { state.start = addDays(upcomingSunday, -7); loadRoster(); });

  // ---------- Duty dialog ----------

  const dialog = $("duty-dialog");

  function openDialog(date) {
    const duty = state.duties[date];
    state.editing = duty || { duty_date: date };
    $("duty-dialog-title").textContent = duty ? "Edit Sunday duty" : "Assign Sunday duty";
    $("duty-dialog-date").textContent = pretty(date, { weekday: "long", year: "numeric", month: "long" });
    $("duty-error").classList.add("hidden");
    $("duty-notes").value = duty?.notes || "";
    $("duty-search").value = "";

    $("duty-lead").innerHTML = '<option value="">Pick the lead…</option>' + state.employees.map((emp) =>
      '<option value="' + emp.id + '"' + (duty && duty.lead_employee_id === emp.id ? " selected" : "") + ">" + esc(emp.name) + " · " + esc(emp.role) + "</option>"
    ).join("");

    const onTeam = new Set((duty?.members || []).map((m) => m.id));
    $("duty-members").innerHTML = state.employees.map((emp) =>
      '<label class="flex items-center gap-3 px-3 py-2.5 cursor-pointer hover:bg-ink-50" data-search="' + esc((emp.name + " " + emp.role).toLowerCase()) + '">' +
        '<input type="checkbox" value="' + emp.id + '"' + (onTeam.has(emp.id) ? " checked" : "") + ' class="w-4 h-4 rounded border-line text-coolant focus:ring-frost">' +
        '<span class="flex-1 min-w-0"><span class="block text-sm text-ink truncate">' + esc(emp.name) + '</span><span class="block text-[11px] text-slate-500">' + esc(emp.role) + "</span></span>" +
      "</label>"
    ).join("");

    syncLead();
    dialog.showModal();
    $("duty-lead").focus();
  }

  // The lead is always on the team: tick and lock their checkbox.
  function syncLead() {
    const lead = $("duty-lead").value;
    $("duty-members").querySelectorAll("input").forEach((cb) => {
      const isLead = cb.value === lead;
      if (isLead) cb.checked = true;
      cb.disabled = isLead;
    });
    const n = $("duty-members").querySelectorAll("input:checked").length;
    $("duty-count").textContent = n + " selected";
  }

  $("duty-lead").addEventListener("change", syncLead);
  $("duty-members").addEventListener("change", syncLead);
  $("duty-search").addEventListener("input", (e) => {
    const q = e.target.value.trim().toLowerCase();
    $("duty-members").querySelectorAll("label").forEach((l) => { l.hidden = q !== "" && !l.dataset.search.includes(q); });
  });
  $("duty-cancel").addEventListener("click", () => dialog.close());

  $("duty-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const lead = Number($("duty-lead").value);
    const err = $("duty-error");
    if (!lead) {
      err.textContent = "Pick a lead.";
      err.classList.remove("hidden");
      return;
    }

    const save = $("duty-save");
    save.disabled = true;
    try {
      await api.post("duty-calendar", "saveDuty", {
        id: state.editing.id,
        body: {
          duty_date: state.editing.duty_date,
          lead_employee_id: lead,
          member_ids: Array.from($("duty-members").querySelectorAll("input:checked")).map((cb) => Number(cb.value)),
          notes: $("duty-notes").value,
        },
      });
      dialog.close();
      toast("Duty team saved.", true);
      loadRoster();
    } catch (ex) {
      err.textContent = ex.message;
      err.classList.remove("hidden");
    } finally {
      save.disabled = false;
    }
  });

  // ---------- Holidays ----------

  const yearSelect = $("holiday-year");
  const thisYear = Number(today.slice(0, 4));
  yearSelect.innerHTML = [thisYear - 1, thisYear, thisYear + 1].map((y) => '<option value="' + y + '"' + (y === thisYear ? " selected" : "") + ">" + y + "</option>").join("");

  async function loadHolidays(force) {
    const year = yearSelect.value;
    if (force) delete state.holidays[year];
    try {
      const rows = await holidaysFor(year);
      $("holiday-list").innerHTML = rows.length
        ? rows.map((h) =>
            '<li class="flex items-center gap-3 px-4 sm:px-5 py-3">' +
              '<span class="num text-xs font-semibold text-slate-600 w-24 flex-shrink-0">' + esc(pretty(h.holiday_date, { weekday: "short" })) + "</span>" +
              '<span class="flex-1 min-w-0"><span class="block text-sm text-ink truncate">' + esc(h.name) + "</span>" +
                '<span class="inline-block mt-0.5 px-2 py-0.5 rounded-full text-[11px] font-semibold ' + (TYPE_BADGE[h.type] || "") + '">' + esc(TYPE_LABEL[h.type] || h.type) + "</span></span>" +
              '<button type="button" data-holiday="' + h.id + '" aria-label="Delete ' + esc(h.name) + '" class="ui-btn w-10 h-10 inline-flex items-center justify-center rounded-lg text-slate-400 hover:text-negative hover:bg-red-50 focus:outline-none focus:ring-2 focus:ring-frost">' +
                '<svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" /></svg>' +
              "</button>" +
            "</li>"
          ).join("")
        : '<li class="px-5 py-8 text-center text-sm text-slate-500">No holidays for ' + esc(year) + ' yet. Use "Add fixed PH dates", then add Holy Week and other movable holidays.</li>';
    } catch (e) {
      $("holiday-list").innerHTML = '<li class="p-5 text-sm text-negative">' + esc(e.message) + "</li>";
    }
  }

  function holidaysChanged(year) {
    delete state.holidays[year];
    loadHolidays(true);
    loadRoster();
  }

  yearSelect.addEventListener("change", () => loadHolidays(false));

  $("seed-holidays").addEventListener("click", async (e) => {
    const btn = e.currentTarget;
    btn.disabled = true;
    try {
      const res = await api.post("duty-calendar", "seedHolidays", { body: { year: Number(yearSelect.value) } });
      toast(res.added ? res.added + " holidays added." : "All fixed dates are already listed.", true);
      holidaysChanged(yearSelect.value);
    } catch (err) {
      toast(err.message, false);
    } finally {
      btn.disabled = false;
    }
  });

  $("holiday-form").addEventListener("submit", async (e) => {
    e.preventDefault();
    const form = e.currentTarget;
    const data = Object.fromEntries(new FormData(form));
    if (!data.holiday_date || !data.name.trim()) return toast("Date and name are required.", false);
    try {
      await api.post("duty-calendar", "saveHoliday", { body: data });
      toast("Holiday added.", true);
      form.reset();
      const year = data.holiday_date.slice(0, 4);
      if (yearSelect.querySelector('option[value="' + year + '"]')) yearSelect.value = year;
      holidaysChanged(year);
    } catch (err) {
      toast(err.message, false);
    }
  });

  $("holiday-list").addEventListener("click", async (e) => {
    const btn = e.target.closest("[data-holiday]");
    if (!btn || !confirm("Delete this holiday? Payroll computed after this will pay that day at the normal rate.")) return;
    btn.disabled = true;
    try {
      await api.post("duty-calendar", "deleteHoliday", { id: btn.dataset.holiday });
      toast("Holiday deleted.", true);
      holidaysChanged(yearSelect.value);
    } catch (err) {
      btn.disabled = false;
      toast(err.message, false);
    }
  });

  // ---------- Setting ----------

  const toggle = $("unworked-toggle");

  function paintToggle(on) {
    toggle.setAttribute("aria-checked", String(on));
    toggle.classList.toggle("bg-coolant", on);
    toggle.classList.toggle("bg-slate-300", !on);
    toggle.firstElementChild.style.transform = on ? "translateX(20px)" : "";
  }

  toggle.addEventListener("click", async () => {
    const next = toggle.getAttribute("aria-checked") !== "true";
    toggle.disabled = true;
    try {
      const res = await api.post("duty-calendar", "saveSettings", { body: { pay_unworked_regular_holiday: next } });
      paintToggle(res.pay_unworked_regular_holiday);
      toast(next ? "Unworked regular holidays will be paid." : "Unworked regular holidays: no work, no pay.", true);
    } catch (err) {
      toast(err.message, false);
    } finally {
      toggle.disabled = false;
    }
  });

  // ---------- Boot ----------

  (async function boot() {
    try {
      const [emps, settings] = await Promise.all([
        api.get("employees", "index"),
        api.get("duty-calendar", "settings"),
      ]);
      state.employees = emps.filter((e) => e.status === "Active").sort((a, b) => a.name.localeCompare(b.name));
      paintToggle(settings.pay_unworked_regular_holiday);
    } catch (e) {
      toast(e.message, false);
    }
    loadRoster();
    loadHolidays(false);
  })();
})();
