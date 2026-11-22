<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Pelanggan · Sewa Garasi</title>
    <link rel="stylesheet" href="{{ asset('vendor/datatables/datatables.min.css') }}">
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
        <header class="topbar">
            <div class="topbar-left">
                <span class="brand-mark" aria-hidden="true">B</span>
                <div class="brand-text">
                    <strong>Sewa Garasi</strong>
                    <small>Kelola garasi &amp; pelanggan</small>
                </div>
            </div>
            <div class="topbar-right">
                <span class="who" id="who-email">–</span>
                <a class="btn btn-sm btn-ghost-danger" href="#" id="logout-btn">Keluar</a>
            </div>
        </header>

        <nav class="tabs" aria-label="Navigasi admin">
            <a href="{{ route('admin') }}" class="tab-btn">Dasbor</a>
            <a href="{{ route('admin.customers') }}" class="tab-btn active" aria-current="page">Verifikasi pelanggan</a>
            <a href="{{ route('admin.garages') }}" class="tab-btn">Kelola garasi</a>
            <a href="{{ route('admin.rental-approvals') }}" class="tab-btn">Verifikasi sewa</a>
            <a href="{{ route('admin.rentals') }}" class="tab-btn">Perpanjang sewa</a>
            <a href="{{ route('admin.reports') }}" class="tab-btn">Laporan</a>
        </nav>

        <main class="layout">
            <h2 class="page-title">Verifikasi Pelanggan</h2>
            <p class="page-sub">Setujui pendaftaran yang valid, atau tolak secara final. NIK lengkap ditampilkan untuk pengecekan identitas.</p>

            <div id="global-msg" class="msgbox error" role="alert" hidden></div>

            <section class="panel" aria-label="Daftar pelanggan">
                <div class="panel-head">
                    <div class="chips">
                        <span class="chip warn">Menunggu: <b id="sum-pending">–</b></span>
                        <span class="chip ok">Disetujui: <b id="sum-accepted">–</b></span>
                        <span class="chip fail">Ditolak: <b id="sum-declined">–</b></span>
                    </div>
                    <button type="button" class="btn" id="reload-btn">Muat ulang</button>
                </div>

                <table id="customers-table" class="data-table" style="width:100%">
                    <thead>
                        <tr>
                            <th>NIK</th>
                            <th>No Telepon</th>
                            <th>Nama</th>
                            <th>Email</th>
                            <th>Terdaftar</th>
                            <th>Status</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr class="empty-row"><td colspan="7">Memuat data…</td></tr>
                    </tbody>
                </table>
            </section>
        </main>

        <script src="{{ asset('vendor/jquery.min.js') }}"></script>
        <script src="{{ asset('vendor/datatables/datatables.min.js') }}"></script>
        <script src="{{ asset('js/api.js') }}"></script>

        <script>
            (function () {
                'use strict';

                const TOKEN_KEY = 'bnsps_token';
                const $ = (sel) => document.querySelector(sel);
                let dtInstance = null;
                let rowsCache = [];

                const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => (
                    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
                ));

                const STATUS_TEXT = { pending: 'Menunggu', accepted: 'Disetujui', declined: 'Ditolak' };

                function showMsg(text, kind) {
                    const box = $('#global-msg');
                    box.textContent = text;
                    box.className = 'msgbox ' + (kind || 'error');
                    box.hidden = false;
                    clearTimeout(showMsg._t);
                    showMsg._t = setTimeout(() => { box.hidden = true; }, 6500);
                }

                // Header Authorization + refresh token otomatis ditangani public/js/api.js.
                const api = (path, options) => window.Api.request(path, options);

                function badge(status) {
                    const map = { pending: 'b-pending', accepted: 'b-accepted', declined: 'b-declined' };
                    return '<span class="badge-status ' + (map[status] || 'b-pending') + '">' + esc(STATUS_TEXT[status] || status) + '</span>';
                }

                function actionCell(row) {
                    if (row.status !== 'pending') {
                        return '<span class="muted">–</span>';
                    }

                    return [
                        '<button type="button" class="btn btn-ok act-approve" data-id="' + row.id + '">Setujui</button>',
                        '<button type="button" class="btn btn-danger-soft act-decline" data-id="' + row.id + '">Tolak</button>',
                    ].join(' ');
                }

                function updateSummary(rows) {
                    const count = (status) => rows.filter((r) => r.status === status).length;
                    $('#sum-pending').textContent = count('pending');
                    $('#sum-accepted').textContent = count('accepted');
                    $('#sum-declined').textContent = count('declined');
                }

                async function load() {
                    const data = await api('/api/admin/customers');
                    rowsCache = data.data || [];
                    updateSummary(rowsCache);
                    renderTable();
                }

                function renderTable() {
                    if (window.DataTable) {
                        if (dtInstance) {
                            dtInstance.clear().rows.add(rowsCache).draw();
                            return;
                        }

                        dtInstance = new window.DataTable(document.querySelector('#customers-table'), {
                            data: rowsCache,
                            order: [],
                            pageLength: 10,
                            lengthMenu: [10, 25, 50],
                            columns: [
                                { data: 'nik' },
                                { data: 'phone' },
                                { data: 'name' },
                                { data: 'email' },
                                { data: 'created_at', render: (value) => new Date(value).toLocaleDateString('id-ID') },
                                { data: 'status', render: (value) => badge(value), "class": 'no-sort' },
                                { data: null, render: (row) => actionCell(row), orderable: false, searchable: false },
                            ],
                            language: {
                                search: 'Cari:',
                                emptyTable: 'Belum ada pelanggan terdaftar.',
                                zeroRecords: 'Tidak ditemukan hasil untuk pencarian tersebut.',
                                info: 'Menampilkan _START_–_END_ dari _TOTAL_ pelanggan',
                                lengthMenu: 'Tampilkan _MENU_',
                            },
                        });
                    } else {
                        // Fallback sederhana bila pustaka DataTables gagal dimuat.
                        const body = document.querySelector('#customers-table tbody');
                        body.innerHTML = '';

                        rowsCache.forEach((row) => {
                            body.insertAdjacentHTML('beforeend', [
                                '<tr>',
                                '<td>'+esc(row.nik)+'</td>',
                                '<td>'+esc(row.phone)+'</td>',
                                '<td>'+esc(row.name)+'</td>',
                                '<td>'+esc(row.email)+'</td>',
                                '<td>'+esc(new Date(row.created_at).toLocaleDateString('id-ID'))+'</td>',
                                '<td>'+badge(row.status)+'</td>',
                                '<td>'+actionCell(row)+'</td>',
                                '</tr>',
                            ].join(''));
                        });

                        if (!rowsCache.length) {
                            body.insertAdjacentHTML('beforeend', '<tr class="empty-row"><td colspan="7">Belum ada pelanggan terdaftar.</td></tr>');
                        }
                    }
                }

                $('#customers-table').addEventListener('click', async (event) => {
                    const approveBtn = event.target.closest('.act-approve');
                    const declineBtn = event.target.closest('.act-decline');

                    if (!approveBtn && !declineBtn) {
                        return;
                    }

                    const id = parseInt(approveBtn ? approveBtn.dataset.id : declineBtn.dataset.id, 10);
                    const row = rowsCache.find((r) => String(r.id) === String(id));

                    if (approveBtn) {
                        try {
                            await api('/api/admin/customers/' + id + '/approve', { method: 'POST' });
                            showMsg(row ? ('Pendaftaran '+esc(row.name)+' disetujui.') : 'Disetujui.', 'ok');
                            await load();
                        } catch (e) {
                            showMsg(e.message, 'error');
                        }

                        return;
                    }

                    const sure = confirm('Tolak pendaftaran ini secara final? Calon pelanggan akan tetap dapat mendaftarkan ulang.');

                    if (!sure) {
                        return;
                    }

                    try {
                        await api('/api/admin/customers/' + id + '/decline', { method: 'POST' });
                        showMsg('Penolakan disimpan. Pendaftaran kini final.', 'ok');
                        await load();
                    } catch (e) {
                        showMsg(e.message, 'error');
                    }
                });

                $('#reload-btn').addEventListener('click', () => { load().catch((e) => showMsg(e.message)); });

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
                        showMsg('Sesi tidak valid. '+e.message);
                        localStorage.removeItem(TOKEN_KEY);
                        window.location.assign('/login');
                        return;
                    }

                    if (!me.is_admin) {
                        window.location.assign('/customer');
                        return;
                    }

                    $('#who-email').textContent = me.email;
                    load().catch((e) => showMsg(e.message));
                }

                $('#logout-btn').addEventListener('click', (event) => {
                    event.preventDefault();
                    window.Api.logout();
                });

                boot();
            })();
        </script>
    </body>
</html>
