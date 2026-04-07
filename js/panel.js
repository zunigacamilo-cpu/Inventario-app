(function () {
  var userSpan = document.getElementById("user-name");
  var perfilSpan = document.getElementById("user-perfil");
  var linkUsuarios = document.getElementById("nav-usuarios");
  var btnLogout = document.getElementById("btn-logout");
  var avisosUl = document.getElementById("avisos-recientes");

  function escapeHtml(s) {
    var d = document.createElement("div");
    d.textContent = s;
    return d.innerHTML;
  }

  function cargarAvisos() {
    if (!avisosUl) return;
    InventarioApp.apiFetch("comunicados.php?limite=4")
      .then(function (r) {
        avisosUl.innerHTML = "";
        if (!r.comunicados || r.comunicados.length === 0) {
          avisosUl.innerHTML = "<li>No hay avisos publicados.</li>";
          return;
        }
        r.comunicados.forEach(function (c) {
          var li = document.createElement("li");
          var texto = (c.cuerpo || "").slice(0, 120);
          li.innerHTML =
            "<a href=\"comunicados.html\">" + escapeHtml(c.titulo) + "</a> — " + escapeHtml(texto);
          if ((c.cuerpo || "").length > 120) li.innerHTML += "…";
          avisosUl.appendChild(li);
        });
      })
      .catch(function () {
        avisosUl.innerHTML = "<li>No se pudieron cargar los avisos.</li>";
      });
  }

  InventarioApp.requireAuth().then(function (user) {
    if (!user) return;
    userSpan.textContent = user.username;
    perfilSpan.textContent = user.perfil_nombre;
    perfilSpan.className = "badge " + InventarioApp.perfilBadgeClass(user.perfil_nombre);
    if (user.perfil_id === 1) {
      linkUsuarios.classList.remove("hidden");
    }
    cargarAvisos();
  });

  btnLogout.addEventListener("click", function () {
    InventarioApp.logout();
  });
})();
