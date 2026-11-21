<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kelola Garasi · BNSP</title>
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
    <a href="{{ route('admin.garages') }}" class="tab-btn active" aria-current="page">Kelola garasi</a>
    <a href="{{ route('admin.rental-approvals') }}" class="tab-btn">Verifikasi sewa</a>
    <a href="{{ route('admin.rentals') }}" class="tab-btn">Perpanjang sewa</a>
    <a href="{{ route('admin.reports') }}" class="tab-btn">Laporan</a>
</nav>

<main class="layout">
    <h2 class="page-title">Kelola garasi</h2>
    <p class="page-sub">Harga tersimpan di setiap slot (S001 s.d. S999, C001 s.d. C999, E001 s.d. E999 — tiap tipe maksimal 999 selamanya). Harga bulanan dihitung dari 25x harga harian.</p>

    <div id="global-msg" class="msgbox error" role="alert" hidden></div>

    <!-- ===== Kapasitas per tipe ===== -->
    <section class="panel" aria-label="Kapasitas">
        <div class="panel-head"><h3>Kapasitas per tipe</h3><span class="hint">Urutan berjalan tidak pernah dilepas atau dipakai ulang.</span></div>
        <div id="cap-grid" class="cap-grid">
            <div class="empty-row"><td colspan="4">Memuat…</td></div>
        </div>
    </section>

    <!-- ===== Buat slot baru ===== -->
    <section class="panel" aria-label="Buat garasi">
        <div class="panel-head"><h3>Buat slot baru</h3></div>
        <form id="create-form" novalidate>
            <div class="form-grid2">
                <div class="field">
                    <label for="c-type">Tipe</label>
                    <select id="c-type" name="garage_type_id">
                        <option value="S">Sepeda / Motor (S)</option>
                        <option value="C">Mobil (C)</option>
                        <option value="E">Mobil Listrik (E)</option>
                    </select>
                </div>
                <div class="field">
                    <label for="c-qty">Jumlah</label>
                    <input id="c-qty" name="quantity" type="number" min="1" max="999" step="1" value="1" inputmode="numeric">
                    <small class="hint">1 = buat satu; lebih dari 1 = massal (all-or-nothing).</small>
                </div>
                <div class="field">
                    <label for="c-rent">Sewa harian (Rp)</label>
                    <input id="c-rent" name="rent_price" type="number" min="1" step="1" inputmode="numeric" placeholder="mis. 50000">
                </div>
                <div class="field" id="c-kwh-wrap" hidden>
                    <label for="c-kwh">Tarif per kWh (Rp)</label>
                    <input id="c-kwh" name="charge_price_kwh" type="number" min="1" step="1" inputmode="numeric" placeholder="mis. 5000">
                    <small class="hint">Hanya untuk mobil listrik.</small>
                </div>
            </div>
            <p><button class="btn btn-primary" type="submit">Buat slot garasi</button></p>
        </form>
    </section>

    <!-- ===== Ubah harga satu slot ===== -->
    <section class="panel" id="edit-panel" aria-label="Ubah harga" hidden>
        <div class="edit-head">
            <h3>Ubah harga garasi</h3>
            <strong id="e-code"></strong>
            <small class="muted" id="e-note"></small>
        </div>
        <form id="edit-form" novalidate>
            <div class="form-grid2">
                <div class="field">
                    <label for="e-rent">Sewa harian (Rp)</label>
                    <input id="e-rent" name="rent_price" type="number" min="1" step="1" inputmode="numeric">
                    <small class="hint">Harga bulanan selalu 25x nilai ini.</small>
                </div>
                <div class="field" id="e-kwh-wrap" hidden>
                    <label for="e-kwh">Tarif per kWh (Rp)</label>
                    <input id="e-kwh" name="charge_price_kwh" type="number" min="1" step="1" inputmode="numeric">
                </div>
            </div>
            <p>
                <button class="btn btn-primary" type="submit">Simpan harga</button>
                <button class="btn btn-ghost" type="button" id="e-close">Batal</button>
            </p>
        </form>
    </section>

    <!-- ===== Daftar slot ===== -->
    <section class="panel" aria-label="Daftar slot">
        <div class="filter-bar">
            <label class="muted">Filter:
                <select id="l-type" style="padding:4px 8px;border-radius:8px;border:1px solid var(--border)">
                    <option value="">Semua</option>
                    <option value="S">Sepeda / Motor</option>
                    <option value="C">Mobil</option>
                    <option value="E">Mobil Listrik</option>
                </select>
            </label>
        </div>

        <table class="data-table" id="garage-table">
            <thead>
                <tr>
                    <th>Slot</th>
                    <th>Status</th>
                    <th>Sewa harian</th>
                    <th>Tarif listrik</th>
                    <th class="t-right col-act"></th>
                </tr>
            </thead>
            <tbody id="garage-body">
                <tr><td colspan="5" class="empty-row">Memuat…</td></tr>
            </tbody>
        </table>
        <div class="page-nav">
            <button class="btn btn-ghost" type="button" id="l-prev">← Sebelumnya</button>
            <span id="l-info" class="muted"></span>
            <button class="btn btn-ghost" type="button" id="l-next">Berikutnya →</button>
        </div>
    </section>
</main>

<script src="{{ asset('js/api.js') }}"></script>
<script>
(function () {
    'use strict';

    const TOKEN_KEY = 'bnsps_token';
    const $ = (sel) => document.querySelector(sel);

    function token() { return localStorage.getItem(TOKEN_KEY); }

    const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => (
        { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
    ));

    const rp = (n) => 'Rp ' + Number(n ?? 0).toLocaleString('id-ID');

    function showMsg(text, kind) {
        const box = $('#global-msg');
        box.textContent = text;
        box.className = 'msgbox ' + (kind || 'error');
        box.hidden = false;
        if (kind === 'ok') {
            window.setTimeout(() => { box.hidden = true; }, 6000);
        }
    }

    // Header Authorization + refresh token otomatis ditangani public/js/api.js.
    // (Versi lama menimpa header Authorization saat mengirim Content-Type.)
    const api = (path, options) => window.Api.request(path, options);

    /* ===== Kapasitas per tipe ===== */
    async function loadCapacities() {
        const grid = $('#cap-grid');
        let data;

        try {
            data = await api('/api/admin/garage-types');
        } catch (e) {
            grid.innerHTML = '<div class="empty-row"><td colspan="4">' + esc(e.message) + '</td></div>';
            return;
        }

        grid.innerHTML = '';
        data.data.forEach((t) => {
            const card = document.createElement('div');
            card.className = 'cap-card';
            card.innerHTML = [
                '<span class="cap-chip" aria-hidden="true">' + esc(t.key) + '</span>',
                '<span class="cap-info">',
                '<b>' + esc(t.label) + '</b>',
                '<small>Diterbitkan: ' + Number(t.count).toLocaleString('id-ID') + ' / 999 · Tersisa ' + Number(t.remaining_capacity).toLocaleString('id-ID') + '</small>',
                '</span>',
            ].join('');
            grid.appendChild(card);
        });
    }

    /* ===== Daftar slot ===== */
    let state = { page: 1, type: '' };
    let editing = null; // { id, label, isElectric }
    const rowsCache = {};

    function statusCell(used, rentedUntil) {
        if (!used) {
            return '<span class="chip ok">Kosong</span>';
        }
        const until = rentedUntil
            ? '<div class="muted" style="font-size:12px">s.d. ' + esc(new Date(rentedUntil + 'T00:00:00').toLocaleDateString('id-ID')) + '</div>'
            : '';
        return '<span class="chip warn">Terisi (used)</span>' + until;
    }

    async function loadList() {
        const body = $('#garage-body');
        const params = new URLSearchParams({ page: String(state.page) });

        if (state.type) {
            params.set('type', state.type);
        }

        let data;

        try {
            data = await api('/api/admin/garages?' + params.toString());
        } catch (e) {
            body.innerHTML = '<tr><td colspan="5" class="empty-row">' + esc(e.message) + '</td></tr>';
            $('#l-info').textContent = '';
            return;
        }

        const meta = data.meta || {};
        const items = data.data || [];

        if (!items.length) {
            body.innerHTML = '<tr><td colspan="5" class="empty-row">Belum ada slot garasi' + (state.type ? ' untuk tipe ini.' : '.') + '</td></tr>';
        } else {
            body.innerHTML = '';
            items.forEach((g) => {
                rowsCache[g.id] = g;
                const chargeCell = g.charge_price_kwh === null ? '—' : rp(g.charge_price_kwh) + ' /kWh';
                const tr = document.createElement('tr');
                tr.id = 'row-' + esc(g.id);
                tr.innerHTML = [
                    '<td><strong>' + esc(g.id) + '</strong> <span class="muted">· ' + esc(g.label) + '</span></td>',
                    '<td>' + statusCell(Boolean(g.is_used), g.rented_until) + '</td>',
                    '<td>' + rp(g.rent_price) + '<div class="muted" style="font-size:12px">25× = ' + rp(g.rent_price * 25) + '/bulan</div></td>',
                    '<td>' + chargeCell + '</td>',
                    '<td class="t-right"><button type="button" class="btn btn-sm btn-ghost" data-act="edit" data-id="' + esc(g.id) + '">Ubah harga</button></td>',
                ].join('');
                body.appendChild(tr);
            });
        }

        $('#l-info').textContent = items.length
            ? 'Halaman ' + Number(state.page) + ' dari ' + Number(meta.last_page || 1) + ' (' + Number(meta.total || 0) + ' slot)'
            : String(meta.total || 0) + ' slot';
    }

    /* ===== Buat slot (satu atau massal) ===== */
    async function create(event) {
        event.preventDefault();

        const type = $('#c-type').value;
        const qtyRaw = $('#c-qty').value.trim();
        const rentRaw = $('#c-rent').value.trim();
        const kwhRaw = $('#c-kwh').value.trim();

        if (type === 'E' && (kwhRaw === '' || (parseInt(kwhRaw, 10) || 0) < 1)) {
            showMsg('Tarif per kWh wajib diisi untuk mobil listrik.');
            return;
        }

        const quantity = parseInt(qtyRaw, 10) || 0;
        if (quantity < 1 || !/^\d+$/.test(qtyRaw)) {
            showMsg('Jumlah slot harus angka bulat minimal 1.');
            return;
        }

        const rent = parseInt(rentRaw, 10);
        if (!(rent > 0) || rent !== Number(rentRaw)) {
            showMsg('Harga sewa harian wajib berupa angka bulat positive.');
            return;
        }

        const isBulk = quantity > 1;
        const url = isBulk ? '/api/admin/garages/bulk' : '/api/admin/garages';
        const bodyObj = {
            garage_type_id: type,
            rent_price: rent,
        };

        if (type === 'E') {
            const kwh = parseInt(kwhRaw, 10);
            if (!(kwh > 0) || kwh !== Number(kwhRaw)) {
                showMsg('Tarif per kWh wajib berupa angka bulat positive.');
                return;
            }
            bodyObj.charge_price_kwh = kwh;
        }

        if (isBulk) {
            bodyObj.quantity = quantity;
        }

        try {
            await api(url, { method: 'POST', headers: { 'Content-Type': 'application/json' }, body: JSON.stringify(bodyObj) });
            showMsg(isBulk ? (quantity + ' slot garasi berhasil dibuat.') : 'Slot garasi baru berhasil dibuat.', 'ok');
            $('#c-rent').value = '';
        } catch (e) {
            showMsg(e.message);
            return;
        }

        state.type = type;
        state.page = 1;
        loadCapacities();
        loadList();
    }

    /* ===== Ubah harga satu slot ===== */
    function openEdit(id, row) {
        if (!row) {
            return;
        }

        editing = {
            id: row.id,
            label: row.label,
            isElectric: row.type === 'E',
        };

        $('#e-code').textContent = row.id + ' · ' + row.label;
        $('#e-note').textContent = row.is_used ? 'Terisi — perubahan hanya memengaruhi sewa berikutnya.' : 'Slot kosong.';
        $('#e-rent').value = Number(row.rent_price).toString();
        $('#e-kwh-wrap').hidden = !editing.isElectric;

        if (editing.isElectric) {
            $('#e-kwh').value = row.charge_price_kwh === null ? '' : Number(row.charge_price_kwh).toString();
        } else {
            $('#e-kwh').value = '';
        }

        $('#edit-panel').hidden = false;
        $('#edit-panel').scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    }

    async function saveEdit(event) {
        event.preventDefault();

        if (!editing) {
            return;
        }

        const rentRaw = $('#e-rent').value.trim();
        const kwhRaw = $('#e-kwh').value.trim();

        let data;

        try {
            data = await api('/api/admin/garages/' + encodeURIComponent(editing.id), {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    rent_price: parseInt(rentRaw, 10),
                    charge_price_kwh: editing.isElectric && kwhRaw !== '' ? parseInt(kwhRaw, 10) : null,
                }),
            });

            showMsg('Harga garasi ' + (data.data.id || editing.id) + ' berhasil disimpan.', 'ok');
        } catch (e) {
            showMsg(e.message);
            return;
        }

        $('#edit-panel').hidden = true;
        editing = null;
        loadList();
    }

    /* ===== Wiring awal ===== */
    function wire() {
        $('#create-form').addEventListener('submit', create);
        $('#c-type').addEventListener('change', () => {
            $('#c-kwh-wrap').hidden = $('#c-type').value !== 'E';
        });

        $('#l-type').addEventListener('change', (ev) => {
            state.type = ev.target.value;
            state.page = 1;
            loadList();
        });

        $('#l-prev').addEventListener('click', () => {
            if (state.page > 1) {
                state.page -= 1;
                loadList();
            }
        });

        $('#l-next').addEventListener('click', () => {
            state.page += 1;
            loadList();
        });

        $('#e-close').addEventListener('click', () => {
            $('#edit-panel').hidden = true;
            editing = null;
        });

        $('#edit-form').addEventListener('submit', saveEdit);

        $('#garage-body').addEventListener('click', (ev) => {
            const target = ev.target && ev.target.closest ? ev.target.closest('[data-act="edit"]') : null;
            if (!target || !target.dataset.id) {
                return;
            }
            openEdit(target.dataset.id, rowsCache[target.dataset.id]);
        });
    }

    $('#logout-btn').addEventListener('click', (event) => {
        event.preventDefault();
        window.Api.logout();
    });

    async function boot() {
        if (!token()) {
            window.location.replace('/login');
            return;
        }

        let me = null;

        try {
            me = (await api('/api/me')).user || null;
        } catch (_) {
            localStorage.removeItem(TOKEN_KEY);
            window.location.replace('/login');
            return;
        }

        if (!me || !me.is_admin) {
            window.location.replace(me ? '/customer' : '/login');
            return;
        }

        $('#who-email').textContent = me.email;
        wire();
        loadCapacities();
        loadList();
    }

    boot();
})();
</script>
</body>
</html>
