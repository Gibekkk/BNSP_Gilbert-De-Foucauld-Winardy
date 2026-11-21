<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Dasbor · PNSP</title>
        <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
    </head>
    <body>
        <header class="topbar">
            <div class="topbar-left">
                <span class="brand-mark" aria-hidden="true">P</span>
                <div class="brand-text">
                    <strong>PNSP</strong>
                    <small>Kelola garasi &amp; pelanggan</small>
                </div>
            </div>
            <div class="topbar-right">
                <span class="who" id="who-email">–</span>
                <a class="link" href="{{ route('admin.customers') }}">Verifikasi pelanggan</a>
                <a class="link" href="{{ route('admin.garages') }}">Kelola garasi</a>
                <a class="btn btn-sm btn-ghost-danger" href="#" id="logout-btn">Keluar</a>
            </div>
        </header>

        <main class="layout">
            <h2 class="page-title">Dasbor Admin</h2>
            <p class="page-sub">Ringkasan aktivitas, menara pendaftaran menunggu verifikasi, dan status garasi.</p>

            <div id="global-msg" class="msgbox error" role="alert" hidden></div>

            <!-- ===== Statistik ===== -->
            <section class="stat-grid" aria-label="Ringkasan statistik">
                <div class="stat-card">
                    <div class="stat-value" id="st-cust-total">–</div>
                    <div class="stat-label">Total pelanggan</div>
                </div>
                <div class="stat-card accent-warn">
                    <div class="stat-value" id="st-cust-pending">–</div>
                    <div class="stat-label">Menunggu verifikasi</div>
                </div>
                <div class="stat-card accent-ok">
                    <div class="stat-value" id="st-cust-accepted">–</div>
                    <div class="stat-label">Disetujui</div>
                </div>
                <div class="stat-card accent-fail">
                    <div class="stat-value" id="st-cust-declined">–</div>
                    <div class="stat-label">Ditolak</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="st-vehicles">–</div>
                    <div class="stat-label">Kendaraan terdaftar</div>
                </div>
                <div class="stat-card">
                    <div class="stat-value" id="st-garages">–/–</div>
                    <div class="stat-label">Garasi tersedia / total</div>
                </div>
            </section>

            <!-- ===== Registrasi terbaru ===== -->
            <section class="panel" aria-label="Registrasi terbaru">
                <div class="panel-head">
                    <h3>Registrasi terbaru</h3>
                    <a class="btn btn-primary" href="{{ route('admin.customers') }}">Buka verifikasi pelanggan</a>
                </div>

                <table class="data-table">
                    <thead>
                        <tr>
                            <th>Nama</th>
                            <th>NIK</th>
                            <th>Email</th>
                            <th>Status</th>
                        </tr>
                    </thead>
                    <tbody id="recent-body">
                        <tr class="empty-row" id="recent-hint"><td colspan="4">Memuat data…</td></tr>
                    </tbody>
                </table>
            </section>
        </main>

        <script>
            (function () {
                'use strict';

                const TOKEN_KEY = 'pnsps_token';
                const $ = (sel) => document.querySelector(sel);

                const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => (
                    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
                ));

                const STATUS_TEXT = { pending: 'Menunggu', accepted: 'Disetujui', declined: 'Ditolak' };

                function showMsg(text, kind) {
                    const box = $('#global-msg');
                    box.textContent = text;
                    box.className = 'msgbox ' + (kind || 'error');
                    box.hidden = false;
                }

                async function api(path) {
                    const res = await fetch(path, {
                        headers: {
                            'Accept': 'application/json',
                            'Authorization': 'Bearer ' + localStorage.getItem(TOKEN_KEY),
                        },
                    });
                    const data = await res.json().catch(() => ({}));
                    if (!res.ok) {
                        const err = new Error(data.message || 'Terjadi kesalahan.');
                        throw err;
                    }
                    return data;
                }

                function badge(status) {
                    const map = { pending: 'b-pending', accepted: 'b-accepted', declined: 'b-declined' };
                    return '<span class="badge-status ' + (map[status] || 'b-pending') + '">' + esc(STATUS_TEXT[status] || status) + '</span>';
                }

                async function boot() {
                    const token = localStorage.getItem(TOKEN_KEY);

                    if (!token) {
                        window.location.assign('/login');
                        return;
                    }

                    let me;

                    try {
                        me = (await api('/api/me')).user;
                    } catch (e) {
                        localStorage.removeItem(TOKEN_KEY);
                        window.location.assign('/login');
                        return;
                    }

                    if (!me.is_admin) {
                        window.location.assign('/customer');
                        return;
                    }

                    $('#who-email').textContent = me.email;

                    try {
                        const data = await api('/api/admin/dashboard');
                        fillStats(data.stats || {});
                        fillRecent(data.recent || []);
                    } catch (e) {
                        showMsg(e.message);
                    }
                }

                function fillStats(stats) {
                    const cust = stats.customers || {};
                    $('#st-cust-total').textContent = Number(cust.total ?? 0);
                    $('#st-cust-pending').textContent = Number(cust.pending ?? 0);
                    $('#st-cust-accepted').textContent = Number(cust.accepted ?? 0);
                    $('#st-cust-declined').textContent = Number(cust.declined ?? 0);
                    $('#st-vehicles').textContent = Number(stats.vehicles ?? 0);

                    const g = stats.garages || {};
                    $('#st-garages').textContent = Number(g.free ?? 0)+ ' / '+Number(g.total ?? 0);
                }

                function fillRecent(rows) {
                    const body = $('#recent-body');
                    body.innerHTML = '';

                    if (!rows.length) {
                        body.insertAdjacentHTML('beforeend', '<tr class="empty-row"><td colspan="4">Belum ada pendaftaran.</td></tr>');
                        return;
                    }

                    rows.forEach((row) => {
                        body.insertAdjacentHTML('beforeend', [
                            '<tr>',
                            '<td>'+esc(row.name)+'</td>',
                            '<td>'+esc(row.nik)+'</td>',
                            '<td>'+esc(row.email)+'</td>',
                            '<td>'+badge(row.status)+'</td>',
                            '</tr>',
                        ].join(''));
                    });
                }

                $('#logout-btn').addEventListener('click', async (event) => {
                    event.preventDefault();
                    const token = localStorage.getItem(TOKEN_KEY);
                    localStorage.removeItem(TOKEN_KEY);

                    if (token) {
                        try {
                            await fetch('/api/logout', {
                                method: 'POST',
                                headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
                            });
                        } catch (e) { /* token already dead */ }
                    }

                    window.location.assign('/login');
                });

                boot();
            })();
        </script>
    </body>
</html>
