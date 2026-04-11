(function (global) {
  const API = "api";

  async function apiFetch(path, options) {
    options = options || {};
    const res = await fetch(API + "/" + path, Object.assign(
      {
        credentials: "same-origin",
        headers: Object.assign({ "Content-Type": "application/json" }, options.headers || {}),
      },
      options
    ));
    const text = await res.text();
    const textForParse = (text || "").replace(/^\uFEFF/, "");
    var data = {};
    try {
      data = textForParse ? JSON.parse(textForParse) : {};
    } catch (e) {
      throw new Error("Respuesta no JSON del servidor");
    }
    if (!res.ok) {
      var err = new Error(data.error || "Error HTTP " + res.status);
      err.status = res.status;
      err.data = data;
      throw err;
    }
    return data;
  }

  function normalizeUser(u) {
    if (!u || typeof u !== "object") return u;
    u.id = Number(u.id);
    u.perfil_id = Number(u.perfil_id);
    return u;
  }

  /** Usa flags del servidor si existen; si no, perfil_id (retrocompatibilidad). */
  function puedeGestionarInventario(u) {
    if (!u) return false;
    if (typeof u.puede_gestionar_inventario === "boolean") return u.puede_gestionar_inventario;
    var pid = Number(u.perfil_id);
    return pid === 1 || pid === 3;
  }

  function esAdminUsuario(u) {
    if (!u) return false;
    if (typeof u.es_admin === "boolean") return u.es_admin;
    return Number(u.perfil_id) === 1;
  }

  function esSupervisorUsuario(u) {
    if (!u) return false;
    if (typeof u.es_supervisor === "boolean") return u.es_supervisor;
    return Number(u.perfil_id) === 3;
  }

  function puedeGestionarReservas(u) {
    if (!u) return false;
    if (typeof u.puede_gestionar_reservas === "boolean") return u.puede_gestionar_reservas;
    var pid = Number(u.perfil_id);
    return pid === 1 || pid === 3;
  }

  function puedeListarUsuarios(u) {
    if (!u) return false;
    if (typeof u.puede_listar_usuarios === "boolean") return u.puede_listar_usuarios;
    var pid = Number(u.perfil_id);
    return pid === 1 || pid === 3;
  }

  async function getSession() {
    var r = await apiFetch("auth.php?action=me");
    if (r && r.user) normalizeUser(r.user);
    return r;
  }

  async function logout() {
    await apiFetch("auth.php?action=logout", { method: "POST", body: "{}" });
    window.location.href = "login.html";
  }

  function perfilBadgeClass(nombre) {
    var n = (nombre || "").toLowerCase();
    if (n.indexOf("admin") !== -1) return "admin";
    if (n.indexOf("supervisor") !== -1) return "supervisor";
    return "residente";
  }

  async function requireAuth() {
    var r = await getSession();
    if (!r.user) {
      window.location.href = "login.html";
      return null;
    }
    return r.user;
  }

  global.InventarioApp = {
    apiFetch: apiFetch,
    getSession: getSession,
    logout: logout,
    perfilBadgeClass: perfilBadgeClass,
    requireAuth: requireAuth,
    normalizeUser: normalizeUser,
    puedeGestionarInventario: puedeGestionarInventario,
    esAdminUsuario: esAdminUsuario,
    puedeGestionarReservas: puedeGestionarReservas,
    puedeListarUsuarios: puedeListarUsuarios,
    esSupervisorUsuario: esSupervisorUsuario,
  };
})(typeof window !== "undefined" ? window : this);
