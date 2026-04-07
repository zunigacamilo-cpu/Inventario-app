(function () {
  var errEl = document.getElementById("msg-error");
  var okEl = document.getElementById("msg-ok");
  var lista = document.getElementById("lista-comunicados");
  var form = document.getElementById("form-comunicado");
  var cardNuevo = document.getElementById("card-nuevo-aviso");
  var linkUsuarios = document.getElementById("nav-usuarios");
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

  function escapeHtml(s) {
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function fmtFecha(iso) {
    var d = new Date(String(iso).replace(" ", "T"));
    if (isNaN(d.getTime())) return escapeHtml(iso || "");
    return escapeHtml(
      d.toLocaleString("es-CO", { dateStyle: "medium", timeStyle: "short" })
    );
  }

  function cargar() {
    return InventarioApp.apiFetch("comunicados.php?limite=80").then(function (r) {
      lista.innerHTML = "";
      if (!r.comunicados || r.comunicados.length === 0) {
        lista.innerHTML =
          '<li class="user-meta">No hay avisos publicados todavía.</li>';
        return;
      }
      r.comunicados.forEach(function (c) {
        var li = document.createElement("li");
        li.className = "item-comunicado";
        li.innerHTML =
          '<div class="item-comunicado__meta">' +
          fmtFecha(c.creado_en) +
          " · " +
          escapeHtml(c.autor_username || "") +
          "</div>" +
          "<h3 class=\"item-comunicado__titulo\">" +
          escapeHtml(c.titulo) +
          "</h3>" +
          '<div class="item-comunicado__cuerpo">' +
          escapeHtml(c.cuerpo).replace(/\n/g, "<br />") +
          "</div>";
        lista.appendChild(li);
      });
    });
  }

  InventarioApp.requireAuth().then(function (user) {
    if (!user) return;
    document.getElementById("user-name").textContent = user.username;
    if (user.perfil_id === 1) {
      linkUsuarios.classList.remove("hidden");
      cardNuevo.classList.remove("hidden");
    }
    cargar().catch(function (e) {
      showErr(e.message);
    });
  });

  form.addEventListener("submit", function (e) {
    e.preventDefault();
    var body = {
      titulo: document.getElementById("com-titulo").value.trim(),
      cuerpo: document.getElementById("com-cuerpo").value.trim(),
    };
    InventarioApp.apiFetch("comunicados.php", { method: "POST", body: JSON.stringify(body) })
      .then(function () {
        form.reset();
        showOk("Aviso publicado.");
        return cargar();
      })
      .catch(function (err) {
        showErr(err.message);
      });
  });

  btnLogout.addEventListener("click", function () {
    InventarioApp.logout();
  });
})();
