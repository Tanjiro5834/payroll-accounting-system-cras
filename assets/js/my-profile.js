(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);
  const FREQ = { weekly: "Weekly", biweekly: "Bi-weekly", kinsenas: "Kinsenas", monthly: "Monthly" };
  const MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];
  const MAX_PHOTO = 5 * 1024 * 1024;

  function esc(s) {
    const d = document.createElement("div");
    d.textContent = s == null ? "" : String(s);
    return d.innerHTML;
  }

  function initials(name) {
    return String(name || "?").trim().split(/\s+/).slice(0, 2).map(function (w) { return w[0]; }).join("").toUpperCase();
  }

  function longDate(ymd) {
    const [y, m, d] = String(ymd || "").split("-").map(Number);
    return y && m && d ? MONTHS[m - 1] + " " + d + ", " + y : "—";
  }

  function toast(message, ok) {
    const t = document.createElement("div");
    t.className = "pointer-events-auto w-full max-w-sm rounded-lg shadow-lg px-4 py-3 text-sm font-medium text-white " + (ok ? "bg-emerald-700" : "bg-red-600");
    t.textContent = message;
    $("toast-container").appendChild(t);
    setTimeout(function () { t.remove(); }, 3500);
  }

  function row(label, value, mono) {
    return '<div class="flex items-baseline justify-between gap-3"><dt class="text-slate-500">' + esc(label) +
      '</dt><dd class="text-right text-ink font-medium' + (mono ? " num" : "") + '">' + esc(value || "—") + "</dd></div>";
  }

  function render(p) {
    $("avatar").innerHTML = p.photo
      ? '<img src="' + esc(p.photo) + "?t=" + Date.now() + '" alt="" class="w-full h-full object-cover">'
      : '<span class="text-white font-bold text-3xl">' + esc(initials(p.full_name)) + "</span>";
    $("p-name").textContent = p.full_name;
    $("p-sub").textContent = p.role + " · " + (FREQ[p.pay_frequency] || p.pay_frequency) + " pay";
    $("photo-remove").classList.toggle("hidden", !p.photo);
    $("upload-label").textContent = p.photo ? "Change photo" : "Upload photo";

    const ids = p.gov_ids || {};
    $("details").innerHTML =
      row("Date hired", p.date_hired ? longDate(p.date_hired) : "Not set") +
      row("With us for", p.tenure) +
      Object.keys(ids).map(function (k) { return ids[k] ? row(k, ids[k], true) : row(k, "Not on file"); }).join("");
  }

  async function load() {
    try {
      const s = await api.get("my-dashboard", "summary");
      render(s.profile);
    } catch (e) {
      $("load-error").textContent = e.message;
      $("load-error").classList.remove("hidden");
    }
  }

  $("photo-input").addEventListener("change", async function () {
    const file = this.files && this.files[0];
    this.value = "";
    if (!file) return;
    if (file.size > MAX_PHOTO) return toast("Photo is larger than 5MB.");
    const body = new FormData();
    body.append("photo", file);
    try {
      await api.post("my-dashboard", "uploadPhoto", { body: body });
      toast("Photo updated.", true);
      load();
    } catch (e) {
      toast(e.message);
    }
  });

  $("photo-remove").addEventListener("click", async function () {
    if (!confirm("Remove your profile photo?")) return;
    try {
      await api.post("my-dashboard", "removePhoto");
      toast("Photo removed.", true);
      load();
    } catch (e) {
      toast(e.message);
    }
  });

  document.querySelectorAll("[data-toggle]").forEach(function (btn) {
    btn.addEventListener("click", function () {
      const input = $(btn.dataset.toggle);
      const show = input.type === "password";
      input.type = show ? "text" : "password";
      btn.setAttribute("aria-label", show ? "Hide password" : "Show password");
      btn.classList.toggle("text-coolant", show);
    });
  });

  // Simple strength hint: length + mix of character types.
  $("new_password").addEventListener("input", function () {
    const v = this.value;
    let score = 0;
    if (v.length >= 8) score++;
    if (v.length >= 12) score++;
    if (/[a-z]/.test(v) && /[A-Z]/.test(v)) score++;
    if (/\d/.test(v)) score++;
    if (/[^A-Za-z0-9]/.test(v)) score++;
    const levels = [
      ["w-0", "", ""],
      ["w-1/4 bg-red-500", "text-red-600", "Too weak"],
      ["w-2/4 bg-amber-500", "text-amber-600", "Okay"],
      ["w-3/4 bg-emerald-500", "text-emerald-600", "Good"],
      ["w-full bg-emerald-600", "text-emerald-700", "Strong"],
    ];
    const lvl = v ? levels[Math.max(1, Math.min(4, score))] : levels[0];
    $("strength-bar").className = "h-full rounded-full transition-all " + lvl[0];
    $("strength-text").className = "text-xs !mt-1.5 min-h-[1rem] " + (lvl[1] || "text-slate-400");
    $("strength-text").innerHTML = v.length && v.length < 8 ? "At least 8 characters" : lvl[2];
    checkMatch();
  });

  function checkMatch() {
    const a = $("new_password").value;
    const b = $("confirm_password").value;
    const el = $("match-text");
    if (!b) return el.classList.add("hidden");
    el.classList.remove("hidden");
    el.className = "text-xs -mt-2 " + (a === b ? "text-emerald-600" : "text-red-600");
    el.textContent = a === b ? "Passwords match" : "Passwords don't match";
  }
  $("confirm_password").addEventListener("input", checkMatch);

  $("password-form").addEventListener("submit", async function (e) {
    e.preventDefault();
    const next = $("new_password").value;
    if (next !== $("confirm_password").value) return toast("New passwords don't match.");
    const btn = $("password-save");
    btn.disabled = true;
    btn.textContent = "Updating…";
    try {
      await api.post("my-dashboard", "changePassword", {
        body: { current_password: $("current_password").value, new_password: next },
      });
      this.reset();
      $("strength-bar").className = "h-full w-0 rounded-full transition-all";
      $("strength-text").textContent = "";
      $("match-text").classList.add("hidden");
      toast("Password updated.", true);
    } catch (err) {
      toast(err.message);
    } finally {
      btn.disabled = false;
      btn.textContent = "Update password";
    }
  });

  load();
})();