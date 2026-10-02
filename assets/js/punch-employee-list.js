(function () {
  "use strict";

  var timeEls = document.querySelectorAll('[data-clock="time"]');
  var dateEls = document.querySelectorAll('[data-clock="date"]');

  function updateClock() {
    var now = new Date();

    var hours = now.getHours();
    var minutes = String(now.getMinutes()).padStart(2, "0");
    var seconds = String(now.getSeconds()).padStart(2, "0");
    var ampm = hours >= 12 ? "PM" : "AM";
    hours = hours % 12 || 12;
    var hh = String(hours).padStart(2, "0");

    var timeStr = hh + ":" + minutes + ":" + seconds + " " + ampm;
    var dateStr = now.toLocaleDateString("en-PH", {
      weekday: "short",
      month: "short",
      day: "numeric",
      year: "numeric",
    });

    timeEls.forEach(function (el) { el.textContent = timeStr; });
    dateEls.forEach(function (el) { el.textContent = dateStr; });
  }
  updateClock();
  setInterval(updateClock, 1000);

  var employees = [];
  var canPickAny = false;

  function getInitials(name) {
    return String(name || "")
      .split(" ")
      .filter(Boolean)
      .slice(0, 2)
      .map(function (w) { return w[0]; })
      .join("")
      .toUpperCase();
  }

  function escapeHtml(str) {
    var div = document.createElement("div");
    div.textContent = str == null ? "" : str;
    return div.innerHTML;
  }

  var grid = document.getElementById("employee-grid");
  var emptyState = document.getElementById("empty-state");
  var errorState = document.getElementById("error-state");
  var errorMessage = document.getElementById("error-message");
  var retryBtn = document.getElementById("retry-btn");
  var skeletonGrid = document.getElementById("skeleton-grid");
  var searchInput = document.getElementById("search-input");
  var searchClear = document.getElementById("search-clear");
  var resultCount = document.getElementById("result-count");
  var adminLink = document.getElementById("admin-link");

  function show(el) { if (el) el.classList.remove("hidden"); }
  function hide(el) { if (el) el.classList.add("hidden"); }

  function setState(which) {
    if (which === "loading") {
      show(skeletonGrid);
      hide(grid);
      hide(emptyState);
      hide(errorState);
    } else if (which === "list") {
      hide(skeletonGrid);
      show(grid);
      hide(emptyState);
      hide(errorState);
    } else if (which === "empty") {
      hide(skeletonGrid);
      hide(grid);
      show(emptyState);
      hide(errorState);
    } else if (which === "error") {
      hide(skeletonGrid);
      hide(grid);
      hide(emptyState);
      show(errorState);
    }
  }

  function updateResultCount(shown, total) {
    if (!resultCount) return;
    if (total === 0) {
      resultCount.textContent = "";
      return;
    }
    if (shown === total) {
      resultCount.textContent = total + (total === 1 ? " employee" : " employees");
    } else {
      resultCount.textContent = shown + (shown === 1 ? " match" : " matches");
    }
  }

  var staggerIndex = 0;

  function buildCard(emp) {
    var wrap = document.createElement("div");
    wrap.setAttribute("role", "listitem");

    var card = document.createElement("a");
    card.href = "index.php?page=punch&employee_id=" + encodeURIComponent(emp.id);
    card.setAttribute("aria-label", "Punch as " + (emp.name || "employee"));

    card.className =
      "group flex flex-col items-center text-center bg-white rounded-2xl border border-line shadow-sm " +
      "p-4 min-h-[160px] hover:-translate-y-0.5 hover:shadow-md hover:border-coolant/40 " +
      "active:scale-[0.97] focus:outline-none focus-visible:ring-2 focus-visible:ring-frost focus-visible:ring-offset-2 " +
      "transition-[transform,box-shadow,border-color] duration-200";

    var delay = Math.min(staggerIndex, 12) * 30;
    staggerIndex += 1;
    card.style.animation = "ui-fade-up 260ms cubic-bezier(.2,.8,.2,1) both";
    card.style.animationDelay = delay + "ms";

    var avatarHtml = emp.photo
      ? '<img src="' + escapeHtml(emp.photo) + '" alt="" ' +
        'class="w-16 h-16 sm:w-[72px] sm:h-[72px] rounded-full object-cover border-2 border-coolant-tint flex-shrink-0">'
      : '<div class="w-16 h-16 sm:w-[72px] sm:h-[72px] rounded-full flex items-center justify-center border-2 border-coolant-tint flex-shrink-0" ' +
        'style="background: linear-gradient(135deg, #0E7490 0%, #22D3EE 100%);">' +
          '<span class="text-white font-bold text-lg sm:text-xl tracking-wide">' +
            escapeHtml(getInitials(emp.name)) +
          "</span>" +
        "</div>";

    card.innerHTML =
      avatarHtml +
      '<p class="mt-3 text-sm font-semibold text-ink leading-snug line-clamp-2">' +
        escapeHtml(emp.name) +
      "</p>" +
      '<span class="mt-2 inline-block px-2.5 py-0.5 rounded-full bg-coolant-tint text-coolant-hover text-[10px] font-semibold uppercase tracking-wider">' +
        escapeHtml(emp.role) +
      "</span>";

    wrap.appendChild(card);
    return wrap;
  }

  function renderEmployees(list) {
    grid.innerHTML = "";
    staggerIndex = 0;

    if (list.length === 0) {
      setState("empty");
      updateResultCount(0, employees.length);
      return;
    }

    setState("list");
    list.forEach(function (emp) {
      grid.appendChild(buildCard(emp));
    });

    updateResultCount(list.length, employees.length);
  }

  function filterEmployees() {
    var query = (searchInput.value || "").trim().toLowerCase();

    if (searchClear) {
      if (query) searchClear.classList.remove("hidden");
      else searchClear.classList.add("hidden");
    }

    if (!query) {
      renderEmployees(employees);
      return;
    }

    var filtered = employees.filter(function (emp) {
      return String(emp.name || "").toLowerCase().indexOf(query) !== -1;
    });
    renderEmployees(filtered);
  }

  searchInput.addEventListener("input", filterEmployees);

  searchInput.addEventListener("keydown", function (e) {
    if (e.key === "Escape") {
      if (searchInput.value) {
        e.preventDefault();
        searchInput.value = "";
        filterEmployees();
      }
    } else if (e.key === "Enter") {
      var cards = grid.querySelectorAll('a[href^="index.php?page=punch"]');
      if (cards.length === 1) {
        e.preventDefault();
        cards[0].click();
      }
    }
  });

  if (searchClear) {
    searchClear.addEventListener("click", function () {
      searchInput.value = "";
      filterEmployees();
      searchInput.focus();
    });
  }

  async function load() {
    setState("loading");
    if (errorMessage) errorMessage.textContent = "";

    try {
      var data = await api.get("punch-employee-list", "index");

      if (!data.can_pick_any && data.employees.length === 1) {
        location.replace(
          "index.php?page=punch&employee_id=" +
            encodeURIComponent(data.employees[0].id)
        );
        return;
      }

      employees = Array.isArray(data.employees) ? data.employees : [];
      canPickAny = !!data.can_pick_any;

      if (adminLink) {
        if (canPickAny) adminLink.classList.remove("hidden");
        else adminLink.classList.add("hidden");
        adminLink.classList.toggle("inline-flex", canPickAny);
      }

      renderEmployees(employees);
    } catch (error) {
      setState("error");
      if (errorMessage) {
        errorMessage.textContent =
          (error && error.message ? error.message : "Unknown error.") +
          " Please check your connection and try again.";
      }
    }
  }

  if (retryBtn) retryBtn.addEventListener("click", load);

  load();
})();