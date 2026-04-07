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
    var data = {};
    try {
      data = text ? JSON.parse(text) : {};
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

  async function getSession() {
    return apiFetch("auth.php?action=me");
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
  };
})(typeof window !== "undefined" ? window : this);
