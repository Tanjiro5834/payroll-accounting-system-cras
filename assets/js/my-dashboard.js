(function () {
  "use strict";

  const $ = (id) => document.getElementById(id);

  function esc(str) {
    const div = document.createElement("div");
    div.textContent = str == null ? "" : String(str);
    return div.innerHTML;
  }

  function peso(n) {
    return "₱" + Number(n || 0).toLocaleString("en-PH", { minimumFractionDigits: 2, maximumFractionDigits: 2 });
  }

  function hrs(n) {
    const v = Math.round(Number(n || 0) * 100) / 100;
    return v + (v === 1 ? " hr" : " hrs");
  }

  function mins(m) {
    m = Number(m || 0);
    return m >= 60 ? Math.floor(m / 60) + "h " + (m % 60) + "m" : m + " min";
  }

  function plural(n, word) {
    return n + " " + word + (n === 1 ? "" : "s");
  }

  function date(value, opts) {
    if (!value) return "—";
    const d = new Date(String(value).replace(" ", "T"));
    return d.toLocaleDateString("en-PH", opts || { month: "short", day: "numeric", year: "numeric" });
  }

  function period(start, end) {
    const s = new Date(start + "T00:00");
    const e = new Date(end + "T00:00");
    const sameMonth = s.getMonth() === e.getMonth();
    return (
      s.toLocaleDateString("en-PH", { month: "short", day: "numeric" }) +
      "–" +
      e.toLocaleDateString("en-PH", sameMonth ? { day: "numeric" } : { month: "short", day: "numeric" }) +
      ", " + e.getFullYear()
    );
  }

  function initials(name) {
    return String(name || "?").split(/\s+/).filter(Boolean).slice(0, 2).map((w) => w[0]).join("").toUpperCase();
  }

  function avatar(el, photo, name, textCls) {
    el.innerHTML = photo
      ? '<img src="' + esc(photo) + '?t=' + Date.now() + '" alt="" class="w-full h-full object-cover">'
      : '<span class="text-white font-bold ' + (textCls || "text-lg") + '">' + esc(initials(name)) + "</span>";
  }

  const FREQ = { weekly: "Weekly", kinsenas: "Kinsenas", monthly: "Monthly" };
  const STATUS = {
    paid: ["Released", "bg-emerald-100 text-emerald-800"],
    approved: ["Approved", "bg-amber-100 text-amber-800"],
  };

  function badge(status) {
    const s = STATUS[status] || [status, "bg-slate-100 text-slate-700"];
    return '<span class="inline-flex px-2 py-0.5 rounded-md text-[11px] font-bold uppercase tracking-wide ' + s[1] + '">' + esc(s[0]) + "</span>";
  }

  const toastContainer = $("toast-container");
  function toast(message, ok) {
    const t = document.createElement("div");
    t.className = "pointer-events-auto w-full max-w-sm rounded-lg shadow-lg px-4 py-3 text-sm font-medium text-white " + (ok ? "bg-emerald-700" : "bg-red-600");
    t.textContent = message;
    toastContainer.appendChild(t);
    setTimeout(() => t.remove(), 3200);
  }

  let state = { summary: null };

  /* ---------------- Summary ---------------- */

  function greeting() {
    const h = new Date().getHours();
    return h < 12 ? "Good morning" : h < 18 ? "Good afternoon" : "Good evening";
  }

  const TONE = {
    good: ["border-emerald-200 bg-emerald-50 text-emerald-900", "M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z", "Good"],
    warn: ["border-amber-200 bg-amber-50 text-amber-900", "M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z", "Heads up"],
    info: ["border-sky-200 bg-sky-50 text-sky-900", "m11.25 11.25.041-.02a.75.75 0 0 1 1.063.852l-.708 2.836a.75.75 0 0 0 1.063.853l.041-.021M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9-3.75h.008v.008H12V8.25Z", "Info"],
  };

  function renderInsights(list) {
    $("insights").innerHTML = list
      .map(function (i) {
        const t = TONE[i.tone] || TONE.info;
        return (
          '<li class="flex items-start gap-3 rounded-2xl border px-4 py-3 text-sm ' + t[0] + '">' +
          '<svg class="w-5 h-5 flex-shrink-0 mt-px" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.8" stroke="currentColor" aria-label="' + t[2] + '"><path stroke-linecap="round" stroke-linejoin="round" d="' + t[1] + '" /></svg>' +
          "<span>" + esc(i.text) + "</span></li>"
        );
      })
      .join("");
  }

  function row(label, value) {
    return '<div class="flex items-baseline justify-between gap-3"><dt class="text-slate-500">' + esc(label) + '</dt><dd class="text-right text-ink font-medium">' + value + "</dd></div>";
  }

  function renderSummary(s) {
    state.summary = s;
    const p = s.profile;
    const first = String(p.full_name).split(/\s+/)[0];

    $("greeting").textContent = greeting() + ", " + first;
    $("hero-sub").textContent = p.role + " · " + (FREQ[p.pay_frequency] || p.pay_frequency) + " pay";
    avatar($("hero-avatar"), p.photo, p.full_name);
    avatar($("modal-avatar"), p.photo, p.full_name, "text-2xl");
    $("photo-remove").classList.toggle("hidden", !p.photo);

    renderInsights(s.insights);

    const m = s.month;
    const pm = s.prev_month;
    $("kpi-days").textContent = m.days_worked;
    $("kpi-days-sub").textContent = m.label;

    $("kpi-late").textContent = plural(m.late_days, "day");
    $("kpi-late").classList.toggle("text-warning", m.late_days > 0);
    $("kpi-late").classList.toggle("text-ink", m.late_days === 0);
    $("kpi-late-sub").textContent = (m.late_days ? mins(m.late_minutes) + " total" : "On time so far") + " · last month: " + pm.late_days;

    $("kpi-ot").textContent = hrs(m.ot_hours);
    $("kpi-ot-sub").textContent = m.ot_days ? "over " + plural(m.ot_days, "day") : "No overtime yet";

    const slip = s.latest_payslip;
    if (slip) {
      $("kpi-pay").textContent = peso(slip.net_pay);
      $("kpi-pay-sub").textContent = (slip.status === "paid" ? "Released " + date(slip.paid_at) : "Approved, awaiting release");
    } else {
      $("kpi-pay").textContent = "—";
      $("kpi-pay-sub").textContent = "No released pay yet";
    }

    const pay = s.pay;
    $("pay-list").innerHTML =
      (pay.monthly_rate ? row("Monthly salary", '<span class="num">' + peso(pay.monthly_rate) + "</span>") : "") +
      (pay.daily_rate ? row("Daily rate", '<span class="num">' + peso(pay.daily_rate) + "</span>") : "") +
      (pay.hourly_rate ? row("Hourly rate", '<span class="num">' + peso(pay.hourly_rate) + "</span>") : "") +
      row("Pay schedule", esc(FREQ[p.pay_frequency] || p.pay_frequency)) +
      row("Next payday", esc(date(pay.next_payday, { weekday: "short", month: "short", day: "numeric" }))) +
      row("Released this " + s.year, '<span class="num text-positive">' + peso(s.ytd_paid) + "</span>");

    $("profile-list").innerHTML =
      row("Job title", esc(p.role)) +
      row("Date hired", esc(date(p.date_hired))) +
      row("With the company", esc(p.tenure || "—")) +
      Object.keys(p.gov_ids)
        .map((k) => row(k, p.gov_ids[k] ? '<span class="num">' + esc(p.gov_ids[k]) + "</span>" : '<span class="text-slate-400">Not on file</span>'))
        .join("");

    renderDuty(s.sunday_duty || [], s.profile.id);

    const t = s.thirteenth;
    $("thirteenth-title").textContent = "13th Month Pay " + s.year;
    $("thirteenth-body").innerHTML = t
      ? '<p class="num text-2xl font-semibold text-ink">' + peso(t.thirteenth_month_pay) + "</p>" +
        '<div class="mt-2 flex items-center gap-2">' + badge(t.status) + "</div>" +
        '<dl class="mt-3 space-y-2">' +
        row("Approved by", esc(t.approved_by || "—")) +
        row("Approved on", esc(date(t.approved_at))) +
        (t.paid_at ? row("Released on", esc(date(t.paid_at))) : "") +
        "</dl>"
      : "Not approved yet. It will show here once your employer approves it.";
  }

  function renderDuty(duties, me) {
    $("duty-list").innerHTML = duties.length
      ? duties.map((d) => {
          const team = d.members.filter((m) => m.id !== me && m.id !== d.lead_employee_id).map((m) => esc(m.name));
          const isLead = d.lead_employee_id === me;
          return '<li class="rounded-xl bg-ink-50 px-3 py-2.5">' +
            '<p class="font-semibold text-ink num">' + esc(date(d.duty_date, { weekday: "long", month: "short", day: "numeric" })) + "</p>" +
            '<p class="text-xs text-slate-600 mt-0.5">' + (isLead ? "You're the lead" : "Lead: " + esc(d.lead_name)) + "</p>" +
            (team.length ? '<p class="text-xs text-slate-500 mt-0.5">With ' + team.join(", ") + "</p>" : "") +
            (d.notes ? '<p class="text-xs text-slate-400 mt-0.5">' + esc(d.notes) + "</p>" : "") +
          "</li>";
        }).join("")
      : '<li class="text-slate-500">No Sunday duty scheduled.</li>';
  }

  /* ---------------- Payslips ---------------- */

  function renderPayslips(rows) {
    const tbody = $("payslip-tbody");
    if (!rows.length) {
      tbody.innerHTML = '<tr><td colspan="6" class="px-4 py-8 text-center text-sm text-slate-400">No approved payslips yet.</td></tr>';
      return;
    }
    tbody.innerHTML = rows
      .map(
        (r) =>
          '<tr data-id="' + Number(r.id) + '" tabindex="0" class="cursor-pointer focus:outline-none focus:bg-ink-50">' +
          '<td class="px-4 py-3 whitespace-nowrap text-ink">' + esc(period(r.period_start, r.period_end)) + "</td>" +
          '<td class="hidden sm:table-cell px-4 py-3 text-right num text-xs text-slate-700 whitespace-nowrap">' + peso(r.gross_pay) + "</td>" +
          '<td class="hidden sm:table-cell px-4 py-3 text-right num text-xs text-red-700 whitespace-nowrap">' + (Number(r.total_deductions) ? "-" : "") + peso(r.total_deductions) + "</td>" +
          '<td class="px-4 py-3 text-right num font-bold text-emerald-800 whitespace-nowrap">' + peso(r.net_pay) + "</td>" +
          '<td class="px-4 py-3">' + badge(r.status) + "</td>" +
          '<td class="px-4 py-3 hidden md:table-cell text-slate-600">' + esc(r.approved_by || "—") + "</td>" +
          "</tr>"
      )
      .join("");
  }

  async function openPayslip(id) {
    openModal("payslip-modal");
    $("payslip-body").innerHTML = '<p class="text-slate-400">Loading…</p>';
    try {
      const r = await api.get("my-dashboard", "payslip", { id: id });
      $("payslip-title").textContent = "Payslip · " + period(r.period_start, r.period_end);
      $("payslip-sub").innerHTML = badge(r.status) + (r.approved_by ? " &nbsp;Approved by " + esc(r.approved_by) + " on " + esc(date(r.approved_at)) : "") +
        (r.paid_at ? " · Released " + esc(date(r.paid_at)) : "");

      const line = (label, value, cls) =>
        '<div class="flex justify-between gap-4 py-1.5"><span class="text-slate-600">' + label + '</span><span class="num ' + (cls || "text-ink") + '">' + value + "</span></div>";

      const ded = r.deductions.length
        ? r.deductions.map((d) => line(esc(d.name || d.code), "-" + peso(d.amount), "text-red-700")).join("")
        : line("None", peso(0));

      const extra = r.premium_lines || [];
      const premium = extra.length
        ? '<p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mt-3 mb-1">Sunday &amp; holiday pay</p>' +
          extra.map((l) => line(esc(date(l.date)) + " · " + esc(l.label) + ' <span class="text-slate-400">' + esc(l.rate) + "</span>", "+" + peso(l.amount), "text-sky-800")).join("")
        : "";

      $("payslip-body").innerHTML =
        '<p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mb-1">Hours</p>' +
        line("Regular", hrs(r.total_regular_hours)) +
        line("Overtime", hrs(r.total_overtime_hours)) +
        line("Night differential", hrs(r.total_night_diff_hours)) +
        line("Late", mins(r.total_late_minutes), Number(r.total_late_minutes) ? "text-warning" : "") +
        line("Undertime", mins(r.total_undertime_minutes), Number(r.total_undertime_minutes) ? "text-warning" : "") +
        line("Hourly rate", peso(r.hourly_rate)) +
        premium +
        '<div class="border-t border-line my-3"></div>' +
        line('<strong class="text-ink">Gross pay</strong>', peso(r.gross_pay), "font-semibold") +
        '<p class="text-[11px] font-semibold uppercase tracking-wider text-slate-500 mt-3 mb-1">Deductions</p>' +
        ded +
        '<div class="border-t border-line my-3"></div>' +
        '<div class="flex justify-between items-baseline"><span class="font-bold text-ink">Net pay</span><span class="num text-xl font-bold text-emerald-800">' + peso(r.net_pay) + "</span></div>";
    } catch (e) {
      $("payslip-body").innerHTML = '<p class="text-red-700">' + esc(e.message) + "</p>";
    }
  }

  $("payslip-tbody").addEventListener("click", function (e) {
    const tr = e.target.closest("tr[data-id]");
    if (tr) openPayslip(tr.dataset.id);
  });
  $("payslip-tbody").addEventListener("keydown", function (e) {
    const tr = e.target.closest("tr[data-id]");
    if (tr && (e.key === "Enter" || e.key === " ")) {
      e.preventDefault();
      openPayslip(tr.dataset.id);
    }
  });

  /* ---------------- Attendance ---------------- */

  let attendance = [];
  let tab = "late";
  const TABS = {
    late: { head: "Late", keep: (d) => d.late_minutes > 0, value: (d) => mins(d.late_minutes), cls: "text-warning", empty: "No lates this month." },
    ot: { head: "Overtime", keep: (d) => Number(d.overtime_hours) > 0, value: (d) => hrs(d.overtime_hours), cls: "text-coolant", empty: "No overtime this month." },
    undertime: { head: "Left early", keep: (d) => d.undertime_minutes > 0, value: (d) => mins(d.undertime_minutes), cls: "text-warning", empty: "No early outs this month." },
    all: { head: "Hours", keep: () => true, value: (d) => hrs(Number(d.regular_hours) + Number(d.overtime_hours)), cls: "text-ink", empty: "No attendance this month." },
  };

  function renderAttendance() {
    const t = TABS[tab];
    const rows = attendance.filter(t.keep);
    $("att-metric-head").textContent = t.head;
    document.querySelectorAll(".att-tab").forEach(function (b) {
      const on = b.dataset.tab === tab;
      b.setAttribute("aria-selected", on ? "true" : "false");
      b.className = "att-tab px-3 py-1.5 rounded-lg text-xs font-semibold " + (on ? "bg-ink text-white" : "bg-ink-50 text-slate-600 hover:bg-ink-100");
    });

    const late = attendance.filter(TABS.late.keep).length;
    const ot = attendance.filter(TABS.ot.keep).length;
    $("att-sub").textContent = plural(attendance.length, "day") + " recorded · " + plural(late, "late day") + " · " + plural(ot, "overtime day");

    $("att-tbody").innerHTML = rows.length
      ? rows
          .slice()
          .reverse()
          .map(
            (d) =>
              "<tr>" +
              '<td class="px-4 py-2.5 whitespace-nowrap text-ink">' + esc(date(d.date, { weekday: "short", month: "short", day: "numeric" })) + "</td>" +
              '<td class="px-4 py-2.5 num text-xs text-slate-600">' + esc(d.first_in || "—") + "</td>" +
              '<td class="px-4 py-2.5 num text-xs text-slate-600">' + esc(d.last_out || "—") + "</td>" +
              '<td class="px-4 py-2.5 text-right num font-semibold ' + t.cls + '">' + esc(t.value(d)) + "</td>" +
              "</tr>"
          )
          .join("")
      : '<tr><td colspan="4" class="px-4 py-6 text-center text-sm text-slate-400">' + t.empty + "</td></tr>";
  }

  async function loadAttendance() {
    $("att-tbody").innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-sm text-slate-400">Loading…</td></tr>';
    try {
      const res = await api.get("my-dashboard", "attendance", { params: { month: $("att-month").value } });
      attendance = res.days;
      renderAttendance();
    } catch (e) {
      $("att-tbody").innerHTML = '<tr><td colspan="4" class="px-4 py-6 text-center text-sm text-red-700">' + esc(e.message) + "</td></tr>";
    }
  }

  document.querySelectorAll(".att-tab").forEach(function (b) {
    b.addEventListener("click", function () {
      tab = b.dataset.tab;
      renderAttendance();
    });
  });
  $("att-month").addEventListener("change", loadAttendance);

  /* ---------------- Trend charts ---------------- */

  const tip = $("chart-tip");
  function showTip(e, html) {
    tip.innerHTML = html;
    tip.classList.remove("hidden");
    const x = Math.min(e.clientX + 12, window.innerWidth - tip.offsetWidth - 8);
    tip.style.left = x + "px";
    tip.style.top = e.clientY - tip.offsetHeight - 10 + "px";
  }
  function hideTip() {
    tip.classList.add("hidden");
  }

  function niceMax(v) {
    if (v <= 0) return 4;
    const step = Math.pow(10, Math.floor(Math.log10(v)));
    const n = v / step;
    return (n <= 2 ? 2 : n <= 5 ? 5 : 10) * step;
  }

  // Single-series bar chart: one hue, thin bars rounded at the top, value label on the latest bar only.
  function barChart(el, data, valueOf, format, tipOf) {
    const W = 320, H = 150, padL = 26, padB = 22, padT = 16;
    const plotW = W - padL, plotH = H - padB - padT;
    const max = niceMax(Math.max.apply(null, data.map(valueOf)));
    const slot = plotW / data.length;
    const barW = Math.min(28, slot * 0.5);
    const y = (v) => padT + plotH - (v / max) * plotH;

    let svg = '<svg viewBox="0 0 ' + W + " " + H + '" class="w-full h-auto" role="img">';
    [0, max / 2, max].forEach(function (g) {
      svg += '<line x1="' + padL + '" x2="' + W + '" y1="' + y(g) + '" y2="' + y(g) + '" stroke="#E1E7F0" stroke-width="1" />';
      svg += '<text x="' + (padL - 6) + '" y="' + (y(g) + 3) + '" text-anchor="end" font-size="9" fill="#8B98AE" font-family="Roboto Mono, monospace">' + Math.round(g * 10) / 10 + "</text>";
    });

    data.forEach(function (d, i) {
      const v = valueOf(d);
      const cx = padL + slot * i + slot / 2;
      const top = y(v);
      const h = padT + plotH - top;
      const r = Math.min(4, h, barW / 2);
      const x0 = cx - barW / 2, x1 = cx + barW / 2, base = padT + plotH;
      if (h > 0.5) {
        svg += '<path d="M' + x0 + "," + base + " V" + (top + r) + " Q" + x0 + "," + top + " " + (x0 + r) + "," + top +
          " H" + (x1 - r) + " Q" + x1 + "," + top + " " + x1 + "," + (top + r) + " V" + base + ' Z" fill="#0E7490" />';
      }
      if (i === data.length - 1) {
        svg += '<text x="' + cx + '" y="' + (top - 4) + '" text-anchor="middle" font-size="10" font-weight="600" fill="#0B1220" font-family="Roboto Mono, monospace">' + esc(format(v)) + "</text>";
      }
      svg += '<text x="' + cx + '" y="' + (H - 6) + '" text-anchor="middle" font-size="10" fill="#5A6A85">' + esc(d.label) + "</text>";
      svg += '<rect data-i="' + i + '" x="' + (padL + slot * i) + '" y="' + padT + '" width="' + slot + '" height="' + plotH + '" fill="transparent" />';
    });
    svg += "</svg>";
    el.innerHTML = svg;

    el.querySelectorAll("rect[data-i]").forEach(function (rect) {
      const d = data[Number(rect.dataset.i)];
      rect.addEventListener("mousemove", (e) => showTip(e, tipOf(d)));
      rect.addEventListener("mouseleave", hideTip);
    });
  }

  function monthName(ym) {
    return new Date(ym + "-01T00:00").toLocaleDateString("en-PH", { month: "long", year: "numeric" });
  }

  function renderTrend(rows) {
    barChart($("chart-late"), rows, (d) => d.late_days, (v) => v, (d) =>
      "<strong>" + esc(monthName(d.month)) + "</strong><br>" + plural(d.late_days, "late day") + " · " + mins(d.late_minutes));
    barChart($("chart-ot"), rows, (d) => d.ot_hours, (v) => Math.round(v * 10) / 10, (d) =>
      "<strong>" + esc(monthName(d.month)) + "</strong><br>" + hrs(d.ot_hours) + " over " + plural(d.ot_days, "day"));

    $("trend-table").innerHTML = rows
      .slice()
      .reverse()
      .map(
        (d) =>
          "<tr><td class=\"py-2 text-ink\">" + esc(monthName(d.month)) + "</td>" +
          '<td class="py-2 text-right num">' + d.days_worked + "</td>" +
          '<td class="py-2 text-right num">' + d.late_days + "</td>" +
          '<td class="py-2 text-right num">' + d.late_minutes + "</td>" +
          '<td class="py-2 text-right num">' + d.ot_hours + "</td></tr>"
      )
      .join("");
  }

  /* ---------------- Salary progression ---------------- */

  function renderRates(rows) {
    if (!rows.length) {
      $("rate-list").innerHTML = '<li class="ml-4 text-sm text-slate-400">No rate on record.</li>';
      return;
    }
    // Payroll pays the hourly rate when one is set, so that is the rate shown and compared.
    const hourly = (r) => Number(r.hourly_rate) > 0;
    const value = (r) => (hourly(r) ? peso(r.hourly_rate) + "/hr" : peso(r.monthly_rate) + "/mo");
    const amount = (r) => Number(hourly(r) ? r.hourly_rate : r.monthly_rate) || 0;

    $("rate-list").innerHTML = rows
      .map(function (r, i) {
        const prev = rows[i - 1];
        let change = "";
        if (prev && amount(prev) > 0 && hourly(prev) === hourly(r)) {
          const pct = ((amount(r) - amount(prev)) / amount(prev)) * 100;
          if (pct !== 0) {
            change = '<span class="ml-2 text-xs font-semibold ' + (pct > 0 ? "text-positive" : "text-negative") + '">' +
              (pct > 0 ? "▲ +" : "▼ ") + pct.toFixed(1) + "%</span>";
          }
        }
        const note = Number(r.is_baseline) ? "Rate on record" : i === 0 ? "Starting rate" : "Rate updated";
        const last = i === rows.length - 1;
        return (
          '<li class="ml-4">' +
          '<span class="absolute -left-1.5 mt-1.5 w-3 h-3 rounded-full border-2 border-white ' + (last ? "bg-coolant" : "bg-ink-300") + '"></span>' +
          '<p class="text-xs text-slate-500">' + esc(date(r.effective_date)) + " · " + note + "</p>" +
          '<p class="num text-sm font-semibold text-ink">' + value(r) + change + "</p>" +
          "</li>"
        );
      })
      .reverse()
      .join("");
  }

  /* ---------------- Profile modal ---------------- */

  let lastFocus = null;
  function openModal(id) {
    lastFocus = document.activeElement;
    $(id).classList.remove("hidden");
    document.body.classList.add("ui-drawer-locked");
    const f = $(id).querySelector("button, input");
    if (f) f.focus();
  }
  function closeModal(modal) {
    modal.classList.add("hidden");
    document.body.classList.remove("ui-drawer-locked");
    if (lastFocus) lastFocus.focus();
  }
  document.querySelectorAll("#payslip-modal, #profile-modal").forEach(function (modal) {
    modal.addEventListener("click", function (e) {
      if (e.target === modal || e.target.closest("[data-close]")) closeModal(modal);
    });
  });
  document.addEventListener("keydown", function (e) {
    if (e.key !== "Escape") return;
    document.querySelectorAll("#payslip-modal:not(.hidden), #profile-modal:not(.hidden)").forEach(closeModal);
  });

  $("open-profile").addEventListener("click", () => openModal("profile-modal"));

  const MAX_PHOTO = 5 * 1024 * 1024;
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
      loadSummary();
    } catch (e) {
      toast(e.message);
    }
  });

  $("photo-remove").addEventListener("click", async function () {
    if (!confirm("Remove your profile photo?")) return;
    try {
      await api.post("my-dashboard", "removePhoto");
      toast("Photo removed.", true);
      loadSummary();
    } catch (e) {
      toast(e.message);
    }
  });

  $("password-form").addEventListener("submit", async function (e) {
    e.preventDefault();
    const next = $("new_password").value;
    if (next !== $("confirm_password").value) return toast("New passwords don't match.");
    const btn = $("password-save");
    btn.disabled = true;
    try {
      await api.post("my-dashboard", "changePassword", {
        body: { current_password: $("current_password").value, new_password: next },
      });
      this.reset();
      toast("Password updated.", true);
    } catch (err) {
      toast(err.message);
    } finally {
      btn.disabled = false;
    }
  });

  /* ---------------- Boot ---------------- */

  async function loadSummary() {
    renderSummary(await api.get("my-dashboard", "summary"));
  }

  async function boot() {
    const now = new Date();
    $("att-month").value = now.getFullYear() + "-" + String(now.getMonth() + 1).padStart(2, "0");
    $("att-month").max = $("att-month").value;

    try {
      await loadSummary();
    } catch (e) {
      $("link-error").textContent = e.message;
      $("link-error").classList.remove("hidden");
      return;
    }

    const results = await Promise.allSettled([
      api.get("my-dashboard", "payslips").then(renderPayslips),
      api.get("my-dashboard", "trend").then(renderTrend),
      api.get("my-dashboard", "rateHistory").then(renderRates),
      loadAttendance(),
    ]);
    results.forEach((r) => r.status === "rejected" && toast(r.reason.message));
  }

  boot();
})();
