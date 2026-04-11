(function () {
  var errEl = document.getElementById("msg-error");
  var okEl = document.getElementById("msg-ok");
  var tbody = document.getElementById("tbody-reservas");
  var formRes = document.getElementById("form-reserva");
  var formCfg = document.getElementById("form-config");
  var cardCfg = document.getElementById("card-config-admin");
  var textoCond = document.getElementById("texto-condiciones");
  var labelAnt = document.getElementById("label-anticipacion");
  var filtroDesde = document.getElementById("filtro-desde");
  var filtroHasta = document.getElementById("filtro-hasta");
  var btnRefrescar = document.getElementById("btn-refrescar");
  var linkUsuarios = document.getElementById("nav-usuarios");
  var btnLogout = document.getElementById("btn-logout");

  var currentUser = null;
  var anticipacionHoras = 48;

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

  function fmtDateTime(iso) {
    if (!iso) return "—";
    var d = new Date(iso.replace(" ", "T"));
    if (isNaN(d.getTime())) return escapeHtml(iso);
    return escapeHtml(
      d.toLocaleString("es-CO", {
        dateStyle: "short",
        timeStyle: "short",
      })
    );
  }

  function toDatetimeLocalValue(d) {
    var pad = function (n) {
      return n < 10 ? "0" + n : String(n);
    };
    return (
      d.getFullYear() +
      "-" +
      pad(d.getMonth() + 1) +
      "-" +
      pad(d.getDate()) +
      "T" +
      pad(d.getHours()) +
      ":" +
      pad(d.getMinutes())
    );
  }

  function setMinInicioReserva() {
    var min = new Date();
    min.setHours(min.getHours() + anticipacionHoras);
    var el = document.getElementById("res-inicio");
    el.min = toDatetimeLocalValue(min);
    var fin = document.getElementById("res-fin");
    if (!fin.min || fin.min < el.min) fin.min = el.min;
  }

  function loadConfig() {
    return InventarioApp.apiFetch("salon_config.php").then(function (r) {
      var c = r.config;
      anticipacionHoras = Number(c.anticipacion_horas) || 48;
      textoCond.textContent =
        c.texto_condiciones && String(c.texto_condiciones).trim()
          ? c.texto_condiciones
          : "Consulte a administración las condiciones de uso del salón.";
      labelAnt.textContent =
        "Las nuevas reservas deben hacerse con al menos " +
        anticipacionHoras +
        " horas de anticipación.";
      if (r.es_admin) {
        cardCfg.classList.remove("hidden");
        document.getElementById("cfg-horas").value = String(anticipacionHoras);
        document.getElementById("cfg-texto").value = c.texto_condiciones || "";
      }
      setMinInicioReserva();
    });
  }

  function defaultFiltroFechas() {
    var a = new Date();
    filtroDesde.value = a.toISOString().slice(0, 10);
    var b = new Date();
    b.setDate(b.getDate() + 60);
    filtroHasta.value = b.toISOString().slice(0, 10);
  }

  function loadReservas() {
    var d = filtroDesde.value;
    var h = filtroHasta.value;
    var q = "reservas.php";
    if (d) q += "?desde=" + encodeURIComponent(d + " 00:00:00");
    if (h) q += (q.indexOf("?") === -1 ? "?" : "&") + "hasta=" + encodeURIComponent(h + " 23:59:59");
    return InventarioApp.apiFetch(q).then(function (r) {
      if (typeof r.anticipacion_horas === "number") {
        anticipacionHoras = r.anticipacion_horas;
        labelAnt.textContent =
          "Las nuevas reservas deben hacerse con al menos " +
          anticipacionHoras +
          " horas de anticipación.";
        setMinInicioReserva();
      }
      tbody.innerHTML = "";
      r.reservas.forEach(function (x) {
        var tr = document.createElement("tr");
        var acciones = "";
        var puedeGestionarReservas = InventarioApp.puedeGestionarReservas(currentUser);
        var esMia = currentUser && Number(x.usuario_id) === Number(currentUser.id);
        var estNorm = String(x.estado != null ? x.estado : "")
          .trim()
          .toLowerCase();
        if (puedeGestionarReservas && estNorm === "pendiente") {
          acciones +=
            '<button type="button" data-acc="confirmar" data-id="' +
            x.id +
            '">Confirmar</button> ';
          acciones +=
            '<button type="button" class="secondary" data-acc="rechazar" data-id="' +
            x.id +
            '">Rechazar</button> ';
        }
        if (puedeGestionarReservas && (estNorm === "pendiente" || estNorm === "confirmada")) {
          acciones +=
            '<button type="button" class="danger" data-acc="cancelar-admin" data-id="' +
            x.id +
            '">Cancelar</button> ';
        }
        if (!puedeGestionarReservas && esMia && estNorm === "pendiente") {
          acciones +=
            '<button type="button" class="danger" data-acc="cancelar-user" data-id="' +
            x.id +
            '">Cancelar solicitud</button>';
        }
        tr.innerHTML =
          "<td>" +
          fmtDateTime(x.inicio) +
          "</td>" +
          "<td>" +
          fmtDateTime(x.fin) +
          "</td>" +
          "<td>" +
          escapeHtml(x.usuario_username || "") +
          "</td>" +
          "<td>" +
          escapeHtml(x.motivo || "") +
          "</td>" +
          "<td>" +
          escapeHtml(String(x.estado != null ? x.estado : "")) +
          "</td>" +
          "<td>" +
          escapeHtml(x.notas_admin || "—") +
          "</td>" +
          '<td class="row-actions">' +
          acciones +
          "</td>";
        tbody.appendChild(tr);
      });
      tbody.querySelectorAll("button[data-acc]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var id = Number(btn.getAttribute("data-id"));
          var acc = btn.getAttribute("data-acc");
          if (acc === "confirmar") {
            if (!confirm("¿Confirmar esta reserva?")) return;
            InventarioApp.apiFetch("reservas.php", {
              method: "PATCH",
              body: JSON.stringify({ id: id, estado: "confirmada" }),
            })
              .then(function () {
                showOk("Reserva confirmada.");
                return loadReservas();
              })
              .catch(function (e) {
                showErr(e.message);
              });
          } else if (acc === "rechazar") {
            var notas = window.prompt("Motivo del rechazo (opcional, visible en notas):", "") || "";
            InventarioApp.apiFetch("reservas.php", {
              method: "PATCH",
              body: JSON.stringify({ id: id, estado: "rechazada", notas_admin: notas }),
            })
              .then(function () {
                showOk("Reserva rechazada.");
                return loadReservas();
              })
              .catch(function (e) {
                showErr(e.message);
              });
          } else if (acc === "cancelar-admin") {
            if (!confirm("¿Cancelar esta reserva?")) return;
            InventarioApp.apiFetch("reservas.php", {
              method: "PATCH",
              body: JSON.stringify({ id: id, estado: "cancelada" }),
            })
              .then(function () {
                showOk("Reserva cancelada.");
                return loadReservas();
              })
              .catch(function (e) {
                showErr(e.message);
              });
          } else if (acc === "cancelar-user") {
            if (!confirm("¿Cancelar su solicitud de reserva?")) return;
            InventarioApp.apiFetch("reservas.php", {
              method: "PATCH",
              body: JSON.stringify({ id: id, estado: "cancelada" }),
            })
              .then(function () {
                showOk("Solicitud cancelada.");
                return loadReservas();
              })
              .catch(function (e) {
                showErr(e.message);
              });
          }
        });
      });
    });
  }

  InventarioApp.requireAuth().then(function (user) {
    if (!user) return;
    currentUser = user;
    document.getElementById("user-name").textContent = user.username;
    if (InventarioApp.puedeListarUsuarios(user)) linkUsuarios.classList.remove("hidden");
    defaultFiltroFechas();
    loadConfig()
      .then(loadReservas)
      .catch(function (e) {
        showErr(e.message);
      });
  });

  formRes.addEventListener("submit", function (e) {
    e.preventDefault();
    var ini = document.getElementById("res-inicio").value;
    var fin = document.getElementById("res-fin").value;
    var body = {
      inicio: ini.replace("T", " ") + ":00",
      fin: fin.replace("T", " ") + ":00",
      motivo: document.getElementById("res-motivo").value.trim(),
    };
    InventarioApp.apiFetch("reservas.php", { method: "POST", body: JSON.stringify(body) })
      .then(function () {
        formRes.reset();
        showOk("Solicitud registrada. Espere confirmación de administración o supervisión.");
        return loadReservas();
      })
      .catch(function (err) {
        showErr(err.message);
      });
  });

  formCfg.addEventListener("submit", function (e) {
    e.preventDefault();
    var h = Number(document.getElementById("cfg-horas").value);
    var body = {
      anticipacion_horas: h,
      texto_condiciones: document.getElementById("cfg-texto").value.trim(),
    };
    InventarioApp.apiFetch("salon_config.php", { method: "PATCH", body: JSON.stringify(body) })
      .then(function (r) {
        anticipacionHoras = Number(r.config.anticipacion_horas);
        textoCond.textContent = r.config.texto_condiciones || textoCond.textContent;
        labelAnt.textContent =
          "Las nuevas reservas deben hacerse con al menos " +
          anticipacionHoras +
          " horas de anticipación.";
        setMinInicioReserva();
        showOk("Política del salón actualizada.");
      })
      .catch(function (err) {
        showErr(err.message);
      });
  });

  document.getElementById("res-inicio").addEventListener("change", function () {
    var fin = document.getElementById("res-fin");
    if (!fin.value || fin.value < document.getElementById("res-inicio").value) {
      fin.value = document.getElementById("res-inicio").value;
    }
    fin.min = document.getElementById("res-inicio").value;
  });

  btnRefrescar.addEventListener("click", function () {
    loadReservas().catch(function (e) {
      showErr(e.message);
    });
  });

  filtroDesde.addEventListener("change", function () {
    loadReservas().catch(function (e) {
      showErr(e.message);
    });
  });
  filtroHasta.addEventListener("change", function () {
    loadReservas().catch(function (e) {
      showErr(e.message);
    });
  });

  btnLogout.addEventListener("click", function () {
    InventarioApp.logout();
  });
})();
