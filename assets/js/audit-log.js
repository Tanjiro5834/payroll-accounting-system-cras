(function () {
  "use strict";

  const PAGE_SIZE = 50;

  const tbody = document.getElementById("audit-tbody");
  const employeeSelect = document.getElementById("filter-employee");
  const startInput = document.getElementById("filter-start");
  const endInput = document.getElementById("filter-end");
  const actionSelect = document.getElementById("filter-action");
  const loadMoreBtn = document.getElementById("load-more");
  const loadedCount = document.getElementById("loaded-count");

  const BADGES = {
    PUNCH: "bg-emerald-100 text-emerald-800",
    LOGIN: "bg-blue-100 text-blue-800",
    LOGOUT: "bg-slate-200 text-slate-700",
    PAYROLL: "bg-purple-100 text-purple-800",
    THIRTEENTH: "bg-purple-100 text-purple-800",
    EMPLOYEE: "bg-amber-100 text-amber-800",
    DEDUCTION: "bg-orange-100 text-orange-800",
    SETTING: "bg-sky-100 text-sky-800",
    PUNCH_REVERSE: "bg-red-100 text-red-800",
  };

  const MONTHS = ["January", "February", "March", "April", "May", "June",
    "July", "August", "September", "October", "November", "December"];

  const PUNCH_LABELS = { AM_IN: "Time In", PM_OUT: "Time Out", AM_OUT: "Lunch Out", PM_IN: "Lunch In" };

  let rows = [];
  let shown = 0;

  function esc(s) {
    const d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function ymd(d) {
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
  }

  function fmtTs(ts) {
    const [date, time] = String(ts || "").split(" ");
    const [y, mo, d] = (date || "").split("-").map(Number);
    if (!y || !mo || !d) return ts || "";
    let out = MONTHS[mo - 1] + " " + d + ", " + y;
    if (time) {
      const [h, m] = time.split(":").map(Number);
      out += " " + (h % 12 || 12) + ":" + String(m).padStart(2, "0") + " " + (h < 12 ? "AM" : "PM");
    }
    return out;
  }

  function parseDetails(raw) {
    if (raw == null || raw === "") return null;
    if (typeof raw === "object") return raw;
    try { return JSON.parse(raw); } catch (e) { return raw; }
  }

  function summary(action, d) {
    if (d == null) return "—";
    if (typeof d !== "object") return String(d);
    if (action === "PUNCH" && d.punch_type) return (PUNCH_LABELS[d.punch_type] || d.punch_type) + " recorded";
    if (action === "LOGIN" && d.username) return "Logged in as " + d.username;
    if (d.changes && typeof d.changes === "object") return "Changed: " + Object.keys(d.changes).join(", ");
    return Object.keys(d).slice(0, 4).map(function (k) {
      const v = d[k];
      return k + ": " + (typeof v === "object" ? JSON.stringify(v) : v);
    }).join(" · ");
  }

  function badge(a) {
    const key = BADGES[a] ? a : String(a).split("_")[0];
    return '<span class="inline-block px-2 py-0.5 rounded-full text-[10px] font-bold font-mono uppercase tracking-wider ' +
      (BADGES[key] || "bg-slate-100 text-slate-700") + '">' + esc(a) + "</span>";
  }

  function who(r) {
    if (r.full_name) return r.full_name;
    if (r.actor_username) return r.actor_username;
    return r.employee_id ? "#" + r.employee_id : "System";
  }

  function filtered() {
    const a = actionSelect.value;
    return a ? rows.filter(function (r) { return r.action_type === a; }) : rows;
  }

  function message(text) {
    tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-10 text-center text-sm text-slate-400">' + esc(text) + "</td></tr>";
    loadMoreBtn.classList.add("hidden");
    loadedCount.textContent = "";
  }

  function appendRow(r) {
    const details = parseDetails(r.action_details);
    const by = r.actor_username && r.actor_username !== r.full_name ? " (by " + r.actor_username + ")" : "";

    const tr = document.createElement("tr");
    tr.className = "hover:bg-slate-50 transition-colors cursor-pointer";
    tr.innerHTML =
      '<td class="px-4 py-3 text-slate-400"><svg class="w-4 h-4 transition-transform" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m8.25 4.5 7.5 7.5-7.5 7.5" /></svg></td>' +
      '<td class="px-4 py-3 font-mono text-xs text-slate-700 whitespace-nowrap">' + esc(fmtTs(r.performed_at)) + "</td>" +
      '<td class="px-4 py-3 text-sm text-slate-900 whitespace-nowrap">' + esc(who(r)) + '<span class="text-xs text-slate-400">' + esc(by) + "</span></td>" +
      '<td class="px-4 py-3 whitespace-nowrap">' + badge(r.action_type) + "</td>" +
      '<td class="px-4 py-3 text-xs text-slate-600 max-w-md truncate">' + esc(summary(r.action_type, details)) + "</td>" +
      '<td class="px-4 py-3 font-mono text-xs text-slate-500 whitespace-nowrap">' + esc(r.ip_address || "—") + "</td>";

    tr.addEventListener("click", function () {
      const next = tr.nextElementSibling;
      if (next && next.dataset.detailsFor === "1") {
        next.remove();
        tr.querySelector("svg").style.transform = "";
        return;
      }
      const detailTr = document.createElement("tr");
      detailTr.dataset.detailsFor = "1";
      detailTr.innerHTML =
        '<td colspan="6" class="bg-slate-50 px-4 py-4">' +
        '<p class="text-[10px] font-semibold uppercase tracking-wider text-slate-500 mb-1.5">Details</p>' +
        '<pre class="font-mono text-[11px] text-slate-700 bg-white border border-slate-200 rounded-md p-3 overflow-x-auto whitespace-pre-wrap">' +
        esc(typeof details === "object" && details !== null ? JSON.stringify(details, null, 2) : details || "—") +
        "</pre></td>";
      tr.after(detailTr);
      tr.querySelector("svg").style.transform = "rotate(90deg)";
    });

    tbody.appendChild(tr);
  }

  function renderMore() {
    const list = filtered();
    list.slice(shown, shown + PAGE_SIZE).forEach(appendRow);
    shown = Math.min(shown + PAGE_SIZE, list.length);
    loadedCount.textContent = "Showing " + shown + " of " + list.length + " entries";
    loadMoreBtn.classList.toggle("hidden", shown >= list.length);
  }

  function render() {
    tbody.innerHTML = "";
    shown = 0;
    if (filtered().length === 0) return message("No activity in this range.");
    renderMore();
  }

  function fillActions() {
    const current = actionSelect.value;
    const types = Array.from(new Set(rows.map(function (r) { return r.action_type; }))).sort();
    actionSelect.length = 1;
    types.forEach(function (t) {
      const opt = document.createElement("option");
      opt.value = t;
      opt.textContent = t;
      actionSelect.appendChild(opt);
    });
    actionSelect.value = types.indexOf(current) !== -1 ? current : "";
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
      const data = await api.get("audit-log", "auditLog", { params: params });
      rows = Array.isArray(data) ? data : [];
      fillActions();
      render();
    } catch (err) {
      message(err.message || "Failed to load audit trail.");
    }
  }

  async function loadEmployees() {
    try {
      const list = await api.get("employees", "index");
      (Array.isArray(list) ? list : []).forEach(function (e) {
        const name = e.name || e.full_name;
        if (e.id == null || !name) return;
        const opt = document.createElement("option");
        opt.value = String(e.id);
        opt.textContent = name;
        employeeSelect.appendChild(opt);
      });
    } catch (err) {
      // dropdown stays "All employees"
    }
  }

  const today = new Date();
  const weekAgo = new Date(today);
  weekAgo.setDate(today.getDate() - 6);
  startInput.value = ymd(weekAgo);
  endInput.value = ymd(today);

  [employeeSelect, startInput, endInput].forEach(function (el) { el.addEventListener("change", load); });
  actionSelect.addEventListener("change", render);
  loadMoreBtn.addEventListener("click", renderMore);

  loadEmployees().then(load);
})();