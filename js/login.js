(function () {
  var form = document.getElementById("form-login");
  var errEl = document.getElementById("msg-error");

  InventarioApp.getSession().then(function (r) {
    if (r.user) window.location.href = "panel.html";
  });

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    errEl.classList.add("hidden");
    errEl.textContent = "";
    var username = document.getElementById("username").value.trim();
    var password = document.getElementById("password").value;
    InventarioApp.apiFetch("auth.php?action=login", {
      method: "POST",
      body: JSON.stringify({ username: username, password: password }),
    })
      .then(function () {
        window.location.href = "panel.html";
      })
      .catch(function (err) {
        errEl.textContent = err.message || "Error al iniciar sesión";
        errEl.classList.remove("hidden");
      });
  });
})();
