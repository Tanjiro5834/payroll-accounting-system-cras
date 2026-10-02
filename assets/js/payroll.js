(function () {
  "use strict";

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

  function escapeHtml(s) {
    const d = document.createElement("div");
    d.textContent = String(s);
    return d.innerHTML;
  }
  function peso(n) {
    return (
      "\u20B1" +
      Number(n).toLocaleString("en-PH", {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
      })
    );
  }
  function num(n, d) {
    return Number(n).toFixed(d == null ? 2 : d);
  }

  const toastContainer = document.getElementById("toast-container");
  function showToast(message, type) {
    const ok = type === "success";
    const t = document.createElement("div");
    t.className =
      "pointer-events-auto w-full max-w-sm rounded-lg shadow-lg px-4 py-3 flex items-start gap-3 text-sm font-medium " +
      (ok ? "bg-emerald-700 text-white" : "bg-red-600 text-white");
    t.innerHTML = '<span class="flex-1">' + escapeHtml(message) + "</span>";
    toastContainer.appendChild(t);
    setTimeout(function () {
      t.style.opacity = "0";
      setTimeout(function () {
        if (t.parentNode) t.parentNode.removeChild(t);
      }, 200);
    }, 3000);
  }

  const resultsSection = document.getElementById("results-section");
  const emptyState = document.getElementById("empty-state");
  const tbody = document.getElementById("payroll-tbody");
  const computeBtn = document.getElementById("compute-btn");
  const computeLabel = document.getElementById("compute-label");
  const exportBtn = document.getElementById("export-csv");
  const employeeSelect = document.getElementById("filter-employee");
  const startInput = document.getElementById("filter-start");
  const endInput = document.getElementById("filter-end");
  const freqSelect = document.getElementById("filter-freq");

  const STATUS_BADGE = {
    draft: "bg-slate-200 text-slate-700",
    computed: "bg-sky-100 text-sky-800",
    approved: "bg-amber-100 text-amber-800",
    paid: "bg-emerald-100 text-emerald-800",
  };

  const NEXT_ACTION = {
    computed: { action: "approve", label: "Approve", done: "Approved.", confirm: null },
    approved: {
      action: "markAsPaid",
      label: "Mark paid",
      done: "Marked as paid.",
      confirm: "Mark this payroll as paid? This can't be undone.",
    },
  };

  function isoDate(d) {
    return d.getFullYear() + "-" + String(d.getMonth() + 1).padStart(2, "0") + "-" + String(d.getDate()).padStart(2, "0");
  }

  function setDefaultPeriod() {
    const today = new Date();
    const monday = new Date(today);
    monday.setDate(today.getDate() - ((today.getDay() + 6) % 7));
    const saturday = new Date(monday);
    saturday.setDate(monday.getDate() + 5);
    startInput.value = isoDate(monday);
    endInput.value = isoDate(saturday);
  }

  function filters() {
    return {
      start: startInput.value,
      end: endInput.value,
      employee_id: employeeSelect.value,
      frequency: freqSelect.value,
    };
  }

  function validPeriod(f) {
    if (!f.start || !f.end) {
      showToast("Please select both period start and end dates.", "error");
      return false;
    }
    if (f.start > f.end) {
      showToast("Period start must be on or before the end date.", "error");
      return false;
    }
    return true;
  }

  async function loadEmployees() {
    try {
      const employees = await api.get("employees", "index");
      employees
        .filter(function (e) { return e.status === "Active"; })
        .forEach(function (e) {
          const opt = document.createElement("option");
          opt.value = e.id;
          opt.textContent = e.name;
          employeeSelect.appendChild(opt);
        });
    } catch (error) {
      showToast("Couldn't load employees: " + error.message, "error");
    }
  }

  function minutesCell(n) {
    return (
      '<td class="px-4 py-3 text-right font-mono text-xs ' +
      (Number(n) > 0 ? "text-amber-700" : "text-slate-400") +
      ' whitespace-nowrap">' + Number(n) + "</td>"
    );
  }

  function actionCell(r) {
    const next = NEXT_ACTION[r.status];
    if (!next) {
      return r.paid_at ? '<span class="font-mono text-[11px] text-slate-400">' + escapeHtml(r.paid_at.split(" ")[0]) + "</span>" : "";
    }
    return (
      '<button type="button" data-action="' + next.action + '" data-id="' + Number(r.id) + '"' +
      ' class="px-3 py-1.5 rounded-md border border-line text-xs font-semibold text-coolant hover:bg-coolant-tint focus:outline-none focus:ring-2 focus:ring-frost disabled:opacity-50 transition-colors">' +
      next.label + "</button>"
    );
  }

  function payrollRow(r) {
    const tr = document.createElement("tr");
    tr.className = "hover:bg-slate-50 transition-colors";
    tr.innerHTML =
      '<td class="px-4 py-3 whitespace-nowrap">' +
        '<p class="text-sm font-semibold text-slate-900">' + escapeHtml(r.full_name) + "</p>" +
        '<p class="text-[11px] text-slate-400">' + escapeHtml(r.employee_role) + " · " + escapeHtml(r.pay_frequency) + "</p>" +
      "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' + num(r.total_regular_hours) + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' + num(r.total_overtime_hours) + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' + num(r.total_night_diff_hours) + "</td>" +
      minutesCell(r.total_late_minutes) +
      minutesCell(r.total_undertime_minutes) +
      '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' + peso(r.hourly_rate) + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-xs text-slate-900 whitespace-nowrap">' + peso(r.gross_pay) + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-xs text-red-700 whitespace-nowrap">-' + peso(r.total_deductions) + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-emerald-800 whitespace-nowrap">' + peso(r.net_pay) + "</td>" +
      '<td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded font-mono text-[11px] font-semibold uppercase ' +
        (STATUS_BADGE[r.status] || STATUS_BADGE.draft) + '">' + escapeHtml(r.status) + "</span></td>" +
      '<td class="px-4 py-3 text-right whitespace-nowrap">' + actionCell(r) + "</td>";
    return tr;
  }

  function totalsRow(rows) {
    const sum = function (key) {
      return rows.reduce(function (acc, r) { return acc + Number(r[key]); }, 0);
    };
    const tr = document.createElement("tr");
    tr.className = "bg-slate-50 border-t-2 border-slate-200";
    tr.innerHTML =
      '<td class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-700" colspan="7">Total (' +
        rows.length + " employee" + (rows.length === 1 ? "" : "s") + ")</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-slate-900 whitespace-nowrap">' + peso(sum("gross_pay")) + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-red-700 whitespace-nowrap">-' + peso(sum("total_deductions")) + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-emerald-800 whitespace-nowrap">' + peso(sum("net_pay")) + "</td>" +
      '<td colspan="2"></td>';
    return tr;
  }

  function renderResults(rows, f) {
    tbody.replaceChildren();
    const hasRows = rows.length > 0;
    resultsSection.classList.toggle("hidden", !hasRows);
    emptyState.classList.toggle("hidden", hasRows);
    exportBtn.disabled = !hasRows;
    if (!hasRows) return;

    rows.forEach(function (r) {
      tbody.appendChild(payrollRow(r));
    });
    tbody.appendChild(totalsRow(rows));

    document.getElementById("results-period").textContent = f.start + " → " + f.end;
    document.getElementById("results-count").textContent = rows.length + " row" + (rows.length === 1 ? "" : "s");
  }

  async function loadSaved() {
    const f = filters();
    if (!f.start || !f.end || f.start > f.end) return;
    try {
      renderResults(await api.get("payroll", "index", { params: f }), f);
    } catch (error) {
      showToast(error.message, "error");
    }
  }

  computeBtn.addEventListener("click", async function () {
    const f = filters();
    if (!validPeriod(f)) return;

    computeLabel.textContent = "Computing…";
    computeBtn.disabled = true;
    try {
      const rows = await api.post("payroll", "compute", { body: f });
      renderResults(rows, f);
      showToast("Payroll computed for " + rows.length + " employee" + (rows.length === 1 ? "" : "s") + ".", "success");
    } catch (error) {
      showToast(error.message, "error");
    } finally {
      computeLabel.textContent = "Compute Payroll";
      computeBtn.disabled = false;
    }
  });

  [employeeSelect, startInput, endInput, freqSelect].forEach(function (el) {
    el.addEventListener("change", loadSaved);
  });

  tbody.addEventListener("click", async function (e) {
    const button = e.target.closest("button[data-action]");
    if (!button) return;

    const step = Object.values(NEXT_ACTION).find(function (s) { return s.action === button.dataset.action; });
    if (step.confirm && !confirm(step.confirm)) return;

    button.disabled = true;
    try {
      await api.post("payroll", step.action, { id: button.dataset.id });
      showToast(step.done, "success");
      await loadSaved();
    } catch (error) {
      showToast(error.message, "error");
      button.disabled = false;
    }
  });

  exportBtn.addEventListener("click", async function () {
    const f = filters();
    try {
      const res = await fetch(
        "index.php?" + new URLSearchParams(Object.assign({ page: "payroll", action: "export" }, f)),
        { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } }
      );
      if (!res.ok) {
        const data = await res.json().catch(function () { return {}; });
        throw new Error(data.error || "Export failed (" + res.status + ")");
      }

      const url = URL.createObjectURL(await res.blob());
      const a = document.createElement("a");
      a.href = url;
      a.download = "payroll_" + f.start + "_" + f.end + ".csv";
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      showToast("CSV downloaded.", "success");
    } catch (error) {
      showToast(error.message, "error");
    }
  });

  setDefaultPeriod();
  loadEmployees();
  loadSaved();
})();
