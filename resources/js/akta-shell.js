const SESSION_KEY = "akta_session";
const SIDEBAR_SCROLL_KEY = "akta_sidebar_scroll_top";

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

function renderUser(user) {
    const displayName =
        user.displayName || user.name || user.username || "User";
    const roleText = `${user.role || "-"} • ${user.unitUsaha || "-"}`;

    setText("shellUserName", displayName);
    setText("shellUserRole", roleText);
    setText("topbarUserName", displayName);
    setText("topbarUserRole", roleText);
    setText("dashboardAuthStatus", "Aktif");
}

function applyRoleVisibility(user) {
    const isAdmin = user.role === "admin";

    document.querySelectorAll('[data-admin-only="true"]').forEach((element) => {
        if (isAdmin) {
            element.classList.remove("hidden");
        } else {
            element.classList.add("hidden");
        }
    });
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

async function checkDataStore(session) {
    const element = document.getElementById("dashboardDataStoreStatus");

    if (!element) {
        return;
    }

    try {
        const response = await fetch("/api/all-data", {
            headers: authHeaders(session),
        });

        if (!response.ok) {
            throw new Error("Data store gagal.");
        }

        const data = await response.json();
        element.textContent = `${Object.keys(data).length} key`;
    } catch {
        element.textContent = "Error";
    }
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

function setupMobileMenu() {
    const button = document.getElementById("mobileMenuButton");
    const panel = document.getElementById("mobileMenuPanel");

    if (!button || !panel) {
        return;
    }

    button.addEventListener("click", () => {
        panel.classList.toggle("hidden");
    });
}

function setupSidebarScrollMemory() {
    const sidebarNav = document.getElementById("desktopSidebarNav");

    if (!sidebarNav) {
        return;
    }

    const saveScroll = () => {
        const maxSafeScroll = Math.max(
            0,
            sidebarNav.scrollHeight - sidebarNav.clientHeight,
        );
        const currentScroll = Math.min(sidebarNav.scrollTop, maxSafeScroll);

        sessionStorage.setItem(SIDEBAR_SCROLL_KEY, String(currentScroll));
    };

    sidebarNav.addEventListener("scroll", saveScroll, { passive: true });

    sidebarNav.querySelectorAll("a").forEach((link) => {
        link.addEventListener("pointerdown", saveScroll);
        link.addEventListener("mousedown", saveScroll);
        link.addEventListener("touchstart", saveScroll, { passive: true });
        link.addEventListener("click", saveScroll);
    });

    window.addEventListener("beforeunload", saveScroll);
}

function guardAdminOnlyPage(user) {
    const adminOnlyPaths = [
        "/akta/pengguna",
        "/akta/monitoring",
        "/akta/manajemen-menu",
    ];

    const currentPath = window.location.pathname;
    const isAdminOnlyPage = adminOnlyPaths.includes(currentPath);

    if (isAdminOnlyPage && user.role !== "admin") {
        window.location.href = "/akta/dashboard";
    }
}

document.addEventListener("DOMContentLoaded", async () => {
    setupMobileMenu();
    setupSidebarScrollMemory();

    const session = getSession();

    if (!session || !session.token) {
        clearSession();
        redirectToLogin();
        return;
    }

    try {
        const payload = await validateSession(session);
        const user = payload.user;

        renderUser(user);
        applyRoleVisibility(user);
        guardAdminOnlyPage(user);

        await checkDataStore(session);
    } catch {
        clearSession();
        redirectToLogin();
        return;
    }

    const logoutButton = document.getElementById("shellLogoutButton");

    if (logoutButton) {
        logoutButton.addEventListener("click", () => logout(session));
    }
});
