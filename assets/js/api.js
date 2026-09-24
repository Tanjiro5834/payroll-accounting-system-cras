window.api = (function () {
  "use strict";
  const token = document.querySelector('meta[name="csrf-token"]')?.content || "";

  async function request(page, action, { id, params, method = "GET", body } = {}) {
    const qs = new URLSearchParams({ page, action, ...(id != null && { id }), ...params });
    const res = await fetch("index.php?" + qs, {
      method,
      credentials: "same-origin",
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        ...(method !== "GET" && { "X-CSRF-TOKEN": token }),
        ...(body && !(body instanceof FormData) && { "Content-Type": "application/json" }),
      },
      body: body instanceof FormData ? body : body && JSON.stringify(body),
    });
    if (res.status === 401) { location.href = "index.php?page=login"; throw new Error("Session expired"); }
    const data = await res.json().catch(() => ({}));
    if (!res.ok) throw new Error(data.error || "Request failed (" + res.status + ")");
    return data;
  }

  return {
    get: (page, action, opts) => request(page, action, { ...opts, method: "GET" }),
    post: (page, action, opts) => request(page, action, { ...opts, method: "POST" }),
  };
})();