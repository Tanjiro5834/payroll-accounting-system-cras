(function () {
  "use strict";

  function esc(s) {
    if (s == null) return "";
    return String(s)
      .replace(/&/g, "&amp;")
      .replace(/</g, "&lt;")
      .replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;")
      .replace(/'/g, "&#39;");
  }

  function pad2(n) { return String(n).padStart(2, "0"); }

  function isoDate(d) {
    return d.getFullYear() + "-" + pad2(d.getMonth() + 1) + "-" + pad2(d.getDate());
  }

  function addDays(d, n) {
    var x = new Date(d);
    x.setDate(x.getDate() + n);
    return x;
  }

  var MONTHS = ["Jan","Feb","Mar","Apr","May","Jun","Jul","Aug","Sep","Oct","Nov","Dec"];

  function fmtDate(iso) {
    if (!iso) return "";
    var parts = String(iso).split("-");
    if (parts.length !== 3) return iso;
    var y = Number(parts[0]), m = Number(parts[1]), d = Number(parts[2]);
    if (!y || !m || !d) return iso;
    return MONTHS[m - 1] + " " + d + ", " + y;
  }

  function fmtTime(dt) {
    if (!dt) return "";
    var t = String(dt).split(" ")[1];
    if (!t) return "";
    var hm = t.split(":");
    if (hm.length < 2) return t;
    var h = Number(hm[0]), m = Number(hm[1]);
    if (isNaN(h) || isNaN(m)) return t;
    var ampm = h >= 12 ? "PM" : "AM";
    var h12 = h % 12; if (h12 === 0) h12 = 12;
    return h12 + ":" + pad2(m) + " " + ampm;
  }

  function fmtDateTime(dt) {
    if (!dt) return "";
    var parts = String(dt).split(" ");
    return fmtDate(parts[0]) + " · " + fmtTime(dt);
  }

  var PUNCH_LABELS = {
    AM_IN: "AM In", AM_OUT: "AM Out",
    PM_IN: "PM In", PM_OUT: "PM Out",
    OT_IN: "OT In", OT_OUT: "OT Out"
  };
  function punchLabel(t) {
    return PUNCH_LABELS[t] || t || "";
  }

  function reasonCategory(reason) {
    var s = String(reason || "").toLowerCase();
    if (s.indexOf("early out") !== -1) return "early_out";
    if (s.indexOf("missing pair") !== -1 || s.indexOf("missing") !== -1 && s.indexOf("pair") !== -1) return "missing_pair";
    if (s.indexOf("short lunch") !== -1) return "short_lunch";
    if (s.indexOf("long lunch") !== -1) return "long_lunch";
    if (s.indexOf("different ip") !== -1 || s.indexOf("ip mismatch") !== -1 || s.indexOf("out from different ip") !== -1) return "ip_mismatch";
    return "other";
  }

  function categoryLabel(key) {
    return {
      early_out: "Early out",
      missing_pair: "Missing pair",
      short_lunch: "Short lunch",
      long_lunch: "Long lunch",
      ip_mismatch: "IP mismatch",
      other: "Other"
    }[key] || "Other";
  }

  var $ = function (id) { return document.getElementById(id); };

  var statusSegment   = $("status-segment");
  var dateFromInput   = $("filter-date-from");
  var dateToInput     = $("filter-date-to");
  var employeeSelect  = $("filter-employee");
  var resetBtn        = $("reset-filters");
  var summaryChips    = $("summary-chips");
  var selectAllBox    = $("select-all-visible");
  var tableWrap       = $("table-wrap");
  var tbody           = $("flagged-tbody");
  var cardsWrap       = $("cards-wrap");
  var skeletonWrap    = $("skeleton-wrap");
  var emptyState      = $("empty-flag-state");
  var errorState      = $("error-state");
  var errorMessage    = $("error-message");
  var retryBtn        = $("retry-btn");
  var openCountValue  = $("open-count-value");
  var bulkBar         = $("bulk-bar");
  var bulkCount       = $("bulk-count");
  var bulkReviewBtn   = $("bulk-review");
  var bulkReviewLabel = $("bulk-review-label");
  var bulkReviewSpin  = $("bulk-review-spinner");
  var bulkClearBtn    = $("bulk-clear");
  var toastContainer  = $("toast-container");
  var srCount         = $("sr-count");

  var state = {
    rows: [],
    visible: [],
    selected: new Set(),
    activeChip: null,
    status: "open",
    loading: false,
    reasonCounts: { early_out: 0, missing_pair: 0, short_lunch: 0, long_lunch: 0, ip_mismatch: 0, other: 0 }
  };

  var debounceTimer = null;
  function debounce(fn, ms) {
    return function () {
      var args = arguments, ctx = this;
      clearTimeout(debounceTimer);
      debounceTimer = setTimeout(function () { fn.apply(ctx, args); }, ms);
    };
  }

  function showToast(message, type) {
    var ok = type === "success";
    var t = document.createElement("div");
    t.className =
      "pointer-events-auto w-full max-w-sm rounded-xl shadow-lg px-4 py-3 flex items-start gap-3 text-sm font-medium " +
      (ok ? "bg-emerald-700 text-white" : "bg-red-600 text-white");
    t.setAttribute("role", ok ? "status" : "alert");
    var span = document.createElement("span");
    span.className = "flex-1";
    span.textContent = String(message == null ? "" : message);
    t.appendChild(span);
    toastContainer.appendChild(t);
    setTimeout(function () {
      t.style.transition = "opacity 200ms ease";
      t.style.opacity = "0";
      setTimeout(function () { if (t.parentNode) t.parentNode.removeChild(t); }, 220);
    }, 3200);
  }

  function readUrlState() {
    var qs = new URLSearchParams(location.search);
    var s = qs.get("status");
    if (s === "reviewed" || s === "all" || s === "open") state.status = s;
    else state.status = "open";

    var today = new Date();
    var defFrom = isoDate(addDays(today, -13));
    var defTo = isoDate(today);

    var from = qs.get("date_from");
    var to = qs.get("date_to");
    dateFromInput.value = from && /^\d{4}-\d{2}-\d{2}$/.test(from) ? from : defFrom;
    dateToInput.value   = to   && /^\d{4}-\d{2}-\d{2}$/.test(to)   ? to   : defTo;

    var emp = qs.get("employee_id");
    if (emp && /^\d+$/.test(emp) && emp !== "0") {
      state._pendingEmployee = emp;
    }

    var chip = qs.get("chip");
    if (chip && ["early_out","missing_pair","short_lunch","long_lunch","ip_mismatch","other"].indexOf(chip) !== -1) {
      state.activeChip = chip;
    }
  }

  function writeUrlState() {
    var qs = new URLSearchParams();
    qs.set("page", "flagged-punches");
    qs.set("status", state.status);
    if (dateFromInput.value) qs.set("date_from", dateFromInput.value);
    if (dateToInput.value) qs.set("date_to", dateToInput.value);
    if (employeeSelect.value && employeeSelect.value !== "0") qs.set("employee_id", employeeSelect.value);
    if (state.activeChip) qs.set("chip", state.activeChip);

    var url = location.pathname + "?" + qs.toString();
    history.replaceState(null, "", url);
  }

  function currentParams() {
    return {
      status: state.status,
      date_from: dateFromInput.value || "",
      date_to: dateToInput.value || "",
      employee_id: employeeSelect.value || "0"
    };
  }

  function setSegmentUI() {
    var buttons = statusSegment.querySelectorAll(".seg-btn");
    buttons.forEach(function (b) {
      var isActive = b.dataset.status === state.status;
      b.setAttribute("aria-checked", isActive ? "true" : "false");
      if (isActive) {
        b.classList.add("bg-white", "text-ink", "shadow-sm");
        b.classList.remove("text-slate-500");
      } else {
        b.classList.remove("bg-white", "text-ink", "shadow-sm");
        b.classList.add("text-slate-500");
      }
    });
  }

  function setChipUI() {
    var chips = summaryChips.querySelectorAll(".chip");
    chips.forEach(function (c) {
      var active = state.activeChip === c.dataset.chip;
      c.setAttribute("aria-pressed", active ? "true" : "false");
      if (active) {
        c.classList.add("bg-coolant-tint", "border-coolant", "text-coolant-hover");
        c.classList.remove("bg-white", "text-slate-600");
      } else {
        c.classList.remove("bg-coolant-tint", "border-coolant", "text-coolant-hover");
        c.classList.add("bg-white", "text-slate-600");
      }
    });
  }

  function setLoading(isLoading) {
    state.loading = isLoading;
    skeletonWrap.classList.toggle("hidden", !isLoading);
    if (isLoading) {
      tableWrap.classList.add("hidden");
      cardsWrap.classList.add("hidden");
      emptyState.classList.add("hidden");
      errorState.classList.add("hidden");
    }
  }

  function renderHeaderCounts() {
    var openCount = state.rows.reduce(function (acc, r) {
      return acc + (r.reviewed_at ? 0 : 1);
    }, 0);
    openCountValue.textContent = String(openCount);

    var counts = { early_out: 0, missing_pair: 0, short_lunch: 0, long_lunch: 0, ip_mismatch: 0, other: 0 };
    state.rows.forEach(function (r) {
      counts[reasonCategory(r.flag_reason)] += 1;
    });
    state.reasonCounts = counts;
    Object.keys(counts).forEach(function (k) {
      var el = summaryChips.querySelector('[data-chip-count="' + k + '"]');
      if (el) el.textContent = String(counts[k]);
    });

    srCount.textContent =
      openCount + " open flagged " + (openCount === 1 ? "punch" : "punches") +
      ", " + state.visible.length + " shown.";
  }

  function applyChipFilter(rows) {
    if (!state.activeChip) return rows.slice();
    return rows.filter(function (r) {
      return reasonCategory(r.flag_reason) === state.activeChip;
    });
  }

  function locationCell(r) {
    var hasLat = r.gps_lat != null && r.gps_lat !== "";
    var hasLng = r.gps_lng != null && r.gps_lng !== "";
    if (!hasLat || !hasLng) {
      return '<span class="text-xs text-slate-400">—</span>';
    }
    var lat = encodeURIComponent(r.gps_lat);
    var lng = encodeURIComponent(r.gps_lng);
    var url = "https://www.google.com/maps?q=" + lat + "," + lng;
    var acc = r.gps_accuracy != null && r.gps_accuracy !== ""
      ? '<span class="num text-[11px] text-slate-400 ml-1">±' + esc(Math.round(Number(r.gps_accuracy))) + ' m</span>'
      : "";
    return '<a href="' + url + '" target="_blank" rel="noopener" class="text-xs font-semibold text-coolant hover:text-coolant-hover underline-offset-2 hover:underline focus:outline-none focus:ring-2 focus:ring-frost rounded">View map</a>' + acc;
  }

  function statusCell(r) {
    if (r.reviewed_at) {
      var by = r.reviewed_by_name ? esc(r.reviewed_by_name) : "";
      var at = r.reviewed_at ? esc(fmtDateTime(r.reviewed_at)) : "";
      var title = by || at ? (by + (by && at ? " · " : "") + at) : "";
      return '<span class="inline-block px-2 py-0.5 rounded-full bg-coolant-tint text-coolant-hover text-[11px] font-bold" title="' + title + '">Reviewed</span>';
    }
    return '<span class="inline-block px-2 py-0.5 rounded-full bg-amber-100 text-amber-800 text-[11px] font-bold">Open</span>';
  }

  function actionCell(r) {
    if (r.reviewed_at) return '<span class="text-xs text-slate-400">—</span>';
    return (
      '<button type="button" data-action="review" data-id="' + esc(r.id) + '" ' +
      'class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-line text-xs font-semibold text-coolant hover:bg-coolant-tint focus:outline-none focus:ring-2 focus:ring-frost transition-colors min-h-[36px]">' +
      '<svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="2.4" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" /></svg>' +
      'Mark reviewed</button>'
    );
  }

  function checkboxCell(r) {
    if (r.reviewed_at) return '<span class="inline-block w-4" aria-hidden="true"></span>';
    var checked = state.selected.has(String(r.id)) ? " checked" : "";
    return '<input type="checkbox" data-select-id="' + esc(r.id) + '" aria-label="Select flagged punch for ' + esc(r.full_name) + ' at ' + esc(fmtDateTime(r.punch_time)) + '" class="row-check w-4 h-4 rounded border-line text-coolant focus:ring-frost"' + checked + '>';
  }

  function renderTable() {
    tbody.replaceChildren();
    var rows = state.visible;

    var lastDate = null;
    rows.forEach(function (r) {
      if (r.work_date !== lastDate) {
        lastDate = r.work_date;
        var headTr = document.createElement("tr");
        headTr.className = "bg-ink-50/60";
        headTr.innerHTML =
          '<td colspan="10" class="px-4 py-2 text-[11px] font-bold uppercase tracking-wider text-slate-500 num">' +
          esc(fmtDate(r.work_date)) +
          "</td>";
        tbody.appendChild(headTr);
      }

      var tr = document.createElement("tr");
      tr.dataset.rowId = String(r.id);
      tr.className = "transition-colors";

      tr.innerHTML =
        '<td class="px-4 py-3 align-top">' + checkboxCell(r) + "</td>" +
        '<td class="px-4 py-3 align-top whitespace-nowrap"><p class="text-sm font-semibold text-slate-900">' + esc(r.full_name) + "</p></td>" +
        '<td class="px-4 py-3 align-top whitespace-nowrap"><span class="num text-xs text-slate-600">' + esc(fmtDate(r.work_date)) + "</span></td>" +
        '<td class="px-4 py-3 align-top whitespace-nowrap"><span class="text-xs font-semibold text-slate-700">' + esc(punchLabel(r.punch_type)) + "</span></td>" +
        '<td class="px-4 py-3 align-top whitespace-nowrap"><span class="num text-xs text-slate-700">' + esc(fmtTime(r.punch_time)) + "</span></td>" +
        '<td class="px-4 py-3 align-top"><span class="text-xs text-slate-700">' + esc(r.flag_reason) + "</span></td>" +
        '<td class="px-4 py-3 align-top whitespace-nowrap"><span class="num text-xs text-slate-600">' + esc(r.ip_address || "—") + "</span></td>" +
        '<td class="px-4 py-3 align-top whitespace-nowrap">' + locationCell(r) + "</td>" +
        '<td class="px-4 py-3 align-top whitespace-nowrap">' + statusCell(r) + "</td>" +
        '<td class="px-4 py-3 align-top text-right whitespace-nowrap">' + actionCell(r) + "</td>";

      tbody.appendChild(tr);
    });
  }

  function renderCards() {
    cardsWrap.replaceChildren();
    var rows = state.visible;
    var lastDate = null;

    rows.forEach(function (r) {
      if (r.work_date !== lastDate) {
        lastDate = r.work_date;
        var h = document.createElement("div");
        h.className = "pt-2 pb-1 px-1 text-[11px] font-bold uppercase tracking-wider text-slate-500 num";
        h.textContent = fmtDate(r.work_date);
        cardsWrap.appendChild(h);
      }

      var card = document.createElement("article");
      card.dataset.rowId = String(r.id);
      card.className = "bg-white rounded-2xl border border-line shadow-sm p-4";

      card.innerHTML =
        '<div class="flex items-start gap-3">' +
          '<div class="pt-0.5">' + checkboxCell(r) + "</div>" +
          '<div class="min-w-0 flex-1">' +
            '<div class="flex items-start justify-between gap-3">' +
              '<p class="text-sm font-semibold text-slate-900 truncate">' + esc(r.full_name) + "</p>" +
              statusCell(r) +
            "</div>" +
            '<p class="text-xs text-slate-500 mt-1"><span class="font-semibold text-slate-700">' + esc(punchLabel(r.punch_type)) + "</span> · <span class=\"num\">" + esc(fmtTime(r.punch_time)) + "</span></p>" +
            '<p class="text-xs text-slate-700 mt-2">' + esc(r.flag_reason) + "</p>" +
            '<dl class="mt-3 grid grid-cols-2 gap-x-3 gap-y-1 text-[11px]">' +
              '<dt class="text-slate-400 uppercase tracking-wider font-semibold">IP</dt>' +
              '<dd class="num text-slate-700">' + esc(r.ip_address || "—") + "</dd>" +
              '<dt class="text-slate-400 uppercase tracking-wider font-semibold">Location</dt>' +
              '<dd>' + locationCell(r) + "</dd>" +
            "</dl>" +
            '<div class="mt-3 pt-3 border-t border-line flex justify-end">' + actionCell(r) + "</div>" +
          "</div>" +
        "</div>";

      cardsWrap.appendChild(card);
    });
  }

  function renderBulkBar() {
    var n = state.selected.size;
    bulkCount.textContent = String(n);
    var show = n > 0;
    bulkBar.classList.toggle("translate-y-full", !show);
    bulkBar.classList.toggle("opacity-0", !show);
    bulkBar.setAttribute("aria-hidden", show ? "false" : "true");
  }

  function renderAll() {
    state.visible = applyChipFilter(state.rows);
    var isEmpty = !state.loading && state.visible.length === 0;

    tableWrap.classList.toggle("hidden", isEmpty || state.loading);
    cardsWrap.classList.toggle("hidden", isEmpty || state.loading);
    emptyState.classList.toggle("hidden", !isEmpty);

    if (!isEmpty && !state.loading) {
      renderTable();
      renderCards();
    }
    renderHeaderCounts();
    setSegmentUI();
    setChipUI();
    renderBulkBar();

    syncSelectAllBox();
  }

  function syncSelectAllBox() {
    if (!selectAllBox) return;
    var openVisible = state.visible.filter(function (r) { return !r.reviewed_at; });
    var allChecked = openVisible.length > 0 && openVisible.every(function (r) { return state.selected.has(String(r.id)); });
    var someChecked = openVisible.some(function (r) { return state.selected.has(String(r.id)); });
    selectAllBox.checked = allChecked;
    selectAllBox.indeterminate = !allChecked && someChecked;
    selectAllBox.disabled = openVisible.length === 0;
  }

  var lastFailedParams = null;
  var loadSeq = 0;

  async function loadRows(opts) {
    var seq = ++loadSeq;
    var silent = !!(opts && opts.silent);
    var params = currentParams();
    lastFailedParams = params;
    if (!silent) setLoading(true);
    errorState.classList.add("hidden");

    try {
      var data = await api.get("flagged-punches", "index", { params: params });
      if (seq !== loadSeq) return; 
      state.rows = Array.isArray(data) ? data : (data && Array.isArray(data.rows) ? data.rows : []);

      var stillOpen = new Set(state.rows.filter(function (r) { return !r.reviewed_at; }).map(function (r) { return String(r.id); }));
      Array.from(state.selected).forEach(function (id) {
        if (!stillOpen.has(id)) state.selected.delete(id);
      });
      setLoading(false);
      renderAll();
    } catch (err) {
      if(seq !== loadSeq) return;
      setLoading(false);
      tableWrap.classList.add("hidden");
      cardsWrap.classList.add("hidden");
      emptyState.classList.add("hidden");
      errorState.classList.remove("hidden");
      errorMessage.textContent = err && err.message ? err.message : "Unknown error.";
      showToast(err && err.message ? err.message : "Failed to load.", "error");
    }
  }

  async function loadEmployees() {
    try {
      var data = await api.get("employees", "index");
      if (!Array.isArray(data)) return;
      data.forEach(function (e) {
        var name = e && (e.name || e.full_name);
        if (!e || e.id == null || !name) return;
        var opt = document.createElement("option");
        opt.value = String(e.id);
        opt.textContent = String(name);
        employeeSelect.appendChild(opt);
      });
      if (state._pendingEmployee) {
        employeeSelect.value = state._pendingEmployee;
      }
    } catch (err) {
    }
  }

  function animateRowOut(rowId) {
    var reduce = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
    var selectors = [
      'tr[data-row-id="' + CSS.escape(String(rowId)) + '"]',
      'article[data-row-id="' + CSS.escape(String(rowId)) + '"]'
    ];
    selectors.forEach(function (sel) {
      var el = document.querySelector(sel);
      if (!el) return;
      if (reduce) {
        el.remove();
        return;
      }
      el.style.transition = "opacity 220ms ease, transform 220ms ease, max-height 260ms ease, margin 260ms ease, padding 260ms ease";
      el.style.overflow = "hidden";
      el.style.opacity = "0";
      el.style.transform = "translateY(-4px)";
      var h = el.offsetHeight;
      el.style.maxHeight = h + "px";
      requestAnimationFrame(function () {
        el.style.maxHeight = "0";
        el.style.paddingTop = "0";
        el.style.paddingBottom = "0";
        el.style.marginTop = "0";
        el.style.marginBottom = "0";
      });
      setTimeout(function () { if (el.parentNode) el.parentNode.removeChild(el); }, 280);
    });
  }

  async function reviewOne(id, buttonEl) {
    var row = state.rows.find(function (r) { return String(r.id) === String(id); });
    if (!row) return;

    if (buttonEl) {
      buttonEl.disabled = true;
      buttonEl.dataset.originalText = buttonEl.innerHTML;
      buttonEl.innerHTML =
        '<svg class="w-3.5 h-3.5 animate-spin" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"></path></svg> Reviewing…';
    }

    var snapshot = state.rows.slice();
    var idx = state.rows.findIndex(function (r) { return String(r.id) === String(id); });
    var removed = state.rows.splice(idx, 1)[0];
    state.selected.delete(String(id));

    var underOpenFilter = state.status === "open";
    if (underOpenFilter) {
      animateRowOut(id);
    } else {
      removed.reviewed_at = new Date().toISOString().replace("T", " ").slice(0, 19);
      removed.reviewed_by_name = removed.reviewed_by_name || "You";
    }

    renderHeaderCounts();
    syncSelectAllBox();

    try {
      await api.post("flagged-punches", "review", { id: id });
      await new Promise(function (r) { setTimeout(r, 300); });
      await loadRows();
      showToast("Marked as reviewed.", "success");
    } catch (err) {
      state.rows = snapshot;
      renderAll();
      var msg = err && err.message ? err.message : "Failed to mark reviewed.";
      showToast(msg, "error");
      if (/already|409/i.test(msg)) {
        loadRows();
      } else if (buttonEl) {
        buttonEl.disabled = false;
        if (buttonEl.dataset.originalText) buttonEl.innerHTML = buttonEl.dataset.originalText;
      }
    }
  }

  async function reviewBulk() {
    var ids = Array.from(state.selected);
    if (!ids.length) return;

    bulkReviewBtn.disabled = true;
    bulkReviewSpin.classList.remove("hidden");
    var oldLabel = bulkReviewLabel.textContent;
    bulkReviewLabel.textContent = "Reviewing…";

    var snapshot = state.rows.slice();
    var snapshotSel = new Set(state.selected);

    state.rows = state.rows.filter(function (r) { return !state.selected.has(String(r.id)); });
    var underOpenFilter = state.status === "open";
    if (underOpenFilter) {
      ids.forEach(function (id) { animateRowOut(id); });
    }
    state.selected.clear();
    renderHeaderCounts();
    renderBulkBar();
    syncSelectAllBox();

    try {
      await api.post("flagged-punches", "reviewBulk", { body: { ids: ids.map(Number) } });
      await new Promise(function (r) { setTimeout(r, 300); });
      await loadRows();
      showToast(ids.length + " flagged " + (ids.length === 1 ? "punch" : "punches") + " marked as reviewed.", "success");
    } catch (err) {
      state.rows = snapshot;
      state.selected = snapshotSel;
      renderAll();
      showToast(err && err.message ? err.message : "Bulk review failed.", "error");
    } finally {
      bulkReviewBtn.disabled = false;
      bulkReviewSpin.classList.add("hidden");
      bulkReviewLabel.textContent = oldLabel;
    }
  }

  statusSegment.addEventListener("click", function (e) {
    var btn = e.target.closest(".seg-btn");
    if (!btn) return;
    var s = btn.dataset.status;
    if (s === state.status) return;
    state.status = s;
    setSegmentUI();
    writeUrlState();
    loadRows();
  });

  var onFilterChange = debounce(function () {
    writeUrlState();
    loadRows();
  }, 250);

  dateFromInput.addEventListener("change", onFilterChange);
  dateToInput.addEventListener("change", onFilterChange);
  employeeSelect.addEventListener("change", onFilterChange);

  resetBtn.addEventListener("click", function () {
    var today = new Date();
    state.status = "open";
    state.activeChip = null;
    dateFromInput.value = isoDate(addDays(today, -13));
    dateToInput.value = isoDate(today);
    employeeSelect.value = "";
    setSegmentUI();
    setChipUI();
    writeUrlState();
    loadRows();
  });

  summaryChips.addEventListener("click", function (e) {
    var chip = e.target.closest(".chip");
    if (!chip) return;
    var key = chip.dataset.chip;
    state.activeChip = state.activeChip === key ? null : key;
    writeUrlState();
    renderAll();
  });

  selectAllBox.addEventListener("change", function () {
    var checked = selectAllBox.checked;
    state.visible.forEach(function (r) {
      if (r.reviewed_at) return;
      if (checked) state.selected.add(String(r.id));
      else state.selected.delete(String(r.id));
    });
    tbody.querySelectorAll("input.row-check").forEach(function (cb) {
      cb.checked = state.selected.has(cb.dataset.selectId);
    });
    cardsWrap.querySelectorAll("input.row-check").forEach(function (cb) {
      cb.checked = state.selected.has(cb.dataset.selectId);
    });
    renderBulkBar();
  });

  function onRowCheck(e) {
    var cb = e.target.closest("input.row-check");
    if (!cb) return;
    var id = cb.dataset.selectId;
    if (cb.checked) state.selected.add(id);
    else state.selected.delete(id);
    document.querySelectorAll('input.row-check[data-select-id="' + CSS.escape(String(id)) + '"]').forEach(function (other) {
      other.checked = cb.checked;
    });
    renderBulkBar();
    syncSelectAllBox();
  }
  tbody.addEventListener("change", onRowCheck);
  cardsWrap.addEventListener("change", onRowCheck);

  function onRowAction(e) {
    var btn = e.target.closest('button[data-action="review"]');
    if (!btn) return;
    reviewOne(btn.dataset.id, btn);
  }
  tbody.addEventListener("click", onRowAction);
  cardsWrap.addEventListener("click", onRowAction);

  bulkReviewBtn.addEventListener("click", reviewBulk);
  bulkClearBtn.addEventListener("click", function () {
    state.selected.clear();
    tbody.querySelectorAll("input.row-check").forEach(function (cb) { cb.checked = false; });
    cardsWrap.querySelectorAll("input.row-check").forEach(function (cb) { cb.checked = false; });
    if (selectAllBox) { selectAllBox.checked = false; selectAllBox.indeterminate = false; }
    renderBulkBar();
  });

  retryBtn.addEventListener("click", function () {
    if (lastFailedParams) loadRows();
  });

  readUrlState();
  setSegmentUI();
  setChipUI();
  setLoading(true);

  loadEmployees().then(function () {
    if (state._pendingEmployee) employeeSelect.value = state._pendingEmployee;
    loadRows();
  });
})();