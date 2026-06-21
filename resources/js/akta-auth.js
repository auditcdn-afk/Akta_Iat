const SESSION_KEY = "akta_session";

function showAlert(message) {
    const alert = document.getElementById("loginAlert");

    if (!alert) {
        return;
    }

    alert.textContent = message;
    alert.classList.remove("hidden");
}

function hideAlert() {
    const alert = document.getElementById("loginAlert");

    if (!alert) {
        return;
    }

    alert.textContent = "";
    alert.classList.add("hidden");
}

function setLoading(isLoading) {
    const button = document.getElementById("loginButton");

    if (!button) {
        return;
    }

    button.disabled = isLoading;
    button.textContent = isLoading ? "Memproses..." : "Masuk";
}

function saveSession(payload) {
    const session = {
        token: payload.token,
        tokenType: payload.tokenType || "Bearer",
        user: payload.user,
        loginTime: Date.now(),
    };

    sessionStorage.setItem(SESSION_KEY, JSON.stringify(session));
}

async function login(username, password) {
    const response = await fetch("/api/auth/login", {
        method: "POST",
        headers: {
            Accept: "application/json",
            "Content-Type": "application/json",
        },
        body: JSON.stringify({
            username,
            password,
        }),
    });

    const data = await response.json().catch(() => ({}));

    if (!response.ok) {
        const message =
            data.message ||
            data?.errors?.username?.[0] ||
            "Login gagal. Periksa username dan password.";

        throw new Error(message);
    }

    if (!data.token) {
        throw new Error("Token tidak diterima dari server.");
    }

    return data;
}

document.addEventListener("DOMContentLoaded", () => {
    const existingSession = sessionStorage.getItem(SESSION_KEY);

    if (existingSession) {
        window.location.href = "/akta/dashboard";
        return;
    }

    const form = document.getElementById("aktaLoginForm");

    if (!form) {
        return;
    }

    form.addEventListener("submit", async (event) => {
        event.preventDefault();

        hideAlert();
        setLoading(true);

        const formData = new FormData(form);
        const username = String(formData.get("username") || "").trim();
        const password = String(formData.get("password") || "");

        try {
            const payload = await login(username, password);

            saveSession(payload);

            window.location.href = "/akta/dashboard";
        } catch (error) {
            showAlert(error.message || "Login gagal.");
        } finally {
            setLoading(false);
        }
    });
});
