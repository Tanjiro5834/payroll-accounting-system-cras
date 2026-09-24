(function () {
  "use strict";

  /* ---------- Drawer ---------- */
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

  /* ---------- Helpers ---------- */
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

  /* ---------- Mock computation ---------- */
  function computeMock(employeeId) {
    const all = [
      {
        id: 1,
        name: "Juan Dela Cruz",
        role: "Technician",
        freq: "Weekly",
        rate: 120,
        reg: 80,
        ot: 6,
        nd: 2,
        late: 0,
        ut: 0,
      },
      {
        id: 2,
        name: "Maria Santos",
        role: "Admin",
        freq: "Monthly",
        rate: 185,
        reg: 88,
        ot: 0,
        nd: 0,
        late: 15,
        ut: 10,
      },
      {
        id: 3,
        name: "Pedro Reyes",
        role: "Technician",
        freq: "Kinsenas",
        rate: 130,
        reg: 80,
        ot: 4,
        nd: 1,
        late: 5,
        ut: 0,
      },
      {
        id: 4,
        name: "Ana Villanueva",
        role: "Secretary",
        freq: "Monthly",
        rate: 145,
        reg: 88,
        ot: 2,
        nd: 0,
        late: 0,
        ut: 0,
      },
      {
        id: 5,
        name: "Carlos Mendoza",
        role: "Driver",
        freq: "Weekly",
        rate: 100,
        reg: 78,
        ot: 8,
        nd: 3,
        late: 10,
        ut: 5,
      },
    ];
    let list = all;
    if (employeeId)
      list = all.filter(function (e) {
        return String(e.id) === String(employeeId);
      });

    return list.map(function (e) {
      const gross = e.reg * e.rate + e.ot * e.rate * 1.25 + e.nd * e.rate * 0.1;
      const lateDed = (e.late / 60) * e.rate;
      const utDed = (e.ut / 60) * e.rate;
      const sss = Math.min(gross * 0.045, 900);
      const ph = Math.min(gross * 0.02, 900);
      const pi = Math.min(gross * 0.02, 200);
      const tax = gross > 20833 ? (gross - 20833) * 0.15 : 0;
      const ded = lateDed + utDed + sss + ph + pi + tax;
      return Object.assign({}, e, {
        gross: gross,
        deductions: ded,
        net: gross - ded,
      });
    });
  }

  const resultsSection = document.getElementById("results-section");
  const emptyState = document.getElementById("empty-state");
  const tbody = document.getElementById("payroll-tbody");
  const computeBtn = document.getElementById("compute-btn");
  const computeLabel = document.getElementById("compute-label");
  const exportBtn = document.getElementById("export-csv");

  let currentResults = [];

  computeBtn.addEventListener("click", function () {
    computeLabel.textContent = "Computing…";
    computeBtn.disabled = true;

    const empId = document.getElementById("filter-employee").value;
    const start = document.getElementById("filter-start").value;
    const end = document.getElementById("filter-end").value;
    const freq = document.getElementById("filter-freq").value;

    if (!start || !end) {
      showToast("Please select both period start and end dates.", "error");
      computeLabel.textContent = "Compute Payroll";
      computeBtn.disabled = false;
      return;
    }
    if (new Date(start) > new Date(end)) {
      showToast("Period start must be before end date.", "error");
      computeLabel.textContent = "Compute Payroll";
      computeBtn.disabled = false;
      return;
    }

    // Try real API, fall back to mock
    fetch(
      "/api/payroll?start=" +
        encodeURIComponent(start) +
        "&end=" +
        encodeURIComponent(end) +
        "&employee_id=" +
        encodeURIComponent(empId) +
        "&frequency=" +
        encodeURIComponent(freq),
    )
      .then(function (r) {
        if (!r.ok) throw new Error("API");
        return r.json();
      })
      .then(function (data) {
        currentResults = data.rows || data;
        renderResults(currentResults, start, end);
      })
      .catch(function () {
        setTimeout(function () {
          currentResults = computeMock(empId);
          if (freq)
            currentResults = currentResults.filter(function (r) {
              return r.freq.toLowerCase() === freq.toLowerCase();
            });
          renderResults(currentResults, start, end);
        }, 400);
      });
  });

  function renderResults(rows, start, end) {
    tbody.innerHTML = "";
    if (!rows.length) {
      showToast("No data for the selected period.", "error");
      resultsSection.classList.add("hidden");
      emptyState.classList.remove("hidden");
      exportBtn.disabled = true;
      computeLabel.textContent = "Compute Payroll";
      computeBtn.disabled = false;
      return;
    }

    rows.forEach(function (r) {
      const tr = document.createElement("tr");
      tr.className = "hover:bg-slate-50 transition-colors";
      tr.innerHTML =
        '<td class="px-4 py-3 whitespace-nowrap">' +
        '<p class="text-sm font-semibold text-slate-900">' +
        escapeHtml(r.name) +
        "</p>" +
        '<p class="text-[11px] text-slate-400">' +
        escapeHtml(r.role) +
        " · " +
        escapeHtml(r.freq) +
        "</p>" +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' +
        num(r.reg) +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' +
        num(r.ot) +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' +
        num(r.nd) +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-xs ' +
        (r.late > 0 ? "text-amber-700" : "text-slate-400") +
        ' whitespace-nowrap">' +
        r.late +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-xs ' +
        (r.ut > 0 ? "text-amber-700" : "text-slate-400") +
        ' whitespace-nowrap">' +
        r.ut +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' +
        peso(r.rate) +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-xs text-slate-900 whitespace-nowrap">' +
        peso(r.gross) +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-xs text-red-700 whitespace-nowrap">-' +
        peso(r.deductions) +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-emerald-800 whitespace-nowrap">' +
        peso(r.net) +
        "</td>";
      tbody.appendChild(tr);
    });

    // Totals
    const totals = rows.reduce(
      function (acc, r) {
        acc.gross += r.gross;
        acc.ded += r.deductions;
        acc.net += r.net;
        return acc;
      },
      { gross: 0, ded: 0, net: 0 },
    );

    const totalTr = document.createElement("tr");
    totalTr.className = "bg-slate-50 border-t-2 border-slate-200";
    totalTr.innerHTML =
      '<td class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-700" colspan="7">Total (' +
      rows.length +
      " employees)</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-slate-900 whitespace-nowrap">' +
      peso(totals.gross) +
      "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-red-700 whitespace-nowrap">-' +
      peso(totals.ded) +
      "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-emerald-800 whitespace-nowrap">' +
      peso(totals.net) +
      "</td>";
    tbody.appendChild(totalTr);

    document.getElementById("results-period").textContent =
      start + " \u2192 " + end;
    document.getElementById("results-count").textContent =
      rows.length + " row" + (rows.length === 1 ? "" : "s");

    resultsSection.classList.remove("hidden");
    emptyState.classList.add("hidden");
    exportBtn.disabled = false;
    computeLabel.textContent = "Compute Payroll";
    computeBtn.disabled = false;
    showToast(
      "Payroll computed for " +
        rows.length +
        " employee" +
        (rows.length === 1 ? "" : "s") +
        ".",
      "success",
    );
  }

  /* ---------- CSV export ---------- */
  exportBtn.addEventListener("click", function () {
    if (!currentResults.length) return;
    const headers = [
      "Employee",
      "Role",
      "Frequency",
      "Regular Hours",
      "OT Hours",
      "ND Hours",
      "Late (min)",
      "Undertime (min)",
      "Rate",
      "Gross Pay",
      "Deductions",
      "Net Pay",
    ];
    const lines = [headers.join(",")];
    currentResults.forEach(function (r) {
      lines.push(
        [
          '"' + r.name.replace(/"/g, '""') + '"',
          '"' + r.role + '"',
          r.freq,
          num(r.reg),
          num(r.ot),
          num(r.nd),
          r.late,
          r.ut,
          num(r.rate),
          num(r.gross),
          num(r.deductions),
          num(r.net),
        ].join(","),
      );
    });
    const csv = lines.join("\n");
    const blob = new Blob([csv], { type: "text/csv;charset=utf-8;" });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download =
      "payroll_" +
      document.getElementById("filter-start").value +
      "_" +
      document.getElementById("filter-end").value +
      ".csv";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast("CSV downloaded.", "success");
  });
})();
