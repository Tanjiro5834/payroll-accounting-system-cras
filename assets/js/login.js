(function () {
  "use strict";

  const toggleBtn = document.getElementById("toggle-password");
  const passwordInput = document.getElementById("password");
  const iconEye = document.getElementById("icon-eye");
  const iconEyeOff = document.getElementById("icon-eye-off");

  toggleBtn.addEventListener("click", function () {
    const isPassword = passwordInput.type === "password";
    passwordInput.type = isPassword ? "text" : "password";
    iconEye.classList.toggle("hidden", isPassword);
    iconEyeOff.classList.toggle("hidden", !isPassword);
    toggleBtn.setAttribute(
      "aria-label",
      isPassword ? "Hide password" : "Show password",
    );
  });
})();
