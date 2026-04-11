(function () {
  var form = document.getElementById("form-registro");
  var errEl = document.getElementById("msg-error");
  var okEl = document.getElementById("msg-ok");

  InventarioApp.getSession().then(function (r) {
    if (r.user) window.location.href = "panel.html";
  });

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    errEl.classList.add("hidden");
    okEl.classList.add("hidden");
    var username = document.getElementById("reg-username").value.trim();
    var email = document.getElementById("reg-email").value.trim();
    var password = document.getElementById("reg-password").value;
    var password2 = document.getElementById("reg-password2").value;
    InventarioApp.apiFetch("registro.php", {
      method: "POST",
      body: JSON.stringify({
        username: username,
        email: email,
        password: password,
        password_confirm: password2,
      }),
    })
      .then(function (res) {
        okEl.textContent = res.mensaje || "Cuenta creada.";
        okEl.classList.remove("hidden");
        return InventarioApp.apiFetch("auth.php?action=login", {
          method: "POST",
          body: JSON.stringify({ username: username, password: password }),
        });
      })
      .then(function () {
        window.location.href = "panel.html";
      })
      .catch(function (err) {
        errEl.textContent = err.message || "No se pudo completar el registro";
        errEl.classList.remove("hidden");
      });
  });
})();
