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
  sidebar.querySelectorAll("a").forEach(function (link) {
    link.addEventListener("click", function () {
      if (window.innerWidth < 1024) closeDrawer();
    });
  });

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

  const editId = getParam("id");
  let hasStoredPhoto = false;
  let removeStoredPhoto = false;

  function setAvatarPhoto(url) {
    avatarPreview.innerHTML = '<img src="' + escapeHtml(url) + '" alt="Employee photo" class="w-full h-full object-cover">';
    removePhotoBtn.classList.remove("hidden");
  }

  function fillForm(emp) {
    document.getElementById("page-title").textContent = "Edit Employee";
    document.getElementById("page-subtitle").textContent = "Update employee record and pay settings.";
    document.title = "Edit " + emp.full_name + " — Coronacion Timekeeping";

    document.getElementById("full_name").value = emp.full_name;
    document.getElementById("role").value = emp.role;
    document.getElementById("date_hired").value = emp.date_hired || "";
    document.getElementById("sss").value = emp.sss_number || "";
    document.getElementById("philhealth").value = emp.philhealth_number || "";
    document.getElementById("pagibig").value = emp.pagibig_number || "";
    document.getElementById("tin").value = emp.tin_number || "";
    document.getElementById("pay_frequency").value = emp.pay_frequency;
    document.getElementById("hourly_rate").value = emp.hourly_rate || "";
    document.getElementById("monthly_rate").value = emp.monthly_rate || "";
    document.getElementById("active").checked = Boolean(emp.is_active);
    updateRateVisibility();

    if (emp.profile_photo_url) {
      hasStoredPhoto = true;
      setAvatarPhoto(emp.profile_photo_url);
    } else {
      document.getElementById("avatar-initials").textContent = getInitials(emp.full_name);
    }

    applyLocks(emp);
  }

  // Identity details are set once at hiring. The server enforces this; the UI only mirrors it.
  const LOCKED_INPUTS = {
    full_name: "full_name",
    role: "role",
    date_hired: "date_hired",
    sss_number: "sss",
    philhealth_number: "philhealth",
    pagibig_number: "pagibig",
    tin_number: "tin",
  };
  const LOCK_TITLE = "Locked after hiring. Only the owner can change this.";

  function applyLocks(emp) {
    const locked = emp.locked_fields || [];
    locked.forEach(function (field) {
      const input = document.getElementById(LOCKED_INPUTS[field]);
      if (!input) return;
      input.disabled = true;
      input.title = LOCK_TITLE;
      input.classList.remove("bg-white");
      input.classList.add("bg-ink-100", "text-slate-500", "cursor-not-allowed");
      const label = document.querySelector('label[for="' + input.id + '"]');
      if (label && !label.querySelector(".lock-icon")) {
        label.insertAdjacentHTML(
          "beforeend",
          ' <svg class="lock-icon inline w-3 h-3 -mt-0.5 text-slate-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 1 0-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 0 0 2.25-2.25v-6.75a2.25 2.25 0 0 0-2.25-2.25H6.75a2.25 2.25 0 0 0-2.25 2.25v6.75a2.25 2.25 0 0 0 2.25 2.25Z" /></svg>'
        );
      }
    });

    const photoLocked = !emp.can_edit_identity && Boolean(emp.profile_photo_url);
    if (photoLocked) {
      photoInput.classList.add("hidden");
      removePhotoBtn.remove();
      const hint = photoInput.parentElement.querySelector("p.text-slate-400");
      if (hint) hint.classList.add("hidden");
      photoInput.insertAdjacentHTML(
        "afterend",
        '<p class="text-xs text-slate-500 mt-1">Only the employee (from their dashboard) or the owner can change this photo.</p>'
      );
    }

    if (locked.length || photoLocked) {
      form.insertAdjacentHTML(
        "afterbegin",
        '<div class="rounded-2xl border border-amber-200 bg-amber-50 px-4 py-3 text-sm text-amber-900 page-enter">' +
          "<strong>Identity details are locked.</strong> Name, job title, date hired and government IDs were set at hiring. " +
          "You can still update pay settings and status. Only the owner can correct a locked field." +
          "</div>"
      );
    }
  }

  async function loadEmployee() {
    try {
      fillForm(await api.get("employees", "show", { id: editId }));
    } catch (error) {
      showToast("Couldn't load employee: " + error.message, "error");
    }
  }

  const photoInput = document.getElementById("photo");
  const avatarPreview = document.getElementById("avatar-preview");
  const avatarInitials = document.getElementById("avatar-initials");
  const removePhotoBtn = document.getElementById("remove-photo");
  const MAX_PHOTO_BYTES = 5 * 1024 * 1024;

  photoInput.addEventListener("change", function () {
    const file = photoInput.files && photoInput.files[0];
    if (!file) return;

    if (file.size > MAX_PHOTO_BYTES) {
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
    removeStoredPhoto = hasStoredPhoto;
    const name = document.getElementById("full_name").value.trim();
    avatarPreview.innerHTML =
      '<span id="avatar-initials" class="text-white font-bold text-2xl">' +
      (name ? getInitials(name) : "?") +
      "</span>";
    removePhotoBtn.classList.add("hidden");
  });

  document.getElementById("full_name").addEventListener("input", function () {
    if (photoInput.files && photoInput.files.length) return;
    const name = this.value.trim();
    const el = document.getElementById("avatar-initials");
    if (el) el.textContent = name ? getInitials(name) : "?";
  });

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

  const form = document.getElementById("employee-form");
  const saveBtn = document.getElementById("save-btn");

  function payload() {
    const value = function (id) { return document.getElementById(id).value.trim(); };
    const hourlyShown = !hourlyWrap.classList.contains("hidden");
    const monthlyShown = !monthlyWrap.classList.contains("hidden");
    return {
      full_name: value("full_name"),
      role: value("role"),
      date_hired: value("date_hired"),
      sss_number: value("sss"),
      philhealth_number: value("philhealth"),
      pagibig_number: value("pagibig"),
      tin_number: value("tin"),
      pay_frequency: value("pay_frequency"),
      hourly_rate: hourlyShown ? value("hourly_rate") : "",
      monthly_rate: monthlyShown ? value("monthly_rate") : "",
      is_active: document.getElementById("active").checked,
    };
  }

  async function savePhoto(id) {
    const file = photoInput.files && photoInput.files[0];
    if (file) {
      const body = new FormData();
      body.append("photo", file);
      await api.post("employees", "uploadPhoto", { id: id, body: body });
    } else if (removeStoredPhoto) {
      await api.post("employees", "removePhoto", { id: id });
    }
  }

  form.addEventListener("submit", async function (e) {
    e.preventDefault();
    saveBtn.disabled = true;

    try {
      const saved = editId
        ? await api.post("employees", "update", { id: editId, body: payload() })
        : await api.post("employees", "store", { body: payload() });

      try {
        await savePhoto(saved.id);
      } catch (photoError) {
        showToast("Saved, but the photo failed: " + photoError.message, "error");
        saveBtn.disabled = false;
        return;
      }

      showToast("Employee saved.", "success");
      setTimeout(function () {
        window.location.href = "index.php?page=employees";
      }, 800);
    } catch (error) {
      showToast(error.message, "error");
      saveBtn.disabled = false;
    }
  });

  if (editId) loadEmployee();
})();
