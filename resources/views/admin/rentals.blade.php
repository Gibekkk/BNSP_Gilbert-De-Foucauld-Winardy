<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Perpanjang Sewa · Sewa Garasi</title>
    <link rel="stylesheet" href="{{ asset('css/app.css') }}">
    <link rel="stylesheet" href="{{ asset('css/admin.css') }}">
</head>
<body>
<header class="topbar">
    <div class="topbar-left">
        <span class="brand-mark" aria-hidden="true">B</span>
        <div class="brand-text"><strong>Sewa Garasi</strong><small>Dasbor admin</small></div>
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
    <a href="{{ route('admin.rental-approvals') }}" class="tab-btn">Verifikasi sewa</a>
    <a href="{{ route('admin.rentals') }}" class="tab-btn active" aria-current="page">Perpanjang sewa</a>
    <a href="{{ route('admin.reports') }}" class="tab-btn">Laporan</a>
</nav>

<main class="layout">
    <h2 class="page-title">Perpanjang status garasi</h2>
    <p class="page-sub">Pilih garasi yang sedang disewa hari ini, tentukan tanggal selesai sewa yang baru, lalu unggah bukti bayar sisa biaya. Sewa tidak dapat diperpendek.</p>

    <div id="global-msg" class="msgbox error" role="alert" hidden></div>

    <!-- ===== Daftar sewa aktif hari ini ===== -->
    <section class="panel" aria-label="Garasi yang sedang disewa">
        <div class="panel-head">
            <h3>Garasi disewa hari ini <span class="muted" id="today-label"></span></h3>
            <button class="btn btn-ghost" type="button" id="reload-btn">Muat ulang</button>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Garasi</th>
                        <th>Penyewa</th>
                        <th>Kendaraan</th>
                        <th>Tipe sewa</th>
                        <th>Mulai</th>
                        <th>Selesai</th>
                        <th class="t-right"></th>
                    </tr>
                </thead>
                <tbody id="rental-body">
                    <tr class="empty-row"><td colspan="7">Memuat…</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ===== Riwayat semua perpanjangan ===== -->
    <section class="panel" aria-label="Riwayat perpanjangan">
        <div class="panel-head">
            <h3>Riwayat perpanjangan</h3>
            <span class="hint">Seluruh perpanjangan, termasuk sewa yang sudah selesai (100 terbaru).</span>
        </div>
        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th>Waktu</th>
                        <th>Garasi</th>
                        <th>Penyewa</th>
                        <th>Selesai lama → baru</th>
                        <th class="t-right">Biaya</th>
                        <th>Bukti</th>
                    </tr>
                </thead>
                <tbody id="history-body">
                    <tr class="empty-row"><td colspan="6">Memuat…</td></tr>
                </tbody>
            </table>
        </div>
    </section>

    <!-- ===== Form perpanjangan ===== -->
    <section class="panel" id="extend-panel" aria-label="Perpanjang sewa" hidden>
        <div class="edit-head">
            <h3>Perpanjang sewa garasi</h3>
            <strong id="x-code"></strong>
        </div>

        <div class="detail-grid" id="x-detail"></div>

        <form id="extend-form" novalidate>
            <div class="form-grid2">
                <div class="field">
                    <label for="x-current">Tanggal selesai saat ini</label>
                    <input id="x-current" type="date" readonly>
                </div>
                <div class="field">
                    <label for="x-new">Tanggal selesai baru</label>
                    <input id="x-new" name="new_end_date" type="date" required>
                    <small class="hint">Harus setelah tanggal selesai saat ini.</small>
                </div>
                <div class="field">
                    <label for="x-proof">Bukti bayar (gambar)</label>
                    <input id="x-proof" name="transaction_proof" type="file" accept="image/png,image/jpeg,image/webp" required>
                    <small class="hint">JPG / PNG / WEBP, maks. 5 MB.</small>
                </div>
            </div>

            <div class="quote-box" id="x-quote">
                <div class="muted">Sisa biaya yang harus dibayarkan</div>
                <div class="quote-total" id="x-total">–</div>
                <ul id="x-segments"></ul>
            </div>

            <p>
                <button class="btn btn-primary" type="submit" id="x-submit">Simpan perpanjangan</button>
                <button class="btn btn-ghost" type="button" id="x-close">Batal</button>
            </p>
        </form>

        <div id="x-history"></div>
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
    const fmtDate = (iso) => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '–';

    /** Tambah n hari ke tanggal ISO (YYYY-MM-DD) tanpa terpengaruh zona waktu. */
    function addDays(iso, n) {
        const [y, m, d] = iso.split('-').map(Number);
        const date = new Date(Date.UTC(y, m - 1, d + n));
        return date.toISOString().slice(0, 10);
    }

    let rows = [];
    let selected = null;
    let preselectId = (() => {
        const raw = new URLSearchParams(window.location.search).get('transaction');
        return raw && /^\d+$/.test(raw) ? Number(raw) : null;
    })();
    let quoteTimer = null;
    let quoteSeq = 0;

    function showMsg(text, kind) {
        const box = $('#global-msg');
        box.textContent = text;
        box.className = 'msgbox ' + (kind || 'error');
        box.hidden = false;
        clearTimeout(showMsg._t);
        if (kind === 'ok') {
            showMsg._t = setTimeout(() => { box.hidden = true; }, 7000);
        }
        window.scrollTo({ top: 0, behavior: 'smooth' });
    }

    /* ===== Daftar sewa aktif ===== */
    async function load() {
        const body = $('#rental-body');
        let data;

        try {
            data = await api('/api/admin/rentals');
        } catch (e) {
            body.innerHTML = '<tr class="empty-row"><td colspan="7">' + esc(e.message) + '</td></tr>';
            return;
        }

        rows = data.data || [];
        $('#today-label').textContent = '· ' + fmtDate(data.today);

        if (!rows.length) {
            body.innerHTML = '<tr class="empty-row"><td colspan="7">Tidak ada garasi yang sedang disewa hari ini.</td></tr>';
            closePanel();
            return;
        }

        body.innerHTML = rows.map((r) => [
            '<tr data-id="' + r.id + '"' + (selected && selected.id === r.id ? ' class="is-selected"' : '') + '>',
            '<td><strong>' + esc(r.garage_id) + '</strong> <span class="muted">· ' + esc(r.garage_label) + '</span></td>',
            '<td>' + esc(r.customer.name) + '<div class="muted">' + esc(r.customer.phone || '') + '</div></td>',
            '<td>' + esc(r.vehicle.type_label) + '<div class="muted">' + esc([r.vehicle.license_plate, r.vehicle.brand].filter(Boolean).join(' · ')) + '</div></td>',
            '<td>' + (r.is_monthly ? 'Bulanan' : 'Harian') + (r.extensions.length ? ' <span class="chip warn">' + r.extensions.length + '× diperpanjang</span>' : '') + '</td>',
            '<td>' + fmtDate(r.start_date) + '</td>',
            '<td><strong>' + fmtDate(r.end_date) + '</strong></td>',
            '<td class="t-right"><button type="button" class="btn btn-sm btn-primary" data-act="pick" data-id="' + r.id + '">Perpanjang</button></td>',
            '</tr>',
        ].join('')).join('');

        // Dibuka dari tombol "Perpanjang" di Kelola garasi: pilih sewa dari ?transaction=ID.
        if (preselectId !== null) {
            const target = rows.find((r) => r.id === preselectId);
            preselectId = null;
            history.replaceState(null, '', window.location.pathname);
            if (target) {
                openPanel(target, false);
                return;
            }
            showMsg('Sewa tersebut tidak dapat diperpanjang (belum disetujui atau sudah tidak berjalan hari ini).');
        }

        // Segarkan panel bila transaksi yang dipilih masih ada.
        if (selected) {
            const fresh = rows.find((r) => r.id === selected.id);
            fresh ? openPanel(fresh, true) : closePanel();
        }
    }

    /* ===== Riwayat semua perpanjangan ===== */
    async function loadHistory() {
        const body = $('#history-body');
        let data;

        try {
            data = await api('/api/admin/rentals/extensions');
        } catch (e) {
            body.innerHTML = '<tr class="empty-row"><td colspan="6">' + esc(e.message) + '</td></tr>';
            return;
        }

        const items = data.data || [];
        body.innerHTML = items.length ? items.map((h) => [
            '<tr>',
            '<td>' + esc(h.created_at ? new Date(h.created_at).toLocaleString('id-ID', { day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit' }) : '–') + '</td>',
            '<td><strong>' + esc(h.garage_id) + '</strong><div class="muted">' + esc(h.garage_label) + '</div></td>',
            '<td>' + esc(h.customer_name) + '<div class="muted">' + esc(h.vehicle) + '</div></td>',
            '<td>' + fmtDate(h.current_end_date) + ' → <strong>' + fmtDate(h.new_end_date) + '</strong></td>',
            '<td class="t-right">' + rp(h.extra_rent) + '</td>',
            '<td>' + (h.transaction_proof_url ? '<a class="link" href="' + esc(h.transaction_proof_url) + '" target="_blank" rel="noopener">Lihat</a>' : '–') + '</td>',
            '</tr>',
        ].join('')).join('') : '<tr class="empty-row"><td colspan="6">Belum ada perpanjangan.</td></tr>';
    }

    /* ===== Panel perpanjangan ===== */
    function openPanel(row, keepInputs) {
        selected = row;
        document.querySelectorAll('#rental-body tr').forEach((tr) => {
            tr.classList.toggle('is-selected', Number(tr.dataset.id) === row.id);
        });

        $('#x-code').textContent = row.garage_id + ' · ' + row.garage_label;
        $('#x-detail').innerHTML = [
            '<div><span>Penyewa</span>' + esc(row.customer.name) + ' (NIK ' + esc(row.customer.nik || '-') + ')</div>',
            '<div><span>Kendaraan</span>' + esc(row.vehicle.type_label) + ' ' + esc([row.vehicle.license_plate, row.vehicle.brand].filter(Boolean).join(' · ')) + '</div>',
            '<div><span>Harga sewa harian</span>' + rp(row.daily_price) + ' <span class="muted">(bulanan ' + rp(row.daily_price * 25) + ')</span></div>',
            '<div><span>Biaya awal</span>' + rp(row.rent_price_total) + (row.charge_price_total ? ' <span class="muted">(termasuk isi listrik ' + rp(row.charge_price_total) + ')</span>' : '') +
                (row.transaction_proof_url ? ' · <a class="link" href="' + esc(row.transaction_proof_url) + '" target="_blank" rel="noopener">bukti</a>' : '') + '</div>',
        ].join('');

        const minDate = addDays(row.end_date, 1);
        $('#x-current').value = row.end_date;
        const input = $('#x-new');
        input.min = minDate;
        if (!keepInputs || !input.value || input.value < minDate) {
            input.value = '';
            $('#x-proof').value = '';
            resetQuote();
        }

        renderHistory(row);
        $('#extend-panel').hidden = false;
        if (!keepInputs) {
            $('#extend-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
    }

    function closePanel() {
        selected = null;
        $('#extend-panel').hidden = true;
        document.querySelectorAll('#rental-body tr.is-selected').forEach((tr) => tr.classList.remove('is-selected'));
    }

    function renderHistory(row) {
        if (!row.extensions.length) {
            $('#x-history').innerHTML = '';
            return;
        }

        $('#x-history').innerHTML = [
            '<h4 style="margin:18px 0 6px">Riwayat perpanjangan</h4>',
            '<div class="table-scroll"><table class="data-table"><thead><tr><th>Dari</th><th>Menjadi</th><th>Biaya</th><th>Bukti</th></tr></thead><tbody>',
            row.extensions.map((e) => '<tr><td>' + fmtDate(e.current_end_date) + '</td><td>' + fmtDate(e.new_end_date) + '</td><td>' + rp(e.extra_rent) + '</td><td>' +
                (e.transaction_proof_url ? '<a class="link" href="' + esc(e.transaction_proof_url) + '" target="_blank" rel="noopener">Lihat</a>' : '–') + '</td></tr>').join(''),
            '</tbody></table></div>',
        ].join('');
    }

    /* ===== Pratinjau sisa biaya (dihitung server) ===== */
    function resetQuote(text) {
        $('#x-total').textContent = text || '–';
        $('#x-segments').innerHTML = '';
    }

    async function updateQuote() {
        const value = $('#x-new').value;
        if (!selected || !value) {
            resetQuote();
            return;
        }

        const seq = ++quoteSeq;
        resetQuote('Menghitung…');

        try {
            const res = await api('/api/admin/rentals/' + selected.id + '/quote?new_end_date=' + encodeURIComponent(value));
            if (seq !== quoteSeq) {
                return; // sudah ada input yang lebih baru
            }
            const q = res.data;
            $('#x-total').textContent = rp(q.total) + ' · ' + q.days + ' hari';
            $('#x-segments').innerHTML = q.segments.map((s) => '<li>' + fmtDate(s.from) + ' – ' + fmtDate(s.to) + ': ' +
                (s.monthly ? '1 bulan = ' + s.days + ' hari (25 × ' + rp(q.daily_price) + ')' : s.days + ' hari × ' + rp(q.daily_price)) +
                ' = <strong>' + rp(s.amount) + '</strong></li>').join('');
        } catch (e) {
            if (seq === quoteSeq) {
                resetQuote(e.message);
            }
        }
    }

    /* ===== Simpan ===== */
    async function submit(event) {
        event.preventDefault();
        if (!selected) {
            return;
        }

        const newEnd = $('#x-new').value;
        const file = $('#x-proof').files[0];

        if (!newEnd) {
            showMsg('Tanggal selesai baru wajib diisi.');
            return;
        }
        if (newEnd <= selected.end_date) {
            showMsg('Tanggal selesai baru harus setelah ' + fmtDate(selected.end_date) + '. Sewa tidak dapat diperpendek.');
            return;
        }
        if (!file) {
            showMsg('Bukti bayar wajib diunggah.');
            return;
        }
        if (file.size > 5 * 1024 * 1024) {
            showMsg('Ukuran bukti bayar maksimal 5 MB.');
            return;
        }

        const form = new FormData();
        form.append('new_end_date', newEnd);
        form.append('transaction_proof', file);

        const btn = $('#x-submit');
        btn.disabled = true;
        btn.textContent = 'Menyimpan…';

        try {
            const res = await api('/api/admin/rentals/' + selected.id + '/extend', { method: 'POST', body: form });
            selected = res.data;
            $('#x-new').value = '';
            $('#x-proof').value = '';
            resetQuote();
            showMsg(res.message, 'ok');
            await load();
            loadHistory();
        } catch (e) {
            showMsg(e.message);
        } finally {
            btn.disabled = false;
            btn.textContent = 'Simpan perpanjangan';
        }
    }

    function wire() {
        $('#rental-body').addEventListener('click', (ev) => {
            const btn = ev.target.closest('[data-act="pick"]');
            if (!btn) {
                return;
            }
            const row = rows.find((r) => r.id === Number(btn.dataset.id));
            if (row) {
                openPanel(row, false);
            }
        });

        $('#x-new').addEventListener('change', () => {
            clearTimeout(quoteTimer);
            quoteTimer = setTimeout(updateQuote, 150);
        });
        $('#extend-form').addEventListener('submit', submit);
        $('#x-close').addEventListener('click', closePanel);
        $('#reload-btn').addEventListener('click', () => { load(); loadHistory(); });
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
        loadHistory();
    }

    boot();
})();
</script>
</body>
</html>
