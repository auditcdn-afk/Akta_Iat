const SESSION_KEY = "akta_session";

function getSession() {
    try {
        const rawSession = sessionStorage.getItem(SESSION_KEY);

        if (!rawSession) {
            return null;
        }

        return JSON.parse(rawSession);
    } catch {
        return null;
    }
}

function clearSession() {
    sessionStorage.removeItem(SESSION_KEY);
}

function redirectToLogin() {
    window.location.href = "/akta/login";
}

function authHeaders(session) {
    return {
        Accept: "application/json",
        Authorization: `${session.tokenType || "Bearer"} ${session.token}`,
    };
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = value;
    }
}

function renderSession(session, userFromServer = null) {
    const user = userFromServer || session.user || {};

    setText(
        "userDisplayName",
        user.displayName || user.name || user.username || "User",
    );
    setText("userRole", `${user.role || "-"} • ${user.unitUsaha || "-"}`);
    setText("authStatus", "Aktif");

    const debug = document.getElementById("sessionDebug");

    if (debug) {
        debug.textContent = JSON.stringify(
            {
                user,
                loginTime: new Date(session.loginTime).toLocaleString("id-ID"),
                tokenPreview: session.token
                    ? `${session.token.slice(0, 12)}...`
                    : null,
            },
            null,
            2,
        );
    }
}

async function validateSession(session) {
    const response = await fetch("/api/auth/me", {
        method: "GET",
        headers: authHeaders(session),
    });

    if (!response.ok) {
        throw new Error("Session tidak valid.");
    }

    return response.json();
}

async function checkApi() {
    const response = await fetch("/api/ping", {
        headers: {
            Accept: "application/json",
        },
    });

    if (!response.ok) {
        throw new Error("API ping gagal.");
    }

    return response.json();
}

async function checkDataStore() {
    const response = await fetch("/api/all-data", {
        headers: authHeaders(session),
    });

    if (!response.ok) {
        throw new Error("Data store gagal.");
    }

    const data = await response.json();

    return Object.keys(data).length;
}

async function logout(session) {
    try {
        await fetch("/api/auth/logout", {
            method: "POST",
            headers: authHeaders(session),
        });
    } finally {
        clearSession();
        redirectToLogin();
    }
}

document.addEventListener("DOMContentLoaded", async () => {
    const session = getSession();

    if (!session || !session.token) {
        clearSession();
        redirectToLogin();
        return;
    }

    try {
        const me = await validateSession(session);

        renderSession(session, me.user);

        const ping = await checkApi();
        setText("apiStatus", ping.ok ? "Online" : "Error");

        const dataKeyCount = await checkDataStore();
        setText("dataStoreStatus", `${dataKeyCount} key`);
    } catch {
        clearSession();
        redirectToLogin();
        return;
    }

    const logoutButton = document.getElementById("logoutButton");

    if (logoutButton) {
        logoutButton.addEventListener("click", () => logout(session));
    }
});
