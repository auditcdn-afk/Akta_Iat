const SESSION_KEY = "akta_session";

let profile = null;

function getSession() {
    try {
        const raw = sessionStorage.getItem(SESSION_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch {
        return null;
    }
}

function authHeaders(extra = {}) {
    const session = getSession();
    return {
        Accept: "application/json",
        Authorization: `${session?.tokenType || "Bearer"} ${session?.token}`,
        ...extra,
    };
}

function showAlert(message, type = "success") {
    const el = document.getElementById("profileAlert");
    if (!el) return;
    el.textContent = message;
    el.className = `rounded-xl border px-4 py-3 text-sm ${
        type === "error"
            ? "border-red-500/30 bg-red-500/10 text-red-200"
            : "border-emerald-500/30 bg-emerald-500/10 text-emerald-200"
    }`;
    el.classList.remove("hidden");
    window.scrollTo({ top: 0, behavior: "smooth" });
    setTimeout(() => el.classList.add("hidden"), 5000);
}

function setValue(id, value) {
    const el = document.getElementById(id);
    if (el) el.value = value ?? "";
}

function renderAvatar() {
    const avatar = document.getElementById("profileAvatar");
    const initialEl = document.getElementById("profileAvatarInitial");
    const initial = (profile?.displayName || profile?.name || profile?.username || "U")
        .trim()
        .charAt(0)
        .toUpperCase();

    if (initialEl) initialEl.textContent = initial;

    if (avatar) {
        if (profile?.photoUrl) {
            avatar.innerHTML = `<img src="${profile.photoUrl}?t=${Date.now()}" alt="Foto" class="h-full w-full object-cover">`;
        } else {
            avatar.innerHTML = `<span id="profileAvatarInitial">${initial}</span>`;
        }
    }
}

function fillForm() {
    setValue("username", profile.username);
    setValue("role", profile.role);
    setValue("name", profile.name);
    setValue("displayName", profile.displayName);
    setValue("email", profile.email);
    setValue("unitUsaha", profile.unitUsaha);
    setValue("wilayah", profile.wilayah);
    renderAvatar();
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, options);
    const payload = await response.json().catch(() => ({}));
    if (!response.ok) {
        const firstError = payload.errors
            ? Object.values(payload.errors).flat()[0]
            : null;
        throw new Error(firstError || payload.message || "Request gagal.");
    }
    return payload;
}

async function loadProfile() {
    const payload = await fetchJson("/api/profile", { headers: authHeaders() });
    profile = payload.data;
    fillForm();
}

async function saveIdentity(event) {
    event.preventDefault();
    const payload = await fetchJson("/api/profile", {
        method: "PUT",
        headers: authHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify({
            name: document.getElementById("name").value.trim(),
            display_name: document.getElementById("displayName").value.trim(),
            email: document.getElementById("email").value.trim(),
        }),
    });
    profile = payload.data;
    fillForm();
    showAlert(payload.message || "Identitas berhasil disimpan.");
}

async function uploadPhoto(file) {
    const form = new FormData();
    form.append("photo", file);

    const payload = await fetchJson("/api/profile/photo", {
        method: "POST",
        headers: authHeaders(), // jangan set Content-Type agar boundary multipart otomatis
        body: form,
    });
    profile = payload.data;
    renderAvatar();
    showAlert(payload.message || "Foto berhasil diunggah.");
}

async function deletePhoto() {
    if (!confirm("Hapus foto profil?")) return;
    const payload = await fetchJson("/api/profile/photo", {
        method: "DELETE",
        headers: authHeaders(),
    });
    profile = payload.data;
    renderAvatar();
    showAlert(payload.message || "Foto dihapus.");
}

async function changePassword(event) {
    event.preventDefault();
    const newPass = document.getElementById("newPassword").value;
    const confirm = document.getElementById("confirmPassword").value;

    if (newPass !== confirm) {
        throw new Error("Konfirmasi password tidak cocok.");
    }

    const payload = await fetchJson("/api/profile/password", {
        method: "PUT",
        headers: authHeaders({ "Content-Type": "application/json" }),
        body: JSON.stringify({
            current_password: document.getElementById("currentPassword").value,
            password: newPass,
            password_confirmation: confirm,
        }),
    });

    document.getElementById("passwordForm").reset();
    showAlert(payload.message || "Password berhasil diganti.");
}

document.addEventListener("DOMContentLoaded", async () => {
    document.getElementById("identityForm")?.addEventListener("submit", async (e) => {
        try {
            await saveIdentity(e);
        } catch (err) {
            showAlert(err.message || "Gagal menyimpan identitas.", "error");
        }
    });

    document.getElementById("photoInput")?.addEventListener("change", async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;
        try {
            await uploadPhoto(file);
        } catch (err) {
            showAlert(err.message || "Gagal mengunggah foto.", "error");
        } finally {
            e.target.value = "";
        }
    });

    document.getElementById("deletePhotoButton")?.addEventListener("click", async () => {
        try {
            await deletePhoto();
        } catch (err) {
            showAlert(err.message || "Gagal menghapus foto.", "error");
        }
    });

    document.getElementById("passwordForm")?.addEventListener("submit", async (e) => {
        e.preventDefault();
        try {
            await changePassword(e);
        } catch (err) {
            showAlert(err.message || "Gagal mengganti password.", "error");
        }
    });

    try {
        await loadProfile();
    } catch (err) {
        showAlert(err.message || "Gagal memuat profil.", "error");
    }
});
