(function () {
  var tbody = document.getElementById("tbody-usuarios");
  var form = document.getElementById("form-usuario");
  var errEl = document.getElementById("msg-error");
  var okEl = document.getElementById("msg-ok");
  var selPerfil = document.getElementById("nuevo-perfil");
  var btnLogout = document.getElementById("btn-logout");

  function showErr(msg) {
    okEl.classList.add("hidden");
    errEl.textContent = msg;
    errEl.classList.remove("hidden");
  }
  function showOk(msg) {
    errEl.classList.add("hidden");
    okEl.textContent = msg;
    okEl.classList.remove("hidden");
  }

  function loadPerfiles() {
    return InventarioApp.apiFetch("perfiles.php").then(function (r) {
      selPerfil.innerHTML = "";
      r.perfiles.forEach(function (p) {
        var o = document.createElement("option");
        o.value = p.id;
        o.textContent = p.nombre;
        selPerfil.appendChild(o);
      });
    });
  }

  function renderRows(list) {
    tbody.innerHTML = "";
    list.forEach(function (u) {
      var tr = document.createElement("tr");
      var activo = Number(u.activo) === 1;
      tr.innerHTML =
        "<td>" +
        escapeHtml(u.username) +
        "</td>" +
        "<td>" +
        escapeHtml(u.email) +
        "</td>" +
        "<td><span class=\"badge " +
        InventarioApp.perfilBadgeClass(u.perfil_nombre) +
        "\">" +
        escapeHtml(u.perfil_nombre) +
        "</span></td>" +
        "<td>" +
        (activo ? "<span class=\"badge supervisor\">Activo</span>" : "<span class=\"badge off\">Inactivo</span>") +
        "</td>" +
        "<td class=\"row-actions\">" +
        "<button type=\"button\" class=\"secondary\" data-act=\"" +
        u.id +
        "\" data-next=\"" +
        (activo ? "0" : "1") +
        "\">" +
        (activo ? "Desactivar" : "Activar") +
        "</button>" +
        "<button type=\"button\" class=\"danger\" data-del=\"" +
        u.id +
        "\">Eliminar</button>" +
        "</td>";
      tbody.appendChild(tr);
    });

    tbody.querySelectorAll("button[data-act]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        var id = Number(btn.getAttribute("data-act"));
        var next = Number(btn.getAttribute("data-next"));
        InventarioApp.apiFetch("usuarios.php", {
          method: "PATCH",
          body: JSON.stringify({ id: id, activo: next === 1 }),
        })
          .then(function () {
            showOk(next ? "Usuario activado." : "Usuario desactivado.");
            return loadUsuarios();
          })
          .catch(function (e) {
            showErr(e.message);
          });
      });
    });

    tbody.querySelectorAll("button[data-del]").forEach(function (btn) {
      btn.addEventListener("click", function () {
        if (!confirm("¿Eliminar este usuario de forma permanente?")) return;
        var id = Number(btn.getAttribute("data-del"));
        InventarioApp.apiFetch("usuarios.php", {
          method: "DELETE",
          body: JSON.stringify({ id: id }),
        })
          .then(function () {
            showOk("Usuario eliminado.");
            return loadUsuarios();
          })
          .catch(function (e) {
            showErr(e.message);
          });
      });
    });
  }

  function escapeHtml(s) {
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function loadUsuarios() {
    return InventarioApp.apiFetch("usuarios.php").then(function (r) {
      renderRows(r.usuarios);
    });
  }

  InventarioApp.requireAuth().then(function (user) {
    if (!user) return;
    if (user.perfil_id !== 1) {
      window.location.href = "panel.html";
      return;
    }
    document.getElementById("user-name").textContent = user.username;
    loadPerfiles()
      .then(loadUsuarios)
      .catch(function (e) {
        showErr(e.message);
      });
  });

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    errEl.classList.add("hidden");
    var body = {
      username: document.getElementById("nuevo-user").value.trim(),
      email: document.getElementById("nuevo-email").value.trim(),
      password: document.getElementById("nuevo-pass").value,
      perfil_id: Number(selPerfil.value),
    };
    InventarioApp.apiFetch("usuarios.php", { method: "POST", body: JSON.stringify(body) })
      .then(function () {
        form.reset();
        showOk("Usuario creado.");
        return loadUsuarios();
      })
      .catch(function (err) {
        showErr(err.message);
      });
  });

  btnLogout.addEventListener("click", function () {
    InventarioApp.logout();
  });
})();
