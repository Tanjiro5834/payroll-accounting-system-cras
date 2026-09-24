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

  /* Year dropdown */
  const yearSelect = document.getElementById("year-select");
  const currentYear = new Date().getFullYear();
  for (let y = currentYear; y >= currentYear - 5; y--) {
    const opt = document.createElement("option");
    opt.value = y;
    opt.textContent = y;
    if (y === currentYear) opt.selected = true;
    yearSelect.appendChild(opt);
  }

  /* Mock data — replaced by PHP */
  const MOCK = {
    2026: [
      {
        name: "Ana Villanueva",
        role: "Secretary",
        hired: "2022-06-01",
        months: 12,
        regHours: 2288,
        salary: 331760,
      },
      {
        name: "Carlos Mendoza",
        role: "Driver",
        hired: "2023-01-10",
        months: 12,
        regHours: 2270,
        salary: 227000,
      },
      {
        name: "Juan Dela Cruz",
        role: "Technician",
        hired: "2023-01-15",
        months: 12,
        regHours: 2280,
        salary: 273600,
      },
      {
        name: "Maria Santos",
        role: "Admin",
        hired: "2022-06-01",
        months: 12,
        regHours: 2288,
        salary: 331760,
      },
      {
        name: "Pedro Reyes",
        role: "Technician",
        hired: "2023-03-20",
        months: 12,
        regHours: 2280,
        salary: 296400,
      },
    ],
  };

  function render(year) {
    const rows = MOCK[year] || [];
    const tbody = document.getElementById("tmb-tbody");
    tbody.innerHTML = "";

    if (!rows.length) {
      tbody.innerHTML =
        '<tr><td colspan="7" class="px-4 py-16 text-center text-sm text-slate-500">No data for ' +
        year +
        ".</td></tr>";
      return;
    }

    let totalSalary = 0,
      total13th = 0,
      totalHours = 0;

    rows.forEach(function (r) {
      const tmb = r.salary / 12;
      totalSalary += r.salary;
      total13th += tmb;
      totalHours += r.regHours;
      const tr = document.createElement("tr");
      tr.className = "hover:bg-slate-50 transition-colors";
      tr.innerHTML =
        '<td class="px-4 py-3 text-sm font-semibold text-slate-900 whitespace-nowrap">' +
        escapeHtml(r.name) +
        "</td>" +
        '<td class="px-4 py-3 text-xs text-slate-600 whitespace-nowrap">' +
        escapeHtml(r.role) +
        "</td>" +
        '<td class="px-4 py-3 font-mono text-xs text-slate-600 whitespace-nowrap">' +
        escapeHtml(r.hired) +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' +
        r.months +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' +
        r.regHours.toLocaleString("en-PH") +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-xs text-slate-700 whitespace-nowrap">' +
        peso(r.salary) +
        "</td>" +
        '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-emerald-800 whitespace-nowrap">' +
        peso(tmb) +
        "</td>";
      tbody.appendChild(tr);
    });

    const tr = document.createElement("tr");
    tr.className = "bg-slate-50 border-t-2 border-slate-200";
    tr.innerHTML =
      '<td class="px-4 py-3 text-xs font-bold uppercase tracking-wider text-slate-700 whitespace-nowrap" colspan="4">Total (' +
      rows.length +
      ")</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-slate-900 whitespace-nowrap">' +
      totalHours.toLocaleString("en-PH") +
      "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-slate-900 whitespace-nowrap">' +
      peso(totalSalary) +
      "</td>" +
      '<td class="px-4 py-3 text-right font-mono text-sm font-bold text-emerald-800 whitespace-nowrap">' +
      peso(total13th) +
      "</td>";
    tbody.appendChild(tr);

    document.getElementById("table-title").textContent =
      "13th Month Pay — " + year;
  }

  yearSelect.addEventListener("change", function () {
    render(this.value);
  });
  render(currentYear);

  /* CSV export */
  document.getElementById("export-csv").addEventListener("click", function () {
    const year = yearSelect.value;
    const rows = MOCK[year] || [];
    if (!rows.length) {
      showToast("No data to export.", "error");
      return;
    }
    const lines = [
      [
        "Employee",
        "Role",
        "Date Hired",
        "Months Worked",
        "Total Reg. Hrs",
        "Basic Salary",
        "13th Month Pay",
      ].join(","),
    ];
    rows.forEach(function (r) {
      lines.push(
        [
          '"' + r.name + '"',
          '"' + r.role + '"',
          r.hired,
          r.months,
          r.regHours,
          r.salary.toFixed(2),
          (r.salary / 12).toFixed(2),
        ].join(","),
      );
    });
    const blob = new Blob([lines.join("\n")], {
      type: "text/csv;charset=utf-8;",
    });
    const url = URL.createObjectURL(blob);
    const a = document.createElement("a");
    a.href = url;
    a.download = "thirteenth_month_" + year + ".csv";
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    showToast("CSV downloaded.", "success");
  });

  document.getElementById("export-pdf").addEventListener("click", function () {
    window.print();
  });
})();
