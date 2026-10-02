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

  const toastContainer = document.getElementById("toast-container");
  function showToast(msg, type) {
    const ok = type === "success";
    const t = document.createElement("div");
    t.className =
      "pointer-events-auto w-full max-w-sm rounded-lg shadow-lg px-4 py-3 text-sm font-medium " +
      (ok ? "bg-emerald-700 text-white" : "bg-red-600 text-white");
    t.textContent = msg;
    toastContainer.appendChild(t);
    setTimeout(function () {
      t.style.opacity = "0";
      setTimeout(function () {
        if (t.parentNode) t.parentNode.removeChild(t);
      }, 200);
    }, 3000);
  }

  const yearSelect = document.getElementById("year-select");
  const currentYear = new Date().getFullYear();
  for (let y = currentYear; y >= currentYear - 5; y--) {
    const opt = document.createElement("option");
    opt.value = y;
    opt.textContent = y;
    if (y === currentYear) opt.selected = true;
    yearSelect.appendChild(opt);
  }

  const tbody = document.getElementById("tmb-tbody");
  const COLUMNS = 9;

  const STATUS_BADGE = {
    draft: "bg-slate-200 text-slate-700",
    approved: "bg-amber-100 text-amber-800",
    paid: "bg-emerald-100 text-emerald-800",
  };

  const NEXT_ACTION = {
    draft: { action: "approve", label: "Approve", confirm: null },
    approved: {
      action: "markAsPaid",
      label: "Mark paid",
      confirm: "Mark this 13th month pay as paid? This can't be undone.",
    },
  };

  function hours(n) {
    return Number(n).toLocaleString("en-PH", { maximumFractionDigits: 2 });
  }

  function messageRow(text) {
    return (
      '<tr><td colspan="' + COLUMNS + '" class="px-4 py-16 text-center text-sm text-slate-500">' +
      escapeHtml(text) +
      "</td></tr>"
    );
  }

  function actionCell(r) {
    const next = NEXT_ACTION[r.status];
    if (!next) {
      return '<span class="font-mono text-[11px] text-slate-400">' + escapeHtml((r.paid_at || "").split(" ")[0]) + "</span>";
    }
    return (
      '<button type="button" data-action="' + next.action + '" data-id="' + Number(r.id) + '"' +
      ' class="px-3 py-1.5 rounded-md border border-line text-xs font-semibold text-coolant hover:bg-coolant-tint focus:outline-none focus:ring-2 focus:ring-frost disabled:opacity-50 transition-colors">' +
      next.label +
      "</button>"
    );
  }

  function recordRow(r) {
    const tr = document.createElement("tr");
    tr.className = "hover:bg-slate-50 transition-colors";
    tr.innerHTML =
      '<td class="px-4 py-3 text-sm font-semibold text-slate-900 whitespace-nowrap">' + escapeHtml(r.full_name) + "</td>" +
      '<td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">' + escapeHtml(r.role) + "</td>" +
      '<td class="px-4 py-3 font-mono text-xs text-slate-600 whitespace-nowrap">' + escapeHtml(r.date_hired || "—") + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' + Number(r.months_worked) + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' + hours(r.total_regular_hours) + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' + peso(r.total_basic_salary) + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-emerald-800 whitespace-nowrap">' + peso(r.thirteenth_month_pay) + "</td>" +
      '<td class="px-4 py-3"><span class="inline-block px-2 py-0.5 rounded font-mono text-[11px] font-semibold uppercase ' +
        (STATUS_BADGE[r.status] || STATUS_BADGE.draft) + '">' + escapeHtml(r.status) + "</span></td>" +
      '<td class="px-4 py-3 text-right whitespace-nowrap">' + actionCell(r) + "</td>";
    return tr;
  }

  function totalsRow(rows, totals) {
    const totalHours = rows.reduce(function (sum, r) { return sum + Number(r.total_regular_hours); }, 0);
    const tr = document.createElement("tr");
    tr.className = "bg-slate-50 border-t-2 border-slate-200";
    tr.innerHTML =
      '<td class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-700 whitespace-nowrap" colspan="4">Total (' + totals.employees + ")</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-slate-900 whitespace-nowrap">' + hours(totalHours) + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-slate-900 whitespace-nowrap">' + peso(totals.total_basic) + "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-emerald-800 whitespace-nowrap">' + peso(totals.total_payout) + "</td>" +
      '<td colspan="2"></td>';
    return tr;
  }

  function render(report) {
    document.getElementById("table-title").textContent = "13th Month Pay — " + report.year;
    document.getElementById("compute-label").textContent = "Compute " + report.year;

    const t = report.totals;
    document.getElementById("table-sub").textContent = report.rows.length
      ? "Paid " + peso(t.paid) + " · Unpaid " + peso(t.unpaid) + " · ordered by employee name"
      : "Ordered by employee name";

    if (!report.rows.length) {
      tbody.innerHTML = messageRow("Nothing computed for " + report.year + " yet. Click Compute to generate it from approved payroll.");
      return;
    }

    tbody.replaceChildren();
    report.rows.forEach(function (r) {
      tbody.appendChild(recordRow(r));
    });
    tbody.appendChild(totalsRow(report.rows, t));
  }

  async function load() {
    tbody.innerHTML = messageRow("Loading…");
    try {
      render(await api.get("thirteenth-month", "generateReport", { params: { year: yearSelect.value } }));
    } catch (error) {
      tbody.innerHTML = messageRow("Couldn't load the report: " + error.message);
    }
  }

  yearSelect.addEventListener("change", load);
  load();

  const computeBtn = document.getElementById("compute-btn");

  computeBtn.addEventListener("click", async function () {
    const year = yearSelect.value;
    const ok = confirm(
      "Compute 13th month pay for " + year + " from approved payroll?\n\n" +
      "Draft records are recalculated. Approved and paid records are left untouched."
    );
    if (!ok) return;

    computeBtn.disabled = true;
    try {
      const result = await api.post("thirteenth-month", "computeAll", { body: { year: Number(year) } });
      showToast("Computed " + result.computed + " employee" + (result.computed === 1 ? "" : "s") + ".", "success");
      await load();
    } catch (error) {
      showToast(error.message, "error");
    } finally {
      computeBtn.disabled = false;
    }
  });

  tbody.addEventListener("click", async function (e) {
    const button = e.target.closest("button[data-action]");
    if (!button) return;

    const action = button.dataset.action;
    const step = Object.values(NEXT_ACTION).find(function (s) { return s.action === action; });
    if (step.confirm && !confirm(step.confirm)) return;

    button.disabled = true;
    try {
      await api.post("thirteenth-month", action, { id: button.dataset.id });
      showToast(action === "approve" ? "Approved." : "Marked as paid.", "success");
      await load();
    } catch (error) {
      showToast(error.message, "error");
      button.disabled = false;
    }
  });

  document.getElementById("export-csv").addEventListener("click", async function () {
    const year = yearSelect.value;
    try {
      const res = await fetch(
        "index.php?" + new URLSearchParams({ page: "thirteenth-month", action: "export", year: year }),
        { credentials: "same-origin", headers: { "X-Requested-With": "XMLHttpRequest" } }
      );
      if (!res.ok) {
        const data = await res.json().catch(function () { return {}; });
        throw new Error(data.error || "Export failed (" + res.status + ")");
      }

      const url = URL.createObjectURL(await res.blob());
      const a = document.createElement("a");
      a.href = url;
      a.download = "thirteenth_month_" + year + ".csv";
      document.body.appendChild(a);
      a.click();
      a.remove();
      URL.revokeObjectURL(url);
      showToast("CSV downloaded.", "success");
    } catch (error) {
      showToast(error.message, "error");
    }
  });

  document.getElementById("export-pdf").addEventListener("click", function () {
    window.print();
  });
})();
