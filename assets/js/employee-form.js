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
  sidebar.querySelectorAll("a").forEach(function (link) {
    link.addEventListener("click", function () {
      if (window.innerWidth < 1024) closeDrawer();
    });
  });

  /* ---------- Helpers ---------- */
  function getParam(name) {
    return new URLSearchParams(window.location.search).get(name);
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
  function escapeHtml(str) {
    const div = document.createElement("div");
    div.textContent = String(str);
    return div.innerHTML;
  }

  /* ---------- Edit Mode Prefill (mock — replaced by PHP) ---------- */
  const editId = getParam("id");
  const MOCK_DATA = {
    1: {
      full_name: "Juan Dela Cruz",
      role: "Technician",
      date_hired: "2023-01-15",
      sss: "34-1234567-8",
      philhealth: "12-345678901-2",
      pagibig: "1234-5678-9012",
      tin: "123-456-789-000",
      pay_frequency: "weekly",
      hourly_rate: 120,
      monthly_rate: 0,
      active: true,
    },
    2: {
      full_name: "Maria Santos",
      role: "Admin",
      date_hired: "2022-06-01",
      sss: "34-2345678-9",
      philhealth: "12-456789012-3",
      pagibig: "2345-6789-0123",
      tin: "234-567-890-000",
      pay_frequency: "monthly",
      hourly_rate: 0,
      monthly_rate: 32000,
      active: true,
    },
    3: {
      full_name: "Pedro Reyes",
      role: "Technician",
      date_hired: "2023-03-20",
      sss: "34-3456789-0",
      philhealth: "12-567890123-4",
      pagibig: "3456-7890-1234",
      tin: "345-678-901-000",
      pay_frequency: "kinsenas",
      hourly_rate: 130,
      monthly_rate: 0,
      active: true,
    },
  };

  if (editId && MOCK_DATA[editId]) {
    const d = MOCK_DATA[editId];
    document.getElementById("page-title").textContent = "Edit Employee";
    document.getElementById("page-subtitle").textContent =
      "Update employee record and pay settings.";
    document.title = "Edit " + d.full_name + " — Coronacion Timekeeping";

    document.getElementById("full_name").value = d.full_name;
    document.getElementById("role").value = d.role;
    document.getElementById("date_hired").value = d.date_hired;
    document.getElementById("sss").value = d.sss;
    document.getElementById("philhealth").value = d.philhealth;
    document.getElementById("pagibig").value = d.pagibig;
    document.getElementById("tin").value = d.tin;
    document.getElementById("pay_frequency").value = d.pay_frequency;
    document.getElementById("hourly_rate").value = d.hourly_rate || "";
    document.getElementById("monthly_rate").value = d.monthly_rate || "";
    document.getElementById("active").checked = d.active;

    // Update avatar initials
    document.getElementById("avatar-initials").textContent = getInitials(
      d.full_name,
    );
  }

  /* ---------- Avatar Preview ---------- */
  const photoInput = document.getElementById("photo");
  const avatarPreview = document.getElementById("avatar-preview");
  const avatarInitials = document.getElementById("avatar-initials");
  const removePhotoBtn = document.getElementById("remove-photo");

  photoInput.addEventListener("change", function () {
    const file = photoInput.files && photoInput.files[0];
    if (!file) return;

    // Validate size (2MB)
    if (file.size > 2 * 1024 * 1024) {
      showToast("Photo is larger than 2MB.", "error");
      photoInput.value = "";
      return;
    }

    const reader = new FileReader();
    reader.onload = function (e) {
      avatarPreview.innerHTML =
        '<img src="' +
        e.target.result +
        '" alt="Photo preview" class="w-full h-full object-cover">';
      removePhotoBtn.classList.remove("hidden");
    };
    reader.readAsDataURL(file);
  });

  removePhotoBtn.addEventListener("click", function () {
    photoInput.value = "";
    const name = document.getElementById("full_name").value.trim();
    avatarPreview.innerHTML =
      '<span id="avatar-initials" class="text-white font-bold text-2xl">' +
      (name ? getInitials(name) : "?") +
      "</span>";
    removePhotoBtn.classList.add("hidden");
  });

  // Update initials live when name changes
  document.getElementById("full_name").addEventListener("input", function () {
    if (photoInput.files && photoInput.files.length) return;
    const name = this.value.trim();
    const el = document.getElementById("avatar-initials");
    if (el) el.textContent = name ? getInitials(name) : "?";
  });

  /* ---------- Pay frequency: show relevant rate field ---------- */
  const payFreq = document.getElementById("pay_frequency");
  const hourlyWrap = document.getElementById("hourly-wrap");
  const monthlyWrap = document.getElementById("monthly-wrap");

  function updateRateVisibility() {
    const v = payFreq.value;
    if (v === "monthly") {
      monthlyWrap.classList.remove("hidden");
      hourlyWrap.classList.add("hidden");
    } else if (v === "weekly" || v === "kinsenas") {
      hourlyWrap.classList.remove("hidden");
      monthlyWrap.classList.add("hidden");
    } else {
      hourlyWrap.classList.remove("hidden");
      monthlyWrap.classList.remove("hidden");
    }
  }
  payFreq.addEventListener("change", updateRateVisibility);
  updateRateVisibility();

  /* ---------- Toast ---------- */
  const toastContainer = document.getElementById("toast-container");
  function showToast(message, type) {
    const isSuccess = type === "success";
    const toast = document.createElement("div");
    toast.className =
      "pointer-events-auto w-full max-w-sm rounded-lg shadow-lg px-4 py-3 flex items-start gap-3 text-sm font-medium " +
      (isSuccess ? "bg-emerald-700 text-white" : "bg-red-600 text-white");
    const icon = isSuccess
      ? '<svg class="w-5 h-5 flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>'
      : '<svg class="w-5 h-5 flex-shrink-0 mt-0.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" /></svg>';
    toast.innerHTML =
      icon + '<span class="flex-1">' + escapeHtml(message) + "</span>";
    toastContainer.appendChild(toast);
    setTimeout(function () {
      toast.style.opacity = "0";
      setTimeout(function () {
        if (toast.parentNode) toast.parentNode.removeChild(toast);
      }, 200);
    }, 3000);
  }

  /* ---------- Form Submit ---------- */
  const form = document.getElementById("employee-form");
  form.addEventListener("submit", function (e) {
    e.preventDefault();

    const formData = new FormData(form);
    fetch(form.action, { method: "POST", body: formData })
      .then(function (res) {
        if (!res.ok) throw new Error("Server responded " + res.status);
        return res.json();
      })
      .then(function () {
        showToast("Employee saved successfully.", "success");
        setTimeout(function () {
          window.location.href = "index.php?page=employees";
        }, 800);
      })
      .catch(function () {
        // Mock success for standalone preview
        showToast("Employee saved successfully.", "success");
        setTimeout(function () {
          window.location.href = "index.php?page=employees";
        }, 800);
      });
  });
})();
