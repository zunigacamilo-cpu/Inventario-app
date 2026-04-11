(function () {
  var tbodyIns = document.getElementById("tbody-insumos");
  var tbodyRel = document.getElementById("tbody-relaciones");
  var formIns = document.getElementById("form-insumo");
  var formRel = document.getElementById("form-relacion");
  var cardCat = document.getElementById("card-categoria");
  var formCat = document.getElementById("form-categoria");
  var selCat = document.getElementById("ins-categoria");
  var selOrigen = document.getElementById("rel-origen");
  var selDestino = document.getElementById("rel-destino");
  var selTipo = document.getElementById("rel-tipo");
  var filtroRel = document.getElementById("filtro-insumo-rel");
  var errEl = document.getElementById("msg-error");
  var okEl = document.getElementById("msg-ok");
  var btnLogout = document.getElementById("btn-logout");
  var linkUsuarios = document.getElementById("nav-usuarios");
  /** Admin o supervisor: altas de insumo, relaciones y bajas. */
  var puedeGestionarInsumos = false;
  /** Solo admin: categorías nuevas (coincide con categorias.php POST). */
  var esAdmin = false;

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

  function fillSelect(select, items, valueKey, labelFn, emptyLabel) {
    select.innerHTML = "";
    if (emptyLabel) {
      var z = document.createElement("option");
      z.value = "";
      z.textContent = emptyLabel;
      select.appendChild(z);
    }
    items.forEach(function (it) {
      var o = document.createElement("option");
      o.value = String(it[valueKey]);
      o.textContent = labelFn(it);
      select.appendChild(o);
    });
  }

  function loadCategorias() {
    return InventarioApp.apiFetch("categorias.php").then(function (r) {
      fillSelect(selCat, r.categorias, "id", function (c) {
        return c.nombre;
      }, "— Sin categoría —");
    });
  }

  function loadInsumosIntoSelects(allowEdit) {
    var allow = !!allowEdit;
    return InventarioApp.apiFetch("insumos.php").then(function (r) {
      var list = r.insumos;
      fillSelect(selOrigen, list, "id", function (i) {
        return i.codigo + " — " + i.nombre;
      }, null);
      fillSelect(selDestino, list, "id", function (i) {
        return i.codigo + " — " + i.nombre;
      }, null);
      fillSelect(filtroRel, list, "id", function (i) {
        return i.codigo + " — " + i.nombre;
      }, "Todas las relaciones");

      tbodyIns.innerHTML = "";
      list.forEach(function (i) {
        var tr = document.createElement("tr");
        tr.innerHTML =
          "<td>" +
          escapeHtml(i.codigo) +
          "</td>" +
          "<td>" +
          escapeHtml(i.nombre) +
          "</td>" +
          "<td>" +
          escapeHtml(i.categoria_nombre || "—") +
          "</td>" +
          "<td>" +
          escapeHtml(String(i.stock_actual)) +
          " " +
          escapeHtml(i.unidad_medida) +
          "</td>" +
          "<td class=\"row-actions\">" +
          (allow
            ? "<button type=\"button\" class=\"danger\" data-del-ins=\"" + i.id + "\">Eliminar</button>"
            : "—") +
          "</td>";
        tbodyIns.appendChild(tr);
      });

      tbodyIns.querySelectorAll("button[data-del-ins]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          if (!confirm("¿Eliminar insumo y sus relaciones asociadas?")) return;
          var id = Number(btn.getAttribute("data-del-ins"));
          InventarioApp.apiFetch("insumos.php", {
            method: "DELETE",
            body: JSON.stringify({ id: id }),
          })
            .then(function () {
              showOk("Insumo eliminado.");
              return refreshAll();
            })
            .catch(function (e) {
              showErr(e.message);
            });
        });
      });
    });
  }

  function loadRelaciones(allowEdit) {
    var allow = !!allowEdit;
    var q = filtroRel.value ? "relaciones.php?insumo_id=" + encodeURIComponent(filtroRel.value) : "relaciones.php";
    return InventarioApp.apiFetch(q).then(function (r) {
      if (r.tipos_validos && selTipo.options.length === 0) {
        r.tipos_validos.forEach(function (t) {
          var o = document.createElement("option");
          o.value = t;
          o.textContent = t.replace(/_/g, " ");
          selTipo.appendChild(o);
        });
      }
      tbodyRel.innerHTML = "";
      r.relaciones.forEach(function (x) {
        var tr = document.createElement("tr");
        tr.innerHTML =
          "<td>" +
          escapeHtml(x.origen_codigo) +
          "</td>" +
          "<td>" +
          escapeHtml(x.destino_codigo) +
          "</td>" +
          "<td>" +
          escapeHtml(x.tipo_relacion) +
          "</td>" +
          "<td>" +
          (x.cantidad_referencia != null ? escapeHtml(String(x.cantidad_referencia)) : "—") +
          "</td>" +
          "<td class=\"row-actions\">" +
          (allow
            ? "<button type=\"button\" class=\"danger\" data-del-rel=\"" + x.id + "\">Quitar</button>"
            : "—") +
          "</td>";
        tbodyRel.appendChild(tr);
      });
      tbodyRel.querySelectorAll("button[data-del-rel]").forEach(function (btn) {
        btn.addEventListener("click", function () {
          var id = Number(btn.getAttribute("data-del-rel"));
          InventarioApp.apiFetch("relaciones.php", {
            method: "DELETE",
            body: JSON.stringify({ id: id }),
          })
            .then(function () {
              showOk("Relación eliminada.");
              return loadRelaciones();
            })
            .catch(function (e) {
              showErr(e.message);
            });
        });
      });
    });
  }

  function refreshAll() {
    var allow = puedeGestionarInsumos;
    return loadCategorias()
      .then(function () {
        return loadInsumosIntoSelects(allow);
      })
      .then(function () {
        return loadRelaciones(allow);
      });
  }

  function aplicarSesionInventario(user) {
    puedeGestionarInsumos = InventarioApp.puedeGestionarInventario(user);
    esAdmin = InventarioApp.esAdminUsuario(user);
    document.getElementById("user-name").textContent = user.username;
    if (InventarioApp.puedeListarUsuarios(user)) {
      linkUsuarios.classList.remove("hidden");
    } else {
      linkUsuarios.classList.add("hidden");
    }
    if (esAdmin) {
      cardCat.classList.remove("hidden");
    } else {
      cardCat.classList.add("hidden");
    }
    if (formIns) {
      formIns.closest(".card").classList.toggle("hidden", !puedeGestionarInsumos);
    }
    if (formRel) {
      formRel.closest(".card").classList.toggle("hidden", !puedeGestionarInsumos);
    }
  }

  InventarioApp.requireAuth().then(function (user) {
    if (!user) return;
    aplicarSesionInventario(user);
    refreshAll().catch(function (e) {
      showErr(e.message);
    });
  });

  window.addEventListener("pageshow", function (ev) {
    if (!ev.persisted) return;
    InventarioApp.requireAuth().then(function (user) {
      if (!user) return;
      aplicarSesionInventario(user);
      refreshAll().catch(function (e) {
        showErr(e.message);
      });
    });
  });

  formIns.addEventListener("submit", function (e) {
    e.preventDefault();
    if (!puedeGestionarInsumos) return;
    var cat = selCat.value;
    var body = {
      codigo: document.getElementById("ins-codigo").value.trim(),
      nombre: document.getElementById("ins-nombre").value.trim(),
      descripcion: document.getElementById("ins-desc").value.trim(),
      categoria_id: cat === "" ? null : Number(cat),
      unidad_medida: document.getElementById("ins-unidad").value.trim() || "unidad",
      stock_actual: Number(document.getElementById("ins-stock").value || 0),
      stock_minimo: Number(document.getElementById("ins-min").value || 0),
      ubicacion: document.getElementById("ins-ubicacion").value.trim(),
    };
    InventarioApp.apiFetch("insumos.php", { method: "POST", body: JSON.stringify(body) })
      .then(function () {
        formIns.reset();
        showOk("Insumo registrado.");
        return refreshAll();
      })
      .catch(function (err) {
        showErr(err.message);
      });
  });

  formRel.addEventListener("submit", function (e) {
    e.preventDefault();
    if (!puedeGestionarInsumos) return;
    var cv = document.getElementById("rel-cant").value.trim();
    var body = {
      insumo_origen_id: Number(selOrigen.value),
      insumo_destino_id: Number(selDestino.value),
      tipo_relacion: selTipo.value,
      cantidad_referencia: cv === "" ? null : Number(cv),
      notas: document.getElementById("rel-notas").value.trim(),
    };
    if (body.cantidad_referencia !== null && (isNaN(body.cantidad_referencia) || body.cantidad_referencia < 0)) {
      showErr("Cantidad de referencia no válida.");
      return;
    }
    InventarioApp.apiFetch("relaciones.php", { method: "POST", body: JSON.stringify(body) })
      .then(function () {
        document.getElementById("rel-cant").value = "";
        document.getElementById("rel-notas").value = "";
        showOk("Relación creada.");
        return loadRelaciones();
      })
      .catch(function (err) {
        showErr(err.message);
      });
  });

  formCat.addEventListener("submit", function (e) {
    e.preventDefault();
    if (!esAdmin) return;
    var body = {
      nombre: document.getElementById("cat-nombre").value.trim(),
      descripcion: document.getElementById("cat-desc").value.trim(),
    };
    InventarioApp.apiFetch("categorias.php", { method: "POST", body: JSON.stringify(body) })
      .then(function () {
        formCat.reset();
        showOk("Categoría creada.");
        return loadCategorias();
      })
      .catch(function (err) {
        showErr(err.message);
      });
  });

  filtroRel.addEventListener("change", function () {
    loadRelaciones(puedeGestionarInsumos).catch(function (e) {
      showErr(e.message);
    });
  });

  btnLogout.addEventListener("click", function () {
    InventarioApp.logout();
  });
})();
