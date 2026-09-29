(function () {
    "use strict";

    try {
        var prefersReduced = window.matchMedia(
            "(prefers-reduced-motion: reduce)"
        ).matches;

        function initDrawer() {
            var sidebar   = document.getElementById("sidebar");
            var overlay   = document.getElementById("drawer-overlay");
            var btn       = document.getElementById("hamburger-btn");
            var iconMenu  = document.getElementById("icon-menu");
            var iconClose = document.getElementById("icon-close");
            if (!sidebar || !btn) return; 

            var LG = window.matchMedia("(min-width: 1024px)");
            var lastFocused = null;

            function isOpen() {
                return sidebar.classList.contains("ui-drawer-open");
            }

            function open() {
                if (isOpen()) return;
                lastFocused = document.activeElement;
                sidebar.classList.add("ui-drawer-open");
                sidebar.classList.remove("-translate-x-full");
                sidebar.style.transform = "translateX(0)";
                if (overlay) {
                    overlay.classList.remove("opacity-0", "pointer-events-none");
                    overlay.classList.add("opacity-100");
                    overlay.style.opacity = "1";
                    overlay.style.pointerEvents = "auto";
                }
                document.body.classList.add("ui-drawer-locked");
                btn.setAttribute("aria-expanded", "true");
                if (iconMenu)  iconMenu.classList.add("hidden");
                if (iconClose) iconClose.classList.remove("hidden");
                var focusable = sidebar.querySelector(
                    'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])'
                );
                if (focusable) focusable.focus();
            }

            function close(returnFocus) {
                if (!isOpen()) return;
                sidebar.classList.remove("ui-drawer-open");
                sidebar.style.transform = "";
                sidebar.classList.add("-translate-x-full");
                if (overlay) {
                    overlay.classList.add("opacity-0", "pointer-events-none");
                    overlay.classList.remove("opacity-100");
                    overlay.style.opacity = "0";
                    overlay.style.pointerEvents = "none";
                }
                document.body.classList.remove("ui-drawer-locked");
                btn.setAttribute("aria-expanded", "false");
                if (iconMenu)  iconMenu.classList.remove("hidden");
                if (iconClose) iconClose.classList.add("hidden");
                if (returnFocus && lastFocused && lastFocused.focus) {
                    lastFocused.focus();
                }
            }

            btn.addEventListener("click", function () {
                isOpen() ? close(true) : open();
            });

            if (overlay) {
                overlay.addEventListener("click", function () { close(true); });
            }

            document.addEventListener("keydown", function (e) {
                if (e.key === "Escape" && isOpen()) {
                    e.preventDefault();
                    close(true);
                }
                if (e.key === "Tab" && isOpen()) {
                    var focusables = sidebar.querySelectorAll(
                        'a[href], button:not([disabled]), input, select, textarea, [tabindex]:not([tabindex="-1"])'
                    );
                    if (!focusables.length) return;
                    var first = focusables[0];
                    var last  = focusables[focusables.length - 1];
                    if (e.shiftKey && document.activeElement === first) {
                        e.preventDefault(); last.focus();
                    } else if (!e.shiftKey && document.activeElement === last) {
                        e.preventDefault(); first.focus();
                    }
                }
            });

            sidebar.querySelectorAll('a[href^="index.php?page="]').forEach(function (a) {
                a.addEventListener("click", function () {
                    if (isOpen()) close(false);
                });
            });

            function onResize() {
                if (LG.matches && isOpen()) close(false);
            }
            if (LG.addEventListener) LG.addEventListener("change", onResize);
            else if (LG.addListener) LG.addListener(onResize);
        }

        function initPageExit() {
            if (prefersReduced) return;
            var supportsVT = "startViewTransition" in document;

            document.addEventListener("click", function (e) {
                var a = e.target.closest && e.target.closest("a");
                if (!a) return;
                var href = a.getAttribute("href") || "";
                if (!href) return;
                if (href.charAt(0) === "#") return;
                if (a.target === "_blank") return;
                if (a.hasAttribute("download")) return;
                if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
                if (e.defaultPrevented) return;

                var isSameOrigin = true;
                try {
                    var url = new URL(a.href, window.location.href);
                    isSameOrigin = url.origin === window.location.origin;
                    if (!isSameOrigin) return;
                    if (!/index\.php/.test(url.pathname + url.search)) return;
                } catch (err) { return; }

                if (supportsVT) return;

                e.preventDefault();
                var main = document.querySelector("main") || document.body;
                main.classList.add("ui-exit");
                setTimeout(function () {
                    window.location.href = a.href;
                }, 170);
            });
        }

        function boot() {
            initDrawer();
            initPageExit();
        }

        if (document.readyState === "loading") {
            document.addEventListener("DOMContentLoaded", boot);
        } else {
            boot();
        }
    } catch (err) {}
})();