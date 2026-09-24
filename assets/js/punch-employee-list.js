(function () {
  "use strict";

  /* ---------- Live Clock ---------- */
  function updateClock() {
    const now = new Date();
    const timeEl = document.getElementById("live-time");
    const dateEl = document.getElementById("live-date");

    let hours = now.getHours();
    const minutes = String(now.getMinutes()).padStart(2, "0");
    const seconds = String(now.getSeconds()).padStart(2, "0");
    const ampm = hours >= 12 ? "PM" : "AM";
    hours = hours % 12 || 12;
    const hh = String(hours).padStart(2, "0");

    timeEl.textContent = hh + ":" + minutes + ":" + seconds + " " + ampm;
    dateEl.textContent = now.toLocaleDateString("en-PH", {
      weekday: "short",
      month: "short",
      day: "numeric",
      year: "numeric",
    });
  }
  updateClock();
  setInterval(updateClock, 1000);

  /* ---------- Employee Data (mock — will be replaced by PHP) ---------- */
  const employees = [
    { id: 1, name: "Juan Dela Cruz", role: "Technician", photo: "" },
    { id: 2, name: "Maria Santos", role: "Admin", photo: "" },
    { id: 3, name: "Pedro Reyes", role: "Technician", photo: "" },
    { id: 4, name: "Ana Villanueva", role: "Secretary", photo: "" },
    { id: 5, name: "Carlos Mendoza", role: "Driver", photo: "" },
    { id: 6, name: "Rosa Bautista", role: "Helper", photo: "" },
    { id: 7, name: "Miguel Torres", role: "Construction Worker", photo: "" },
    { id: 8, name: "Elena Garcia", role: "Developer", photo: "" },
    { id: 9, name: "Ramon Flores", role: "Technician", photo: "" },
    { id: 10, name: "Luz Ramos", role: "Admin", photo: "" },
    { id: 11, name: "Jose Aquino", role: "Technician", photo: "" },
    { id: 12, name: "Carmen Lim", role: "Secretary", photo: "" },
  ];

  /* ---------- Helpers ---------- */
  function getInitials(name) {
    return name
      .split(" ")
      .filter(Boolean)
      .slice(0, 2)
      .map(function (w) {
        return w[0];
      })
      .join("")
      .toUpperCase();
  }

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = str;
    return div.innerHTML;
  }

  /* ---------- Render Employee Cards ---------- */
  const grid = document.getElementById("employee-grid");
  const emptyState = document.getElementById("empty-state");

  function renderEmployees(list) {
    grid.innerHTML = "";

    if (list.length === 0) {
      grid.classList.add("hidden");
      emptyState.classList.remove("hidden");
      return;
    }
    grid.classList.remove("hidden");
    emptyState.classList.add("hidden");

    list.forEach(function (emp) {
      const card = document.createElement("a");
      card.href = "index.php?page=punch&employee_id=" + encodeURIComponent(emp.id);
      card.className =
        "group bg-white rounded-lg border border-slate-200 shadow-sm hover:shadow-md hover:border-emerald-300 focus:outline-none focus:ring-2 focus:ring-emerald-500 focus:ring-offset-2 transition-all p-4 flex flex-col items-center text-center min-h-[44px]";

      const avatarHtml = emp.photo
        ? '<img src="' +
          escapeHtml(emp.photo) +
          '" alt="" class="w-16 h-16 rounded-full object-cover border-2 border-slate-100">'
        : '<div class="w-16 h-16 rounded-full bg-emerald-700 flex items-center justify-center border-2 border-emerald-100">' +
          '<span class="text-white font-bold text-lg tracking-wide">' +
          getInitials(emp.name) +
          "</span>" +
          "</div>";

      card.innerHTML =
        avatarHtml +
        '<p class="mt-3 text-sm font-semibold text-slate-900 leading-snug line-clamp-2">' +
        escapeHtml(emp.name) +
        "</p>" +
        '<span class="mt-1.5 inline-block px-2 py-0.5 rounded-full bg-slate-100 text-slate-600 text-[10px] font-semibold uppercase tracking-wider">' +
        escapeHtml(emp.role) +
        "</span>";

      grid.appendChild(card);
    });
  }

  /* ---------- Search Filter ---------- */
  const searchInput = document.getElementById("search-input");

  function filterEmployees() {
    const query = searchInput.value.trim().toLowerCase();
    if (!query) {
      renderEmployees(employees);
      return;
    }
    const filtered = employees.filter(function (emp) {
      return emp.name.toLowerCase().indexOf(query) !== -1;
    });
    renderEmployees(filtered);
  }

  searchInput.addEventListener("input", filterEmployees);

  /* ---------- Init ---------- */
  renderEmployees(employees);
})();
