(function () {
  "use strict";

  /* ============================================================
               Utilities
               ============================================================ */
  function getParam(name) {
    return new URLSearchParams(window.location.search).get(name);
  }

  function pad(n) {
    return String(n).padStart(2, "0");
  }

  function formatTime12(date) {
    let h = date.getHours();
    const m = pad(date.getMinutes());
    const s = pad(date.getSeconds());
    const ampm = h >= 12 ? "PM" : "AM";
    h = h % 12 || 12;
    return pad(h) + ":" + m + ":" + s + " " + ampm;
  }

  function formatTimeShort(date) {
    let h = date.getHours();
    const m = pad(date.getMinutes());
    const ampm = h >= 12 ? "PM" : "AM";
    h = h % 12 || 12;
    return h + ":" + m + " " + ampm;
  }

  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = String(str);
    return div.innerHTML;
  }

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

  /* ============================================================
               Employee Data (mock — replaced by PHP)
               ============================================================ */
  const EMPLOYEES = {
    1: { name: "Juan Dela Cruz", role: "Technician" },
    2: { name: "Maria Santos", role: "Admin" },
    3: { name: "Pedro Reyes", role: "Technician" },
    4: { name: "Ana Villanueva", role: "Secretary" },
    5: { name: "Carlos Mendoza", role: "Driver" },
    6: { name: "Rosa Bautista", role: "Helper" },
    7: { name: "Miguel Torres", role: "Construction Worker" },
    8: { name: "Elena Garcia", role: "Developer" },
    9: { name: "Ramon Flores", role: "Technician" },
    10: { name: "Luz Ramos", role: "Admin" },
    11: { name: "Jose Aquino", role: "Technician" },
    12: { name: "Carmen Lim", role: "Secretary" },
  };

  const employeeId = getParam("employee_id") || "1";
  const employee = EMPLOYEES[employeeId] || EMPLOYEES[1];

  document.getElementById("employee_id").value = employeeId;
  document.getElementById("header-name").textContent = employee.name;
  document.getElementById("header-role").textContent = employee.role;
  document.getElementById("header-initials").textContent = getInitials(
    employee.name,
  );
  document.title = employee.name + " — Clock In / Out";

  /* ============================================================
               Live Clock
               ============================================================ */
  const clockEl = document.getElementById("live-clock");
  const dateEl = document.getElementById("live-date");

  function updateClock() {
    const now = new Date();
    clockEl.textContent = formatTime12(now);
    dateEl.textContent = now.toLocaleDateString("en-PH", {
      weekday: "long",
      month: "long",
      day: "numeric",
      year: "numeric",
    });
  }
  updateClock();
  setInterval(updateClock, 1000);

  /* ============================================================
               Punch Types & State
               ============================================================ */
  const PUNCH_TYPES = [
    { key: "am_in", label: "AM IN", dir: "in" },
    { key: "am_out", label: "AM OUT", dir: "out" },
    { key: "pm_in", label: "PM IN", dir: "in" },
    { key: "pm_out", label: "PM OUT", dir: "out" },
    { key: "ot_in", label: "OT IN", dir: "in" },
    { key: "ot_out", label: "OT OUT", dir: "out" },
  ];

  // Mock state — will be fetched from backend
  // Example: some punches already done
  const punchState = {
    am_in: null,
    am_out: null,
    pm_in: null,
    pm_out: null,
    ot_in: null,
    ot_out: null,
  };

  /* ============================================================
               Render Status Row
               ============================================================ */
  const statusRow = document.getElementById("status-row");

  function renderStatusRow() {
    statusRow.innerHTML = "";
    PUNCH_TYPES.forEach(function (pt) {
      const done = punchState[pt.key];
      const cell = document.createElement("div");
      cell.className =
        "flex flex-col items-center justify-center rounded-md border px-1.5 py-2 " +
        (done
          ? "border-emerald-200 bg-emerald-50"
          : "border-slate-200 bg-slate-50");

      const label = document.createElement("span");
      label.className =
        "font-mono text-[10px] font-semibold uppercase tracking-wider " +
        (done ? "text-emerald-700" : "text-slate-400");
      label.textContent = pt.label;

      const value = document.createElement("span");
      value.className =
        "font-mono text-xs font-semibold mt-0.5 " +
        (done ? "text-emerald-900" : "text-slate-300");
      value.textContent = done ? done : "—";

      cell.appendChild(label);
      cell.appendChild(value);
      statusRow.appendChild(cell);
    });
  }

  /* ============================================================
               Render Punch Buttons
               ============================================================ */
  const punchForm = document.getElementById("punch-form");

  function renderPunchButtons() {
    // Remove old buttons (keep hidden inputs)
    punchForm.querySelectorAll("button[data-punch]").forEach(function (b) {
      b.remove();
    });

    PUNCH_TYPES.forEach(function (pt) {
      const done = punchState[pt.key];
      const btn = document.createElement("button");
      btn.type = "button";
      btn.dataset.punch = pt.key;

      const base =
        "relative flex flex-col items-center justify-center rounded-lg font-semibold transition-all focus:outline-none focus:ring-2 focus:ring-offset-2 min-h-[80px] px-3 py-4 select-none";
      const enabled =
        pt.dir === "in"
          ? "bg-emerald-600 hover:bg-emerald-700 active:bg-emerald-800 text-white focus:ring-emerald-500 shadow-sm"
          : "bg-slate-700 hover:bg-slate-800 active:bg-slate-900 text-white focus:ring-slate-500 shadow-sm";
      const disabled =
        "bg-slate-100 text-slate-400 cursor-not-allowed border border-slate-200 focus:ring-slate-300";

      btn.className = base + " " + (done ? disabled : enabled);

      if (done) {
        btn.disabled = true;
        btn.setAttribute(
          "aria-label",
          pt.label + " already recorded at " + done,
        );
        btn.innerHTML =
          '<span class="font-mono text-base tracking-wide line-through opacity-60">' +
          pt.label +
          "</span>" +
          '<span class="mt-1 inline-flex items-center gap-1 text-[11px] font-mono">' +
          '<svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">' +
          '<path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />' +
          "</svg>" +
          done +
          "</span>";
      } else {
        btn.setAttribute("aria-label", "Record " + pt.label);
        const dirLabel = pt.dir === "in" ? "IN" : "OUT";
        const namePart = pt.label.replace(" " + dirLabel, "");
        btn.innerHTML =
          '<span class="font-mono text-2xl font-bold tracking-wide">' +
          namePart +
          "</span>" +
          '<span class="font-mono text-xs font-semibold tracking-[0.2em] opacity-90 mt-0.5">' +
          dirLabel +
          "</span>";
      }

      btn.addEventListener("click", function () {
        handlePunch(pt);
      });

      punchForm.appendChild(btn);
    });
  }

  /* ============================================================
               Toast Notifications
               ============================================================ */
  const toastContainer = document.getElementById("toast-container");

  function showToast(message, type) {
    const toast = document.createElement("div");
    const isSuccess = type === "success";
    toast.className =
      "pointer-events-auto w-full max-w-sm rounded-lg shadow-lg px-4 py-3 flex items-start gap-3 text-sm font-medium transition-all duration-200 " +
      (isSuccess ? "bg-emerald-700 text-white" : "bg-red-600 text-white");

    const icon = isSuccess
      ? '<svg class="w-5 h-5 flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>'
      : '<svg class="w-5 h-5 flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>';

    toast.innerHTML =
      icon + '<span class="flex-1">' + escapeHtml(message) + "</span>";
    toastContainer.appendChild(toast);

    // Animate in
    requestAnimationFrame(function () {
      toast.style.transform = "translateY(0)";
      toast.style.opacity = "1";
    });

    // Auto-dismiss
    setTimeout(function () {
      toast.style.opacity = "0";
      toast.style.transform = "translateY(8px)";
      setTimeout(function () {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 200);
    }, 3000);
  }

  /* ============================================================
               Punch Handler
               ============================================================ */
  function handlePunch(pt) {
    // Guard: already done
    if (punchState[pt.key]) return;

    // Guard: sequential order enforcement (client-side)
    const order = ["am_in", "am_out", "pm_in", "pm_out", "ot_in", "ot_out"];
    const idx = order.indexOf(pt.key);
    if (idx > 0 && !punchState[order[idx - 1]]) {
      showToast(
        "Please complete " + PUNCH_TYPES[idx - 1].label + " first.",
        "error",
      );
      return;
    }

    const btn = punchForm.querySelector('button[data-punch="' + pt.key + '"]');
    if (btn) {
      btn.disabled = true;
      btn.classList.add("opacity-70", "cursor-wait");
    }

    document.getElementById("punch_type").value = pt.key;

    const formData = new FormData(punchForm);
    // Use current time as fallback if server not available
    const now = new Date();
    const timeStr = formatTimeShort(now);

    // -------------------------------------------------------
    // ATTEMPT REAL API CALL
    // Falls back to mock success if /api/punch is unreachable.
    // -------------------------------------------------------
    fetch("/api/punch", {
      method: "POST",
      body: formData,
    })
      .then(function (res) {
        if (!res.ok) throw new Error("Server responded " + res.status);
        return res.json();
      })
      .then(function (data) {
        // Assume { success: true, time: "7:32 AM" }
        punchState[pt.key] = data && data.time ? data.time : timeStr;
        renderStatusRow();
        renderPunchButtons();
        showToast(
          pt.label + " recorded — " + punchState[pt.key] + " \u2705",
          "success",
        );
      })
      .catch(function () {
        // Mock success fallback for standalone preview
        punchState[pt.key] = timeStr;
        renderStatusRow();
        renderPunchButtons();
        showToast(pt.label + " recorded — " + timeStr + " \u2705", "success");
      });
  }

  /* ============================================================
               GPS Capture (silent)
               ============================================================ */
  const gpsDot = document.getElementById("gps-dot");
  const gpsLabel = document.getElementById("gps-label");

  function captureGPS() {
    if (!navigator.geolocation) {
      gpsDot.className = "w-2 h-2 rounded-full bg-slate-300";
      gpsLabel.textContent = "Location not supported";
      return;
    }

    navigator.geolocation.getCurrentPosition(
      function (pos) {
        const c = pos.coords;
        document.getElementById("gps_lat").value = c.latitude;
        document.getElementById("gps_lng").value = c.longitude;
        document.getElementById("gps_accuracy").value = c.accuracy;
        gpsDot.className = "w-2 h-2 rounded-full bg-emerald-500";
        gpsLabel.textContent =
          "Location captured (±" + Math.round(c.accuracy) + "m)";
      },
      function () {
        // Silently continue — do not block punching
        gpsDot.className = "w-2 h-2 rounded-full bg-amber-400";
        gpsLabel.textContent = "Location unavailable — punching allowed";
      },
      { enableHighAccuracy: true, timeout: 5000, maximumAge: 60000 },
    );
  }

  /* ============================================================
               Device Fingerprint
               ============================================================ */
  function computeFingerprint() {
    const raw = [
      navigator.userAgent,
      screen.width,
      screen.height,
      screen.colorDepth,
      Intl.DateTimeFormat().resolvedOptions().timeZone,
      navigator.language,
    ].join("|");

    // Simple 32-bit hash (djb2)
    let hash = 5381;
    for (let i = 0; i < raw.length; i++) {
      hash = (hash << 5) + hash + raw.charCodeAt(i);
      hash = hash & hash; // force 32-bit
    }
    const hex = (hash >>> 0).toString(16).padStart(8, "0");
    return "dev_" + hex;
  }

  document.getElementById("device_fingerprint").value = computeFingerprint();

  /* ============================================================
               Init
               ============================================================ */
  renderStatusRow();
  renderPunchButtons();
  captureGPS();
})();
