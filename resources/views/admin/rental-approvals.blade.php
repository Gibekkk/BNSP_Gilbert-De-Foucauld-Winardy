<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Verifikasi Sewa · BNSP</title>
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<header class="topbar">
    <div class="topbar-left">
        <span class="brand-mark" aria-hidden="true">B</span>
        <div class="brand-text"><strong>BNSP</strong><small>Dasbor admin</small></div>
    </div>
    <div class="topbar-right">
        <span class="who" id="who-email">–</span>
        <a class="btn btn-sm btn-ghost-danger" href="#" id="logout-btn">Keluar</a>
    </div>
</header>

<nav class="tabs" aria-label="Navigasi admin">
    <a href="{{ route('admin') }}" class="tab-btn">Dasbor</a>
    <a href="{{ route('admin.customers') }}" class="tab-btn">Verifikasi pelanggan</a>
    <a href="{{ route('admin.garages') }}" class="tab-btn">Kelola garasi</a>
    <a href="{{ route('admin.rental-approvals') }}" class="tab-btn active" aria-current="page">Verifikasi sewa</a>
    <a href="{{ route('admin.rentals') }}" class="tab-btn">Perpanjang sewa</a>
    <a href="{{ route('admin.reports') }}" class="tab-btn">Laporan</a>
</nav>

<main class="layout">
    <h2 class="page-title">Verifikasi sewa garasi</h2>
    <p class="page-sub">Periksa bukti pelunasan setiap pengajuan sewa, lalu setujui atau tolak. Selama menunggu, garasi tetap ditahan; bila ditolak, garasi langsung tersedia kembali. Keputusan bersifat final.</p>

    <div id="global-msg" class="msgbox error" role="alert" hidden></div>

    <div class="seg" role="tablist" aria-label="Filter status">
        <button type="button" data-status="pending" class="active">Menunggu <span class="chip warn" id="cnt-pending">0</span></button>
        <button type="button" data-status="approved">Disetujui <span class="chip ok" id="cnt-approved">0</span></button>
        <button type="button" data-status="declined">Ditolak <span class="chip fail" id="cnt-declined">0</span></button>
    </div>

    <section class="panel" aria-label="Daftar pengajuan sewa">
        <div class="panel-head">
            <h3 id="list-title">Menunggu verifikasi</h3>
            <button class="btn btn-ghost" type="button" id="reload-btn">Muat ulang</button>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Garasi</th>
                        <th>Penyewa</th>
                        <th>Kendaraan</th>
                        <th>Sewa</th>
                        <th class="t-right">Total</th>
                        <th>Bukti</th>
                        <th class="t-right" id="col-action">Aksi</th>
                    </tr>
                </thead>
                <tbody id="approval-body">
                    <tr class="empty-row"><td colspan="7">Memuat…</td></tr>
                </tbody>
            </table>
        </div>
    </section>
</main>

<script src="{{ asset('js/api.js') }}"></script>
<script>
(function () {
    'use strict';

    const $ = (sel) => document.querySelector(sel);
    const api = (path, options) => window.Api.request(path, options);

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));
    const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');
    const fmtDate = (iso) => iso ? new Date(iso.length === 10 ? iso + 'T00:00:00' : iso).toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '–';
    const fmtDateTime = (iso) => iso ? new Date(iso).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '–';

    const TITLES = { pending: 'Menunggu verifikasi', approved: 'Disetujui', declined: 'Ditolak' };
    let status = 'pending';
    let busy = false;

    function showMsg(text, kind) {
        const box = $('#global-msg');
        box.textContent = text;
        box.className = 'msgbox ' + (kind || 'error');
        box.hidden = false;
        clearTimeout(showMsg._t);
        if (kind === 'ok') {
            showMsg._t = setTimeout(() => { box.hidden = true; }, 6500);
        }
    }

    function actionCell(r) {
        if (r.status === 'pending') {
            return '<button type="button" class="btn btn-sm btn-ok" data-act="approve" data-id="' + r.id + '">Setujui</button>' +
                '<button type="button" class="btn btn-sm btn-danger-soft" data-act="decline" data-id="' + r.id + '">Tolak</button>';
        }
        const note = r.status_note ? '<div class="muted">Alasan: ' + esc(r.status_note) + '</div>' : '';
        return '<span class="muted">' + fmtDateTime(r.verified_at) + '</span>' + note;
    }

    function row(r) {
        const battery = r.battery_level !== null
            ? '<div class="muted">Sisa baterai ' + Number(r.battery_level) + '% · kapasitas ' + Number(r.vehicle.battery_capacity) + ' kWh</div>'
            : '';
        const charge = r.charge_price_total ? '<div class="muted">termasuk listrik ' + rp(r.charge_price_total) + '</div>' : '';

        return [
            '<tr>',
            '<td><strong>' + esc(r.garage_id) + '</strong><div class="muted">' + esc(r.garage_label) + '</div></td>',
            '<td>' + esc(r.customer.name) + '<div class="muted">NIK ' + esc(r.customer.nik || '-') + '</div><div class="muted">' + esc(r.customer.phone || '') + '</div></td>',
            '<td>' + esc(r.vehicle.type_label) + '<div class="muted">' + esc([r.vehicle.license_plate, r.vehicle.brand].filter(Boolean).join(' · ')) + '</div>' + battery + '</td>',
            '<td>' + (r.is_monthly ? 'Bulanan' : 'Harian') + '<div class="muted">' + fmtDate(r.start_date) + ' – ' + fmtDate(r.end_date) + '</div><div class="muted">Diajukan ' + fmtDateTime(r.submitted_at) + '</div></td>',
            '<td class="t-right"><strong>' + rp(r.rent_price_total) + '</strong>' + charge + '</td>',
            '<td>' + (r.transaction_proof_url ? '<a class="link" href="' + esc(r.transaction_proof_url) + '" target="_blank" rel="noopener">Lihat bukti</a>' : '–') + '</td>',
            '<td class="t-right">' + actionCell(r) + '</td>',
            '</tr>',
        ].join('');
    }

    async function load() {
        const body = $('#approval-body');
        let res;

        try {
            res = await api('/api/admin/rental-approvals?status=' + encodeURIComponent(status));
        } catch (e) {
            body.innerHTML = '<tr class="empty-row"><td colspan="7">' + esc(e.message) + '</td></tr>';
            return;
        }

        const counts = res.counts || {};
        $('#cnt-pending').textContent = Number(counts.pending || 0);
        $('#cnt-approved').textContent = Number(counts.approved || 0);
        $('#cnt-declined').textContent = Number(counts.declined || 0);
        $('#list-title').textContent = TITLES[status];
        $('#col-action').textContent = status === 'pending' ? 'Aksi' : 'Diverifikasi';

        const rows = res.data || [];
        body.innerHTML = rows.length
            ? rows.map(row).join('')
            : '<tr class="empty-row"><td colspan="7">' + (status === 'pending' ? 'Tidak ada pengajuan sewa yang menunggu verifikasi.' : 'Belum ada data.') + '</td></tr>';
    }

    async function act(id, action) {
        if (busy) {
            return;
        }

        let options = { method: 'POST' };

        if (action === 'decline') {
            const reason = window.prompt('Alasan penolakan (opsional, akan ditampilkan ke penyewa):', '');
            if (reason === null) {
                return; // dibatalkan
            }
            options.body = JSON.stringify({ reason: reason });
        } else if (!window.confirm('Setujui sewa ini? Keputusan bersifat final.')) {
            return;
        }

        busy = true;
        try {
            const res = await api('/api/admin/rental-approvals/' + id + '/' + action, options);
            showMsg(res.message, 'ok');
        } catch (e) {
            showMsg(e.message);
        } finally {
            busy = false;
            load();
        }
    }

    function wire() {
        document.querySelectorAll('.seg button').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.seg button').forEach((b) => b.classList.toggle('active', b === btn));
                status = btn.dataset.status;
                load();
            });
        });

        $('#approval-body').addEventListener('click', (ev) => {
            const btn = ev.target.closest('[data-act]');
            if (btn) {
                act(btn.dataset.id, btn.dataset.act);
            }
        });

        $('#reload-btn').addEventListener('click', load);
        $('#logout-btn').addEventListener('click', (event) => {
            event.preventDefault();
            window.Api.logout();
        });
    }

    async function boot() {
        if (!window.Api.token()) {
            window.location.replace('/login');
            return;
        }

        let me;
        try {
            me = (await api('/api/me')).user;
        } catch (_) {
            window.Api.goLogin();
            return;
        }

        if (!me || !me.is_admin) {
            window.location.replace('/customer');
            return;
        }

        $('#who-email').textContent = me.email;
        wire();
        load();
    }

    boot();
})();
</script>
</body>
</html>
