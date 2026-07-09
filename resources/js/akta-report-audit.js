const SESSION_KEY = "akta_session";

let reportItems = [];
let reportSummary = null;
let currentUser = null;

function getSession() {
    try {
        const rawSession = sessionStorage.getItem(SESSION_KEY);
        return rawSession ? JSON.parse(rawSession) : null;
    } catch {
        return null;
    }
}

function authHeaders() {
    const session = getSession();

    return {
        Accept: "application/json",
        "Content-Type": "application/json",
        Authorization: `${session?.tokenType || "Bearer"} ${session?.token}`,
    };
}

function normalizeListPayload(payload) {
    if (Array.isArray(payload)) {
        return payload;
    }

    return payload.data || [];
}

async function fetchJson(url, options = {}) {
    const response = await fetch(url, {
        ...options,
        headers: {
            ...authHeaders(),
            ...(options.headers || {}),
        },
    });

    const payload = await response.json().catch(() => ({}));

    if (!response.ok) {
        const firstError = payload.errors
            ? Object.values(payload.errors).flat()[0]
            : null;

        throw new Error(firstError || payload.message || "Request gagal.");
    }

    return payload;
}

async function loadCurrentUser() {
    const payload = await fetchJson("/api/auth/me");
    currentUser = payload.user;
}

async function loadReportSummary() {
    const payload = await fetchJson("/api/report-audit/summary");

    reportSummary = payload.data || {};

    renderGlobalStats();
}

async function loadReportItems() {
    const q = document.getElementById("reportAuditSearch")?.value || "";
    const status =
        document.getElementById("reportAuditStatusFilter")?.value || "";

    const params = new URLSearchParams();

    if (q) {
        params.set("q", q);
    }

    if (status) {
        params.set("status", status);
    }

    const url = params.toString()
        ? `/api/report-audit?${params.toString()}`
        : "/api/report-audit";

    const payload = await fetchJson(url);

    reportItems = normalizeListPayload(payload);

    renderReportItems();
}

function renderGlobalStats() {
    const data = reportSummary || {};

    setText("reportPlanTotalStat", data.plan_total || 0);
    setText("reportTaskTotalStat", data.task_total || 0);
    setText("reportRecommendationTotalStat", data.recommendation_total || 0);
    setText("reportPicaTotalStat", data.pica_total || 0);
    setText("reportPicaClosedStat", data.pica_closed || 0);
    setText("reportSkTotalStat", data.sk_total || 0);
    setText("reportSkSelesaiStat", data.sk_selesai || 0);
    setText("reportGeneratedAtStat", formatDateTime(data.generated_at));
}

function renderReportItems() {
    const tbody = document.getElementById("reportAuditTableBody");

    if (!tbody) {
        return;
    }

    if (!reportItems.length) {
        tbody.innerHTML = `
            <tr>
                <td colspan="7" class="px-4 py-6 text-center text-sm text-slate-400">
                    Belum ada data Report Audit.
                </td>
            </tr>
        `;
        return;
    }

    tbody.innerHTML = reportItems
        .map((item) => {
            const plan = item.plan || {};
            const summary = item.summary || {};
            const progress = Number(summary.completion_percent || 0);

            return `
                <tr class="hover:bg-slate-950/50">
                    <td class="px-4 py-4">
                        <div class="font-semibold text-slate-100">${escapeHtml(plan.no_spt || "-")}</div>
                        <div class="text-xs text-slate-500">${escapeHtml(plan.cabang || plan.unit_usaha || "-")}</div>
                        <div class="mt-1">
                            <span class="inline-flex rounded-full border px-2.5 py-1 text-xs font-bold capitalize ${statusBadge(plan.status)}">
                                ${escapeHtml(plan.status || "-")}
                            </span>
                        </div>
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div>Total: ${escapeHtml(summary.task_total || 0)}</div>
                        <div class="text-xs text-slate-500">Done: ${escapeHtml(summary.task_done || 0)}</div>
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div>Total: ${escapeHtml(summary.recommendation_total || 0)}</div>
                        <div class="text-xs text-slate-500">Approved: ${escapeHtml(summary.recommendation_approved || 0)}</div>
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div>Total: ${escapeHtml(summary.pica_total || 0)}</div>
                        <div class="text-xs text-slate-500">Closed: ${escapeHtml(summary.pica_closed || 0)}</div>
                    </td>

                    <td class="px-4 py-4 text-sm text-slate-300">
                        <div>Total: ${escapeHtml(summary.sk_total || 0)}</div>
                        <div class="text-xs text-slate-500">Selesai: ${escapeHtml(summary.sk_selesai || 0)}</div>
                    </td>

                    <td class="px-4 py-4">
                        <div class="w-40 rounded-full bg-slate-800">
                            <div class="h-2 rounded-full bg-blue-500" style="width: ${safePercent(progress)}%"></div>
                        </div>
                        <div class="mt-1 text-xs font-semibold text-slate-300">${escapeHtml(progress)}%</div>
                    </td>

                    <td class="px-4 py-4 text-right">
                        <div class="flex items-center justify-end gap-2">
                            ${!plan.is_mandiri ? `
                                <button type="button" class="view-report-detail rounded-lg border border-slate-700 px-3 py-1.5 text-xs font-semibold text-slate-300 hover:bg-slate-800" data-plan-id="${plan.id}">
                                    Detail
                                </button>
                            ` : ""}
                            ${canShowPenilaianButton(plan) ? `
                                <button type="button" class="open-penilaian rounded-lg border border-emerald-500/40 px-3 py-1.5 text-xs font-semibold text-emerald-300 hover:bg-emerald-500/10" data-plan-id="${plan.id}">
                                    Penilaian
                                </button>
                            ` : ""}
                            ${canShowCrosscheckButton(plan) ? `
                                <button type="button" class="open-crosscheck rounded-lg border border-amber-500/40 px-3 py-1.5 text-xs font-semibold text-amber-300 hover:bg-amber-500/10" data-plan-id="${plan.id}">
                                    Crosscheck
                                </button>
                            ` : ""}
                            <a href="/akta/report-audit/pdf/${plan.id}" target="_blank"
                               class="rounded-lg border border-blue-600 bg-blue-600/10 px-3 py-1.5 text-xs font-semibold text-blue-400 hover:bg-blue-600/20">
                                📄 Cetak PDF
                            </a>
                        </div>
                    </td>
                </tr>
            `;
        })
        .join("");
}

// Tombol Penilaian hanya untuk koordinator/manajer, dan hanya saat plan sudah done.
function canShowPenilaianButton(plan) {
    return ["koordinator", "manajer"].includes(currentUser?.role) && plan.status === "done";
}

// Crosscheck: khusus plan Audit Mandiri/Sertijab, bisa dilakukan oleh auditor mana pun (atau admin).
function canShowCrosscheckButton(plan) {
    return !!plan.is_mandiri && ["auditor", "admin"].includes(currentUser?.role);
}

// Auto-fill "Isi Rekomendasi" dari data pemeriksaan, sama seperti Rekomendasi Form
// di menu Audit (rekomendasiAutoFill), agar konsisten dengan hasil report PDF cetak.
async function crosscheckAutoFill(planId) {
    const isiEl = document.getElementById("crosscheckRekIsi");
    if (!isiEl || !planId) return;
    isiEl.value = "⏳ Memuat data pemeriksaan...";

    const fmtRp = (n) => "Rp " + Number(n || 0).toLocaleString("id-ID");
    const sep = "─".repeat(48);
    const blocks = [];

    try {
        // ── 1. PEMERIKSAAN KAS ──────────────────────────────
        try {
            const kasRes = await fetchJson(`/api/audit-detail/kas?plan_audit_id=${planId}`, { headers: authHeaders() });
            const kasRows = kasRes.data ?? kasRes ?? [];
            let kbSaldoBuku = 0, kbSaldoFisik = 0;
            let kkSaldoBuku = 0, kkSaldoFisik = 0;
            for (const k of kasRows) {
                const d = k.detail_json ?? k.detailJson ?? {};
                const kb = d.kas_besar ?? d.kasBesar ?? {};
                const kk = d.kas_kecil ?? d.kasKecil ?? {};
                const pcn = d.pecahan ?? [];
                const kbSaldoAwal = Number(kb.saldo_awal ?? kb.saldoAwal ?? 0);
                const kbTotalTerima = (kb.penerimaan ?? []).reduce((s, r) => s + Number(r.jumlah ?? 0), 0);
                const kbTotalKeluar = (kb.pengeluaran ?? []).reduce((s, r) => s + Number(r.jumlah ?? 0), 0);
                kbSaldoBuku += kbSaldoAwal + kbTotalTerima - kbTotalKeluar;
                kbSaldoFisik += pcn.reduce((s, p) => s + Number(p.nominal ?? 0) * Number(p.lembar_besar ?? p.lembarBesar ?? 0), 0);
                const kkCadangan = Number(kk.cadangan ?? 0);
                const kkTotalBon = (kk.bon ?? []).reduce((s, r) => s + Number(r.jumlah ?? 0), 0);
                kkSaldoBuku += kkCadangan - kkTotalBon;
                kkSaldoFisik += pcn.reduce((s, p) => s + Number(p.nominal ?? 0) * Number(p.lembar_kecil ?? p.lembarKecil ?? 0), 0);
            }
            const kbSel = kbSaldoFisik - kbSaldoBuku;
            const kkSel = kkSaldoFisik - kkSaldoBuku;
            const totBuku = kbSaldoBuku + kkSaldoBuku;
            const totFisik = kbSaldoFisik + kkSaldoFisik;
            const totSel = kbSel + kkSel;
            const rows = [];
            rows.push(`  ${"Pos Kas".padEnd(12)} ${"Saldo Buku".padStart(16)} ${"Saldo Fisik".padStart(16)} ${"Selisih".padStart(14)}`);
            rows.push(`  ${"─".repeat(60)}`);
            rows.push(`  ${"Kas Besar".padEnd(12)} ${fmtRp(kbSaldoBuku).padStart(16)} ${fmtRp(kbSaldoFisik).padStart(16)} ${(kbSel !== 0 ? (kbSel > 0 ? "+" : "") + fmtRp(kbSel) : "Rp 0").padStart(14)}`);
            rows.push(`  ${"Kas Kecil".padEnd(12)} ${fmtRp(kkSaldoBuku).padStart(16)} ${fmtRp(kkSaldoFisik).padStart(16)} ${(kkSel !== 0 ? (kkSel > 0 ? "+" : "") + fmtRp(kkSel) : "Rp 0").padStart(14)}`);
            rows.push(`  ${"─".repeat(60)}`);
            rows.push(`  ${"TOTAL".padEnd(12)} ${fmtRp(totBuku).padStart(16)} ${fmtRp(totFisik).padStart(16)} ${(totSel !== 0 ? (totSel > 0 ? "+" : "") + fmtRp(totSel) : "Rp 0").padStart(14)}`);
            if (totSel !== 0 || kbSel !== 0 || kkSel !== 0)
                blocks.push(`1. PEMERIKSAAN KAS\n${rows.join("\n")}`);
        } catch {}

        // ── 2. PEMERIKSAAN BANK ─────────────────────────────
        try {
            const bankRes = await fetchJson(`/api/audit-detail/bank?plan_audit_id=${planId}`, { headers: authHeaders() });
            const bankRows = bankRes.data ?? bankRes ?? [];
            const bankWithSel = [];
            for (const b of bankRows) {
                const saldoBuku = Number(b.saldo_buku ?? b.saldoBuku ?? 0);
                const saldoRk = Number(b.saldo_bank ?? b.saldoBank ?? b.saldo_rk ?? b.saldoRk ?? 0);
                const selisih = saldoRk - saldoBuku;
                if (selisih !== 0) {
                    bankWithSel.push({ nama: b.nama_bank ?? b.namaBank ?? "-", saldoBuku, saldoRk, selisih });
                }
            }
            if (bankWithSel.length > 0) {
                const rows = [];
                rows.push(`  ${"Nama Bank".padEnd(20)} ${"Saldo Buku".padStart(16)} ${"Saldo Rek. Koran".padStart(18)} ${"Selisih".padStart(14)}`);
                rows.push(`  ${"─".repeat(70)}`);
                for (const b of bankWithSel) {
                    const selStr = (b.selisih > 0 ? "+" : "") + fmtRp(b.selisih);
                    rows.push(`  ${b.nama.padEnd(20)} ${fmtRp(b.saldoBuku).padStart(16)} ${fmtRp(b.saldoRk).padStart(18)} ${selStr.padStart(14)}`);
                }
                blocks.push(`2. PEMERIKSAAN BANK\n${rows.join("\n")}`);
            }
        } catch {}

        // ── 3. CEK FISIK SMH ────────────────────────────────
        try {
            const smhRes = await fetchJson(`/api/audit-detail/smh?plan_audit_id=${planId}`, { headers: authHeaders() });
            const smhRows = smhRes.data ?? smhRes ?? [];
            let totalUnit = 0, totalTemukan = 0;
            for (const s of smhRows) {
                totalUnit += Number(s.totalUnit ?? s.total_unit ?? 0);
                totalTemukan += Number(s.ditemukan ?? s.totalDitemukan ?? 0);
            }
            const tidakTemukan = totalUnit - totalTemukan;
            if (totalUnit > 0 && tidakTemukan > 0) {
                const rows = [
                    `  • Total unit diperiksa : ${totalUnit}`,
                    `  • Ditemukan            : ${totalTemukan}`,
                    `  • Tidak ditemukan      : ${tidakTemukan} unit`,
                ];
                blocks.push(`3. CEK FISIK SMH\n${rows.join("\n")}`);
            }
        } catch {}

        // ── 4. PERLENGKAPAN SMH — rekap gabungan per jenis ──
        try {
            const [smhSumRes, luarRes] = await Promise.all([
                fetchJson(`/api/audit-detail/perlengkapan/smh-summary?plan_audit_id=${planId}`, { headers: authHeaders() }),
                fetchJson(`/api/audit-detail/perlengkapan?plan_audit_id=${planId}`, { headers: authHeaders() }),
            ]);
            const smhMap = {};
            for (const r of (smhSumRes.data ?? [])) {
                const nm = (r.nama || "").trim();
                if (nm) smhMap[nm] = { smhSaldo: Number(r.total ?? 0), smhFisik: Number(r.ada ?? 0) };
            }
            const luarMap = {};
            for (const p of (luarRes.data ?? [])) {
                const nm = (p.jenisPerlengkapan ?? p.jenis_perlengkapan ?? p.jenis ?? "").trim();
                if (!nm) continue;
                if (!luarMap[nm]) luarMap[nm] = { luarSelisih: 0 };
                luarMap[nm].luarSelisih += Number(p.selisih ?? 0);
            }
            const allJenis = [...new Set([...Object.keys(smhMap), ...Object.keys(luarMap)])].sort();
            const rows = [];
            let grandSel = 0;
            for (const jenis of allJenis) {
                const smhD = smhMap[jenis] ?? { smhSaldo: 0, smhFisik: 0 };
                const luarD = luarMap[jenis] ?? { luarSelisih: 0 };
                const totalSel = (smhD.smhFisik - smhD.smhSaldo) + luarD.luarSelisih;
                grandSel += totalSel;
                if (totalSel !== 0) rows.push(`  • ${jenis.padEnd(26)} selisih: ${totalSel}`);
            }
            if (rows.length) blocks.push(`4. PERLENGKAPAN SMH\n${rows.join("\n")}\n  ${"─".repeat(40)}\n  Total selisih: ${grandSel}`);
        } catch {}

        // ── 5. MATERAI ──────────────────────────────────────
        try {
            const matRes = await fetchJson(`/api/audit-detail/materai?plan_audit_id=${planId}`, { headers: authHeaders() });
            const matRows = matRes.data ?? matRes ?? [];
            const matWithSel = [];
            for (const m of matRows) {
                const saldoBuku = Number(m.saldoAkhir ?? m.saldo_akhir ?? 0);
                const fisik = Number(m.fisik ?? 0);
                const selisih = fisik - saldoBuku;
                if (selisih !== 0) matWithSel.push({ jenis: m.jenisMaterai ?? m.jenis_materai ?? "-", saldoBuku, fisik, selisih });
            }
            if (matWithSel.length > 0) {
                const rows = [];
                rows.push(`  ${"Jenis Materai".padEnd(20)} ${"Saldo Buku".padStart(12)} ${"Fisik".padStart(8)} ${"Selisih".padStart(10)}`);
                rows.push(`  ${"─".repeat(52)}`);
                for (const m of matWithSel) {
                    rows.push(`  ${m.jenis.padEnd(20)} ${String(m.saldoBuku).padStart(12)} ${String(m.fisik).padStart(8)} ${((m.selisih > 0 ? "+" : "") + m.selisih).padStart(10)}`);
                }
                blocks.push(`5. MATERAI\n${rows.join("\n")}`);
            }
        } catch {}

        // ── 6. CEK FISIK ────────────────────────────────────
        try {
            const cfRes = await fetchJson(`/api/audit-detail/cek-fisik?plan_audit_id=${planId}`, { headers: authHeaders() });
            const cfRaw = cfRes.data?.data ?? cfRes.data ?? cfRes ?? {};
            const cfSel = cfRaw.selisih ?? {};
            const cfSa = cfRaw.saldoAkhir ?? {};
            const cfFk = cfRaw.fisik ?? {};
            const items = [
                { nama: "CEK FISIK (CF)", saldoAkhir: Number(cfSa.cf ?? 0), fisik: Number(cfFk.cf ?? 0), selisih: Number(cfSel.cf ?? 0) },
                { nama: "STUJ", saldoAkhir: Number(cfSa.stuj ?? 0), fisik: Number(cfFk.stuj ?? 0), selisih: Number(cfSel.stuj ?? 0) },
                { nama: "F. STNK", saldoAkhir: Number(cfSa.fstnk ?? 0), fisik: Number(cfFk.fstnk ?? 0), selisih: Number(cfSel.fstnk ?? 0) },
            ].filter((it) => it.selisih !== 0);
            if (items.length > 0) {
                const rows = [];
                rows.push(`  ${"Jenis".padEnd(20)} ${"Saldo Akhir".padStart(12)} ${"Fisik".padStart(8)} ${"Selisih".padStart(10)}`);
                rows.push(`  ${"─".repeat(52)}`);
                for (const it of items) {
                    rows.push(`  ${it.nama.padEnd(20)} ${String(it.saldoAkhir).padStart(12)} ${String(it.fisik).padStart(8)} ${((it.selisih > 0 ? "+" : "") + it.selisih).padStart(10)}`);
                }
                blocks.push(`6. CEK FISIK\n${rows.join("\n")}`);
            }
        } catch {}

        // ── 7. HGP & AHM OILS ───────────────────────────────
        try {
            const hgpRes = await fetchJson(`/api/audit-detail/hgp?plan_audit_id=${planId}`, { headers: authHeaders() });
            const hgp = hgpRes.data ?? hgpRes ?? {};
            const hgpItems = hgp.items_json ?? hgp.itemsJson ?? hgp.items ?? [];
            let cntSel = 0, totalNilai = 0;
            const rows = [];
            for (const it of hgpItems) {
                const sel = Number(it.selisih ?? 0);
                const harga = Number(it.hargaHet ?? it.harga_het ?? 0);
                if (sel !== 0) {
                    cntSel++;
                    totalNilai += Math.abs(sel) * harga;
                    const nama = (it.sparepart || it.noPart || "-").substring(0, 28);
                    rows.push(`  • ${nama.padEnd(30)} sel: ${sel}  nilai: ${fmtRp(Math.abs(sel) * harga)}`);
                }
            }
            if (cntSel > 0) {
                blocks.push(`7. HGP & AHM OILS\n  • Item selisih : ${cntSel}\n  • Total nilai  : ${fmtRp(totalNilai)}\n${rows.slice(0, 12).join("\n")}`);
            }
        } catch {}

        // ── 8. MEKANIK TOOLS (MT) ───────────────────────────
        try {
            const mtRes = await fetchJson(`/api/audit-detail/mt?plan_audit_id=${planId}`, { headers: authHeaders() });
            const mt = mtRes.data ?? mtRes ?? {};
            const mtEntries = mt.entries ?? mt.items_json ?? mt.itemsJson ?? [];
            const mtSel = mt.mekanikSelectedJenis ?? {};
            const rows = [];
            for (const entry of mtEntries) {
                const mekanik = entry.mekanik || "-";
                if ((entry.jenis || "") !== (mtSel[mekanik] || "baru")) continue;
                const rusak = entry.rusak ?? [];
                const hilang = entry.hilang ?? [];
                if (!rusak.length && !hilang.length) continue;
                rows.push(`  • ${mekanik}`);
                if (rusak.length) rows.push(`    Rusak  (${rusak.length})  : ${rusak.map((t) => t.nama || t).join(", ")}`);
                if (hilang.length) rows.push(`    Hilang (${hilang.length}) : ${hilang.map((t) => t.nama || t).join(", ")}`);
            }
            if (rows.length) blocks.push(`8. MEKANIK TOOLS (MT)\n${rows.join("\n")}`);
        } catch {}

        isiEl.value = blocks.length
            ? blocks.join("\n\n" + sep + "\n\n")
            : "(Tidak ada temuan signifikan dari data pemeriksaan)";
    } catch {
        isiEl.value = "";
    }
}

async function openCrosscheck(planId) {
    const modal = document.getElementById("crosscheckModal");
    if (!modal) return;

    document.getElementById("crosscheckPlanId").value = planId;
    document.getElementById("crosscheckForm")?.reset();
    document.getElementById("crosscheckRekomendasiWrap")?.classList.add("hidden");
    document.getElementById("crosscheckExisting")?.classList.add("hidden");
    document.getElementById("crosscheckRekFileName")?.classList.add("hidden");

    const item = reportItems.find((it) => String(it.plan?.id) === String(planId));
    const plan = item?.plan || {};
    document.getElementById("crosscheckRekNoSpt").value = plan.no_spt || "";
    document.getElementById("crosscheckRekCabang").value = plan.cabang || plan.unit_usaha || "";
    document.getElementById("crosscheckRekTglAudit").value = new Date().toISOString().slice(0, 10);

    modal.classList.remove("hidden");
    modal.classList.add("flex");

    try {
        const res = await fetchJson(`/api/plan-audit-mandiri-crosscheck?plan_audit_id=${planId}`, { headers: authHeaders() });
        if (res.data) {
            const label = { ok: "OK", not_ok: "Not OK", selisih: "Selisih" }[res.data.hasil] || res.data.hasil;
            document.getElementById("crosscheckExistingText").textContent =
                `${label} oleh ${res.data.displayName || res.data.username || "-"} (${res.data.updatedAt || res.data.createdAt || "-"})`;
            document.getElementById("crosscheckExisting")?.classList.remove("hidden");
        }
    } catch (e) {
        showAlert(e.message, "error");
    }
}

function closeCrosscheck() {
    const modal = document.getElementById("crosscheckModal");
    modal?.classList.add("hidden");
    modal?.classList.remove("flex");
}

async function saveCrosscheck(event) {
    event.preventDefault();

    const planId = document.getElementById("crosscheckPlanId")?.value;
    const hasil = document.querySelector('input[name="crosscheckHasil"]:checked')?.value;
    if (!planId || !hasil) {
        showAlert("Pilih hasil crosscheck terlebih dahulu.", "error");
        return;
    }

    const body = {
        plan_audit_id: Number(planId),
        hasil,
        catatan: document.getElementById("crosscheckCatatan")?.value || null,
    };

    if (hasil === "selisih") {
        const isi = (document.getElementById("crosscheckRekIsi")?.value || "").trim();
        if (!isi) {
            showAlert("Isi Rekomendasi wajib diisi jika hasil Selisih.", "error");
            return;
        }

        const file = document.getElementById("crosscheckRekFileInput")?.files?.[0] || null;
        const fileName = file ? file.name : null;

        const firstLine = isi.split("\n").find((l) => l.trim()) ?? "";
        const judul = firstLine.trim().substring(0, 250) || "Rekomendasi Audit";
        const deskripsi = isi + (fileName ? "\n\nLampiran: " + fileName : "");

        body.rekomendasi = {
            judul,
            deskripsi,
            prioritas: "sedang",
        };
    }

    try {
        const payload = await fetchJson("/api/plan-audit-mandiri-crosscheck", {
            method: "POST",
            headers: { ...authHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify(body),
        });
        showAlert(payload.message || "Crosscheck berhasil disimpan.");
        closeCrosscheck();
        await loadReportSummary();
        await loadReportItems();
    } catch (e) {
        showAlert(e.message, "error");
    }
}

async function openPenilaian(planId) {
    const modal = document.getElementById("penilaianModal");
    if (!modal) return;

    document.getElementById("penilaianPlanId").value = planId;
    document.getElementById("penilaianViewWrap")?.classList.add("hidden");
    document.getElementById("penilaianForm")?.classList.add("hidden");
    document.getElementById("penilaianLoading")?.classList.remove("hidden");

    modal.classList.remove("hidden");
    modal.classList.add("flex");

    try {
        const res = await fetchJson(`/api/plan-penilaian?plan_audit_id=${planId}`);
        const rows = res.data || [];
        const mine = rows.find((r) => r.role === currentUser?.role);

        document.getElementById("penilaianLoading")?.classList.add("hidden");

        if (mine) {
            document.getElementById("penilaianViewWrap")?.classList.remove("hidden");
            setText("penilaianViewTgl", mine.tglPemeriksaan || "-");
            setText("penilaianViewCatatan", mine.catatan || "-");
            const hasilEl = document.getElementById("penilaianViewHasil");
            if (hasilEl) {
                const isOk = mine.hasil === "ok";
                hasilEl.textContent = isOk ? "OK" : "Not OK";
                hasilEl.className = "text-sm font-bold " + (isOk ? "text-emerald-300" : "text-red-300");
            }
        } else {
            document.getElementById("penilaianForm")?.classList.remove("hidden");
            setText("penilaianFormTgl", new Date().toLocaleString("id-ID"));
            const textarea = document.getElementById("penilaianCatatan");
            if (textarea) textarea.value = "";
            document.querySelectorAll(".penilaian-hasil-radio").forEach((r) => (r.checked = false));
            document.getElementById("penilaianCatatanWrap")?.classList.add("hidden");
        }
    } catch (e) {
        document.getElementById("penilaianLoading")?.classList.add("hidden");
        showAlert(e.message || "Gagal memuat penilaian.", "error");
    }
}

function closePenilaian() {
    const modal = document.getElementById("penilaianModal");
    modal?.classList.add("hidden");
    modal?.classList.remove("flex");
}

async function savePenilaian(event) {
    event.preventDefault();
    const planId = document.getElementById("penilaianPlanId").value;
    const catatan = document.getElementById("penilaianCatatan")?.value.trim();
    const hasil = document.querySelector(".penilaian-hasil-radio:checked")?.value;
    const btn = document.getElementById("savePenilaianBtn");

    if (!hasil) {
        showAlert("Pilih hasil penilaian: OK atau Not OK.", "error");
        return;
    }
    if (hasil === "not_ok" && !catatan) {
        showAlert("Catatan penilaian wajib diisi jika hasilnya Not OK.", "error");
        return;
    }

    btn.textContent = "Menyimpan...";
    btn.disabled = true;
    try {
        const payload = await fetchJson("/api/plan-penilaian", {
            method: "POST",
            body: JSON.stringify({ plan_audit_id: planId, hasil, catatan }),
        });
        showAlert(payload.message || "Penilaian berhasil disimpan.");
        closePenilaian();
    } catch (e) {
        showAlert(e.message || "Gagal menyimpan penilaian.", "error");
    } finally {
        btn.textContent = "Simpan";
        btn.disabled = false;
    }
}

async function openDetail(planId) {
    const payload = await fetchJson(`/api/report-audit/plans/${planId}`);
    const data = payload.data || {};

    renderDetail(data);

    const modal = document.getElementById("reportAuditDetailModal");
    modal.classList.remove("hidden");
    modal.classList.add("flex");
}

function closeDetail() {
    const modal = document.getElementById("reportAuditDetailModal");

    modal.classList.add("hidden");
    modal.classList.remove("flex");
}

function renderDetail(data) {
    const plan = data.plan || {};
    const summary = data.summary || {};
    const tasks = data.tasks || [];
    const recommendations = data.recommendations || [];
    const picas = data.picas || [];
    const suratKeputusan = data.surat_keputusan || [];

    setText("reportAuditDetailTitle", `Report Audit ${plan.no_spt || "-"}`);
    setText(
        "reportAuditDetailSubtitle",
        `${plan.cabang || plan.unit_usaha || "-"} • ${plan.status || "-"} • Generated ${formatDateTime(data.generated_at)}`,
    );

    setText("detailCompletionPercent", `${summary.completion_percent || 0}%`);
    setText(
        "detailTaskDone",
        `${summary.task_done || 0}/${summary.task_total || 0}`,
    );
    setText(
        "detailPicaClosed",
        `${summary.pica_closed || 0}/${summary.pica_total || 0}`,
    );
    setText(
        "detailSkSelesai",
        `${summary.sk_selesai || 0}/${summary.sk_total || 0}`,
    );

    renderPlanInfo(plan);
    renderTasks(tasks);
    renderRecommendations(recommendations);
    renderPicas(picas);
    renderSuratKeputusan(suratKeputusan);
}

function renderPlanInfo(plan) {
    const wrapper = document.getElementById("detailPlanInfo");

    if (!wrapper) {
        return;
    }

    wrapper.innerHTML = `
        ${infoItem("No SPT", plan.no_spt)}
        ${infoItem("Cabang", plan.cabang)}
        ${infoItem("Unit Usaha", plan.unit_usaha)}
        ${infoItem("Jenis Audit", plan.jenis_audit)}
        ${infoItem("Auditor", plan.auditor)}
        ${infoItem("Status", plan.status)}
        ${infoItem("Tanggal Mulai", formatDate(plan.tanggal_mulai))}
        ${infoItem("Tanggal Selesai", formatDate(plan.tanggal_selesai))}
        ${infoItem("Plan ID", plan.id)}
    `;
}

function renderTasks(tasks) {
    const wrapper = document.getElementById("detailTasks");

    if (!wrapper) {
        return;
    }

    if (!tasks.length) {
        wrapper.innerHTML = emptyText("Belum ada task.");
        return;
    }

    wrapper.innerHTML = tasks
        .map((item) => {
            return `
                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-3">
                    <div class="font-semibold text-slate-100">${escapeHtml(item.judul || item.title || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Status: ${escapeHtml(item.status || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Priority: ${escapeHtml(item.priority || item.prioritas || "-")}</div>
                </div>
            `;
        })
        .join("");
}

function renderRecommendations(recommendations) {
    const wrapper = document.getElementById("detailRecommendations");

    if (!wrapper) {
        return;
    }

    if (!recommendations.length) {
        wrapper.innerHTML = emptyText("Belum ada rekomendasi.");
        return;
    }

    wrapper.innerHTML = recommendations
        .map((item) => {
            return `
                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-3">
                    <div class="font-semibold text-slate-100">${escapeHtml(item.judul || item.title || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">PIC: ${escapeHtml(item.pic || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Status: ${escapeHtml(item.status || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Deadline: ${escapeHtml(formatDate(item.deadline))}</div>
                </div>
            `;
        })
        .join("");
}

function renderPicas(picas) {
    const wrapper = document.getElementById("detailPicas");

    if (!wrapper) {
        return;
    }

    if (!picas.length) {
        wrapper.innerHTML = emptyText("Belum ada PICA.");
        return;
    }

    wrapper.innerHTML = picas
        .map((item) => {
            return `
                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-3">
                    <div class="font-semibold text-slate-100">${escapeHtml(item.pica_no || item.title || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">PIC: ${escapeHtml(item.pic || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Status: ${escapeHtml(item.status || "-")}</div>
                    <div class="mt-2 text-xs text-slate-400">
                        <div>Problem: ${escapeHtml(item.problem || "-")}</div>
                        <div>Corrective: ${escapeHtml(item.corrective_action || "-")}</div>
                    </div>
                </div>
            `;
        })
        .join("");
}

function renderSuratKeputusan(items) {
    const wrapper = document.getElementById("detailSuratKeputusan");

    if (!wrapper) {
        return;
    }

    if (!items.length) {
        wrapper.innerHTML = emptyText("Belum ada SK.");
        return;
    }

    wrapper.innerHTML = items
        .map((item) => {
            const file = item.file_sk || {};

            return `
                <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-3">
                    <div class="font-semibold text-slate-100">${escapeHtml(item.no_sk || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Status: ${escapeHtml(item.status || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">Uploaded: ${escapeHtml(item.uploaded_by_name || "-")}</div>
                    <div class="mt-1 text-xs text-slate-500">File: ${escapeHtml(file.name || "-")}</div>
                </div>
            `;
        })
        .join("");
}

function setupFilters() {
    let timer = null;

    document
        .getElementById("reportAuditSearch")
        ?.addEventListener("input", () => {
            clearTimeout(timer);
            timer = setTimeout(
                () =>
                    loadReportItems().catch((error) =>
                        showAlert(error.message, "error"),
                    ),
                300,
            );
        });

    document
        .getElementById("reportAuditStatusFilter")
        ?.addEventListener("change", () => {
            loadReportItems().catch((error) =>
                showAlert(error.message, "error"),
            );
        });

    document
        .getElementById("reloadReportAuditButton")
        ?.addEventListener("click", async () => {
            try {
                await loadReportSummary();
                await loadReportItems();
                showAlert("Report Audit berhasil dimuat ulang.");
            } catch (error) {
                showAlert(
                    error.message || "Gagal memuat ulang Report Audit.",
                    "error",
                );
            }
        });
}

function setupTableActions() {
    document
        .getElementById("reportAuditTableBody")
        ?.addEventListener("click", async (event) => {
            const detailButton = event.target.closest(".view-report-detail");
            const penilaianButton = event.target.closest(".open-penilaian");
            const crosscheckButton = event.target.closest(".open-crosscheck");

            if (penilaianButton) {
                openPenilaian(penilaianButton.dataset.planId).catch((error) =>
                    showAlert(error.message || "Gagal membuka penilaian.", "error")
                );
                return;
            }

            if (crosscheckButton) {
                openCrosscheck(crosscheckButton.dataset.planId).catch((error) =>
                    showAlert(error.message || "Gagal membuka crosscheck.", "error")
                );
                return;
            }

            if (!detailButton) {
                return;
            }

            try {
                await openDetail(detailButton.dataset.planId);
            } catch (error) {
                showAlert(
                    error.message || "Gagal membuka detail Report Audit.",
                    "error",
                );
            }
        });

    document.getElementById("closePenilaianBtn")?.addEventListener("click", closePenilaian);
    document.getElementById("cancelPenilaianBtn")?.addEventListener("click", closePenilaian);
    document.getElementById("penilaianForm")?.addEventListener("submit", (event) => {
        savePenilaian(event).catch((error) => showAlert(error.message || "Gagal menyimpan penilaian.", "error"));
    });
    document.querySelectorAll(".penilaian-hasil-radio").forEach((radio) => {
        radio.addEventListener("change", () => {
            document.getElementById("penilaianCatatanWrap")?.classList.toggle("hidden", radio.value === "ok" && radio.checked);
        });
    });

    document.getElementById("closeCrosscheckBtn")?.addEventListener("click", closeCrosscheck);
    document.getElementById("cancelCrosscheckBtn")?.addEventListener("click", closeCrosscheck);
    document.getElementById("crosscheckForm")?.addEventListener("submit", (event) => {
        saveCrosscheck(event).catch((error) => showAlert(error.message || "Gagal menyimpan crosscheck.", "error"));
    });
    document.querySelectorAll(".crosscheck-hasil-radio").forEach((radio) => {
        radio.addEventListener("change", () => {
            const isSelisih = radio.value === "selisih";
            document.getElementById("crosscheckRekomendasiWrap")?.classList.toggle("hidden", !isSelisih);
            if (isSelisih) {
                const planId = document.getElementById("crosscheckPlanId")?.value;
                crosscheckAutoFill(planId).catch(() => {});
            }
        });
    });
    document.getElementById("crosscheckRekFileInput")?.addEventListener("change", function () {
        const nameEl = document.getElementById("crosscheckRekFileName");
        if (!nameEl) return;
        if (this.files?.[0]) {
            nameEl.textContent = this.files[0].name;
            nameEl.classList.remove("hidden");
        } else {
            nameEl.classList.add("hidden");
        }
    });
}

function setText(id, value) {
    const element = document.getElementById(id);

    if (element) {
        element.textContent = value ?? "-";
    }
}

function infoItem(label, value) {
    return `
        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-3">
            <div class="text-xs font-semibold uppercase tracking-wide text-slate-500">${escapeHtml(label)}</div>
            <div class="mt-1 text-sm font-semibold text-slate-200">${escapeHtml(value || "-")}</div>
        </div>
    `;
}

function emptyText(message) {
    return `
        <div class="rounded-xl border border-slate-800 bg-slate-900/70 p-4 text-sm text-slate-500">
            ${escapeHtml(message)}
        </div>
    `;
}

function showAlert(message, type = "success") {
    const alert = document.getElementById("reportAuditAlert");

    if (!alert) {
        return;
    }

    alert.textContent = message;
    alert.classList.remove(
        "hidden",
        "border-emerald-500/30",
        "bg-emerald-500/10",
        "text-emerald-200",
        "border-red-500/30",
        "bg-red-500/10",
        "text-red-200",
    );

    if (type === "error") {
        alert.classList.add(
            "border-red-500/30",
            "bg-red-500/10",
            "text-red-200",
        );
    } else {
        alert.classList.add(
            "border-emerald-500/30",
            "bg-emerald-500/10",
            "text-emerald-200",
        );
    }
}

function statusBadge(status) {
    const map = {
        draft: "bg-slate-500/10 text-slate-300 border-slate-500/20",
        open: "bg-blue-500/10 text-blue-300 border-blue-500/20",
        progress: "bg-amber-500/10 text-amber-300 border-amber-500/20",
        in_progress: "bg-amber-500/10 text-amber-300 border-amber-500/20",
        done: "bg-emerald-500/10 text-emerald-300 border-emerald-500/20",
        selesai: "bg-emerald-500/10 text-emerald-300 border-emerald-500/20",
        cancelled: "bg-red-500/10 text-red-300 border-red-500/20",
    };

    return map[status] || map.draft;
}

function safePercent(value) {
    const number = Number(value || 0);

    if (number < 0) {
        return 0;
    }

    if (number > 100) {
        return 100;
    }

    return number;
}

function formatDate(value) {
    if (!value) {
        return "-";
    }

    return String(value).slice(0, 10);
}

function formatDateTime(value) {
    if (!value) {
        return "-";
    }

    return String(value).replace("T", " ").slice(0, 19);
}

function escapeHtml(value) {
    return String(value ?? "")
        .replaceAll("&", "&amp;")
        .replaceAll("<", "&lt;")
        .replaceAll(">", "&gt;")
        .replaceAll('"', "&quot;")
        .replaceAll("'", "&#039;");
}

document.addEventListener("DOMContentLoaded", async () => {
    document
        .getElementById("closeReportAuditDetailButton")
        ?.addEventListener("click", closeDetail);

    setupFilters();
    setupTableActions();

    try {
        await loadCurrentUser();
        await loadReportSummary();
        await loadReportItems();
    } catch (error) {
        showAlert(error.message || "Gagal memuat Report Audit.", "error");
    }
});
