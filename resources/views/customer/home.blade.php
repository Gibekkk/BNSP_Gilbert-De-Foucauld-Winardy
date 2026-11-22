<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Portal Pelanggan · Sewa Garasi</title>
        <link rel="stylesheet" href="{{ asset('css/app.css') }}">
        <link rel="stylesheet" href="{{ asset('css/customer.css') }}">
    </head>
    <body>
        <header class="topbar">
            <div class="topbar-left">
                <span class="brand-mark" aria-hidden="true">B</span>
                <div class="brand-text">
                    <strong>Sewa Garasi</strong>
                    <small>Portal pelanggan</small>
                </div>
            </div>
            <div class="topbar-right">
                <span class="who" id="who-name">–</span>
                <a class="btn btn-sm btn-ghost-danger" href="#" id="logout-btn">Keluar</a>
            </div>
        </header>

        <nav class="tabs" aria-label="Navigasi portal">
            <a href="?tab=garage" class="tab-btn active" data-tab="garage">Garasi</a>
            <a href="?tab=kendaraan" class="tab-btn" data-tab="kendaraan">Kendaraan</a>
        </nav>

        <main class="layout">
            <h2 class="page-title">Halo, <span id="hello-name">…</span></h2>
            <p class="page-sub">Sewa garasi, pantau sewaan, dan kelola kendaraan Anda di satu tempat.</p>

            <div id="global-msg" class="msgbox error" role="alert" hidden></div>

            <!-- ===== Tab: Garasi (default): sewaan saya + sewa baru ===== -->
            <div id="tab-garage">
                <section class="panel" aria-label="Sewaan saya">
                    <div class="panel-head">
                        <h3>Sewaan Saya</h3>
                        <button type="button" class="btn btn-primary" id="new-rent-btn">+ Sewa baru</button>
                    </div>

                    <div class="table-scroll">
                        <table class="vtable">
                            <thead>
                                <tr>
                                    <th>Garasi</th>
                                    <th>Kendaraan</th>
                                    <th>Tipe sewa</th>
                                    <th>Periode</th>
                                    <th>Total</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="rent-body">
                                <tr class="empty-row"><td colspan="6">Memuat data…</td></tr>
                            </tbody>
                        </table>
                    </div>

                    <details id="history-wrap" hidden>
                        <summary class="muted" style="cursor:pointer;margin-top:12px">Riwayat sewa (<span id="history-count">0</span>)</summary>
                        <div class="table-scroll">
                            <table class="vtable">
                                <tbody id="history-body"></tbody>
                            </table>
                        </div>
                    </details>
                </section>

                <!-- ===== Form sewa baru ===== -->
                <section class="panel" id="rent-panel" aria-label="Sewa baru" hidden>
                    <div class="panel-head">
                        <h3>Sewa Baru</h3>
                        <a href="#" class="muted" id="rent-cancel">Batal</a>
                    </div>

                    <div id="rent-msg" class="msgbox error" role="alert" hidden></div>

                    <form id="rent-form" class="rent-steps" novalidate>
                        <div class="rent-step">
                            <h4><span class="num">1</span>Kendaraan</h4>
                            <div class="field">
                                <select id="r-vehicle"></select>
                            </div>
                            <p class="muted" id="r-vehicle-note" hidden>Belum ada kendaraan. Tambahkan kendaraan terlebih dahulu di tab <a href="?tab=kendaraan" class="link" id="go-vehicles">Kendaraan</a>.</p>
                        </div>

                        <div class="rent-step">
                            <h4><span class="num">2</span>Tipe sewa &amp; tanggal</h4>
                            <div class="seg" role="tablist" aria-label="Tipe sewa">
                                <button type="button" data-monthly="0" class="active">Harian</button>
                                <button type="button" data-monthly="1">Bulanan</button>
                            </div>
                            <div class="form-grid" style="margin-top:12px">
                                <div class="field" id="r-days-wrap">
                                    <label for="r-days">Jumlah hari</label>
                                    <input type="number" id="r-days" min="1" step="1" value="1" inputmode="numeric">
                                    <span class="muted" id="r-days-hint"></span>
                                </div>
                                <div class="field">
                                    <label for="r-start">Tanggal mulai sewa</label>
                                    <input type="date" id="r-start" readonly disabled>
                                </div>
                                <div class="field">
                                    <label for="r-end">Tanggal selesai sewa</label>
                                    <input type="date" id="r-end" readonly disabled>
                                </div>
                            </div>
                        </div>

                        <div class="rent-step">
                            <h4><span class="num">3</span>Tipe garasi &amp; garasi tersedia hari ini</h4>
                            <div class="field" style="max-width:280px">
                                <select id="r-type"></select>
                            </div>
                            <div class="garage-grid" id="r-garages" style="margin-top:10px"></div>
                        </div>

                        <div class="rent-step" id="r-battery-step" hidden>
                            <h4><span class="num">4</span>Sisa baterai saat ini</h4>
                            <div class="field" style="max-width:280px">
                                <label for="r-battery">Sisa baterai (%)</label>
                                <input type="number" id="r-battery" min="0" max="100" step="1" inputmode="numeric" placeholder="contoh: 20">
                                <span class="muted" id="r-battery-hint"></span>
                            </div>
                        </div>

                        <div class="rent-step">
                            <h4><span class="num" id="r-proof-num">4</span>Total biaya &amp; bukti pelunasan</h4>
                            <div class="quote-box">
                                <div class="muted">Total biaya sewa (dihitung sistem)</div>
                                <div class="quote-total" id="r-total">–</div>
                                <ul id="r-breakdown"></ul>
                            </div>
                            <div class="field" style="margin-top:12px;max-width:360px">
                                <label for="r-proof">Bukti pelunasan biaya (gambar)</label>
                                <input type="file" id="r-proof" accept="image/png,image/jpeg,image/webp">
                                <span class="muted">JPG / PNG / WEBP, maks. 5 MB.</span>
                            </div>
                        </div>

                        <div class="form-actions">
                            <button type="submit" class="btn btn-primary" id="r-submit">Simpan sewa</button>
                        </div>
                    </form>
                </section>
            </div>

            <!-- ===== Tab: Kendaraan ===== -->
            <section id="tab-kendaraan" class="panel" aria-label="Kendaraan saya" hidden>
                <div class="panel-head">
                    <h3>Kendaraan Saya</h3>
                    <button type="button" class="btn btn-primary" id="add-veh">+ Tambah kendaraan</button>
                </div>

                <table class="vtable">
                    <thead>
                        <tr>
                            <th>No / Plat</th>
                            <th>Tipe</th>
                            <th>Merk &amp; Model</th>
                            <th>Baterai</th>
                            <th>Aksi</th>
                        </tr>
                    </thead>
                    <tbody id="veh-body">
                        <tr class="empty-row"><td colspan="5">Memuat data…</td></tr>
                    </tbody>
                </table>

                <p class="muted" id="veh-empty-note" hidden>Belum ada kendaraan. Klik "Tambah kendaraan" untuk menambahkan yang pertama.</p>
            </section>

            <!-- ===== Panel form tambah / ubah kendaraan ===== -->
            <section id="veh-form-panel" class="panel" aria-label="Form kendaraan" hidden>
                <div class="panel-head">
                    <h3 id="form-title">Tambah kendaraan</h3>
                </div>

                <div id="form-msg" class="msgbox error" role="alert" hidden></div>

                <form id="veh-form" novalidate>
                    <div class="form-grid">
                        <div class="field" id="fwrap-brand">
                            <label for="f-brand">Merk</label>
                            <input type="text" id="f-brand" maxlength="50" placeholder="contoh: Yamaha, Honda">
                            <span class="field-err"></span>
                        </div>

                        <div class="field" id="fwrap-brandtype">
                            <label for="f-brandtype">Model / Varian</label>
                            <input type="text" id="f-brandtype" maxlength="50" placeholder="contoh: NMAX 155, City SUV">
                            <span class="field-err"></span>
                        </div>

                        <div class="field" id="fwrap-type">
                            <label for="f-type">Tipe kendaraan</label>
                            <select id="f-type">
                                <option value="sepeda_motor">Sepeda motor</option>
                                <option value="sepeda">Sepeda</option>
                                <option value="mobil">Mobil</option>
                                <option value="mobil_listrik">Mobil listrik</option>
                            </select>
                            <span class="field-err"></span>
                        </div>

                        <div class="field" id="fwrap-plate">
                            <label for="f-plate">Nomor plat</label>
                            <input type="text" id="f-plate" maxlength="15" placeholder="contoh: D 9234 KXW">
                            <span class="field-err"></span>
                        </div>

                        <div class="field hide" id="fwrap-kwh">
                            <label for="f-kwh">Kapasitas baterai (kWh)</label>
                            <input type="number" id="f-kwh" min="1" max="300" placeholder="contoh: 60">
                            <span class="field-err"></span>
                        </div>
                    </div>

                    <div class="form-actions">
                        <button type="submit" class="btn btn-primary" id="veh-submit">Simpan</button>
                        <a href="#" id="veh-cancel" class="muted hide">Batal</a>
                    </div>
                </form>
            </section>
        </main>

        <script src="{{ asset('js/api.js') }}"></script>
        <script>
            (function () {
                'use strict';

                const TOKEN_KEY = 'bnsps_token';
                const $ = (sel) => document.querySelector(sel);
                let editingId = null;
                let vehicles = [];

                const TYPE_TEXT = {
                    sepeda: 'Sepeda',
                    sepeda_motor: 'Sepeda motor',
                    mobil: 'Mobil',
                    mobil_listrik: 'Mobil listrik',
                };

                const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => (
                    { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]
                ));

                const rp = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID');

                function flash(target, text, kind) {
                    target.textContent = text;
                    target.className = 'msgbox ' + (kind || 'error');
                    target.hidden = false;
                }

                // Header Authorization + refresh token otomatis ditangani public/js/api.js.
                // (Versi lama menimpa header Authorization saat mengirim Content-Type.)
                function api(path, options) {
                    return window.Api.request(path, options);
                }

                /* ===== Tabulasi ===== */
                const SECTION_IDS = { garage: '#tab-garage', kendaraan: '#tab-kendaraan' };

                function showTab(name) {
                    Object.entries(SECTION_IDS).forEach(([key, sel]) => { $(sel).hidden = key !== name; });
                    document.querySelectorAll('.tab-btn').forEach((btn) => {
                        btn.classList.toggle('active', btn.dataset.tab === name);
                    });
                }

                function currentTab() {
                    return new URLSearchParams(location.search).get('tab') === 'kendaraan' ? 'kendaraan' : 'garage';
                }

                document.querySelectorAll('.tab-btn').forEach((btn) => {
                    btn.addEventListener('click', (event) => {
                        event.preventDefault();
                        showTab(btn.dataset.tab);
                        history.pushState({ tab: btn.dataset.tab }, '', '?tab=' + btn.dataset.tab);
                    });
                });

                window.addEventListener('popstate', () => { showTab(currentTab()); });

                /* ===== Tab Garasi: sewaan saya ===== */
                const fmtDate = (iso) => iso ? new Date(iso + 'T00:00:00').toLocaleDateString('id-ID', { day: '2-digit', month: 'short', year: 'numeric' }) : '–';

                function vehicleText(v) {
                    return esc(TYPE_TEXT[v.type] || v.type || '-') + '<div class="muted">' + esc([v.license_plate, v.brand].filter(Boolean).join(' · ')) + '</div>';
                }

                function rentalRow(r, isActive) {
                    // Rincian setiap perpanjangan oleh admin: tanggal selesai lama → baru dan biayanya.
                    const ext = r.extensions.map((e) => '<div class="muted">+ perpanjangan ' + fmtDate(e.current_end_date) + ' → ' +
                        fmtDate(e.new_end_date) + ': ' + rp(e.extra_rent) + '</div>').join('');
                    const charge = r.charge_price_total ? '<div class="muted">termasuk isi listrik ' + rp(r.charge_price_total) + '</div>' : '';
                    const proof = r.transaction_proof_url ? ' · <a class="link" href="' + esc(r.transaction_proof_url) + '" target="_blank" rel="noopener">bukti</a>' : '';
                    // Status verifikasi admin: pending → disetujui / ditolak.
                    let status;
                    if (r.status === 'pending') {
                        status = '<span class="chip warn">Menunggu verifikasi</span>';
                    } else if (r.status === 'declined') {
                        status = '<span class="chip fail">Ditolak</span>' + (r.status_note ? '<div class="muted">Alasan: ' + esc(r.status_note) + '</div>' : '');
                    } else {
                        status = isActive ? '<span class="chip ok">Disetujui · berjalan</span>' : '<span class="chip muted-chip">Selesai</span>';
                    }
                    if (r.extensions.length) {
                        status += ' <span class="chip muted-chip">' + r.extensions.length + '× diperpanjang</span>';
                    }

                    return [
                        '<tr>',
                        '<td><strong>' + esc(r.garage_id) + '</strong><div class="muted">' + esc(r.garage_label) + '</div></td>',
                        '<td>' + vehicleText(r.vehicle) + '</td>',
                        '<td>' + (r.is_monthly ? 'Bulanan' : 'Harian') + '</td>',
                        '<td>' + fmtDate(r.start_date) + ' – ' + fmtDate(r.end_date) + '</td>',
                        '<td><strong>' + rp(r.grand_total) + '</strong>' + charge + ext + '<div class="muted">biaya awal ' + rp(r.rent_price_total) + proof + '</div></td>',
                        '<td>' + status + '</td>',
                        '</tr>',
                    ].join('');
                }

                async function loadRentals() {
                    const body = $('#rent-body');

                    try {
                        const data = await api('/api/customer/rentals');
                        const active = data.active || [];
                        const history = data.history || [];

                        body.innerHTML = active.length
                            ? active.map((r) => rentalRow(r, true)).join('')
                            : '<tr class="empty-row"><td colspan="6">Belum ada sewa yang berjalan. Klik "Sewa baru" untuk mulai menyewa.</td></tr>';

                        $('#history-wrap').hidden = history.length === 0;
                        $('#history-count').textContent = history.length;
                        $('#history-body').innerHTML = history.map((r) => rentalRow(r, false)).join('');
                    } catch (e) {
                        body.innerHTML = '<tr class="empty-row"><td colspan="6">' + esc(e.message) + '</td></tr>';
                    }
                }

                /* ===== Tab Garasi: sewa baru ===== */
                const rent = {
                    isMonthly: false,
                    vehicle: null,
                    garage: null,
                    garages: [],
                    limits: null,
                    quoteSeq: 0,
                    quoteTimer: null,
                };

                function rentMsg(text, kind) {
                    flash($('#rent-msg'), text, kind);
                    if (kind !== 'ok') {
                        $('#rent-msg').scrollIntoView({ behavior: 'smooth', block: 'center' });
                    }
                }

                /** Tambah n hari ke tanggal ISO (YYYY-MM-DD) tanpa terpengaruh zona waktu. */
                function addDays(iso, n) {
                    const [y, m, d] = iso.split('-').map(Number);
                    return new Date(Date.UTC(y, m - 1, d + n)).toISOString().slice(0, 10);
                }

                function fillVehicleSelect() {
                    const select = $('#r-vehicle');
                    const keep = select.value;
                    $('#r-vehicle-note').hidden = vehicles.length !== 0;
                    select.disabled = vehicles.length === 0;
                    select.innerHTML = '<option value="">— Pilih kendaraan —</option>' + vehicles.map((v) => (
                        '<option value="' + v.id + '">' + esc((TYPE_TEXT[v.type] || v.type) + ' · ' + (v.license_plate || 'tanpa plat') + ' · ' + v.brand + ' ' + v.brand_type) + '</option>'
                    )).join('');
                    if (vehicles.some((v) => String(v.id) === keep)) {
                        select.value = keep;
                    }
                }

                function openRent() {
                    $('#rent-panel').hidden = false;
                    $('#rent-msg').hidden = true;
                    fillVehicleSelect();
                    $('#rent-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                function resetRent() {
                    rent.vehicle = null;
                    rent.garage = null;
                    rent.garages = [];
                    $('#r-vehicle').value = '';
                    $('#r-type').innerHTML = '';
                    $('#r-garages').innerHTML = '<p class="muted">Pilih kendaraan untuk melihat garasi yang tersedia.</p>';
                    $('#r-battery').value = '';
                    $('#r-proof').value = '';
                    syncBatteryStep();
                    resetQuote();
                }

                function closeRent() {
                    resetRent();
                    $('#rent-panel').hidden = true;
                }

                /** Hitung tanggal selesai di layar (server tetap menghitung ulang saat menyimpan). */
                function syncDates() {
                    const limits = rent.limits;
                    $('#r-days-wrap').hidden = rent.isMonthly;
                    if (!limits) {
                        return;
                    }

                    $('#r-start').value = limits.start_date;
                    const daysInput = $('#r-days');
                    daysInput.max = limits.max_daily_days;
                    $('#r-days-hint').textContent = 'Maksimal ' + limits.max_daily_days + ' hari untuk bulan ini.';

                    if (rent.isMonthly) {
                        $('#r-end').value = limits.monthly_end_date;
                    } else {
                        const days = parseInt(daysInput.value, 10);
                        $('#r-end').value = days >= 1 && days <= limits.max_daily_days ? addDays(limits.start_date, days - 1) : '';
                    }
                }

                function needsBattery() {
                    return Boolean(rent.vehicle && rent.garage && rent.vehicle.type === 'mobil_listrik' && rent.garage.type === 'E');
                }

                function syncBatteryStep() {
                    const show = needsBattery();
                    $('#r-battery-step').hidden = !show;
                    $('#r-proof-num').textContent = show ? '5' : '4';
                    if (show) {
                        $('#r-battery').max = 100;
                        $('#r-battery-hint').textContent = 'Isi 0–100 %. Kapasitas baterai ' + rent.vehicle.battery_capacity + ' kWh. Mobil wajib diisi hingga penuh; biaya isi listrik ditambahkan ke total sewa.';
                    }
                }

                async function loadAvailable() {
                    const grid = $('#r-garages');
                    rent.garage = null;
                    syncBatteryStep();
                    resetQuote();

                    if (!rent.vehicle) {
                        $('#r-type').innerHTML = '';
                        grid.innerHTML = '<p class="muted">Pilih kendaraan untuk melihat garasi yang tersedia.</p>';
                        return;
                    }

                    grid.innerHTML = '<p class="muted">Memuat garasi…</p>';
                    const params = new URLSearchParams({ vehicle_id: rent.vehicle.id });
                    const typeSelect = $('#r-type');
                    if (typeSelect.value) {
                        params.set('type', typeSelect.value);
                    }

                    let data;
                    try {
                        data = await api('/api/customer/garages/available?' + params.toString());
                    } catch (e) {
                        grid.innerHTML = '<p class="muted">' + esc(e.message) + '</p>';
                        return;
                    }

                    rent.limits = data.limits;
                    syncDates();

                    // Pilihan tipe garasi disesuaikan dengan tipe kendaraan.
                    const current = typeSelect.value || (data.allowed_types[0] && data.allowed_types[0].key) || '';
                    typeSelect.innerHTML = data.allowed_types.map((t) => '<option value="' + esc(t.key) + '">' + esc(t.label) + '</option>').join('');
                    typeSelect.value = current;

                    if (!data.vehicle_free) {
                        grid.innerHTML = '<p class="muted">Kendaraan ini sedang berada di garasi lain. Satu kendaraan hanya dapat menempati satu garasi.</p>';
                        rent.garages = [];
                        return;
                    }

                    rent.garages = (data.data || []).filter((g) => g.type === current);
                    if (!rent.garages.length) {
                        grid.innerHTML = '<p class="muted">Tidak ada garasi ' + esc(typeSelect.options[typeSelect.selectedIndex]?.text || '') + ' yang tersedia hari ini.</p>';
                        return;
                    }

                    grid.innerHTML = rent.garages.map((g) => [
                        '<button type="button" class="garage-card" data-id="' + esc(g.id) + '">',
                        '<strong>' + esc(g.id) + '</strong>',
                        '<small>' + rp(g.rent_price) + ' / hari</small>',
                        '<small>' + rp(g.monthly_price) + ' / bulan</small>',
                        g.charge_price_kwh !== null ? '<small>Listrik ' + rp(g.charge_price_kwh) + ' / kWh</small>' : '',
                        '</button>',
                    ].join('')).join('');
                }

                function resetQuote(text) {
                    $('#r-total').textContent = text || '–';
                    $('#r-breakdown').innerHTML = '';
                }

                /** Kumpulkan input sewa; mengembalikan null bila belum lengkap. */
                function rentPayload() {
                    if (!rent.vehicle || !rent.garage) {
                        return null;
                    }

                    const payload = {
                        vehicle_id: rent.vehicle.id,
                        garage_id: rent.garage.id,
                        is_monthly: rent.isMonthly ? 1 : 0,
                    };

                    if (!rent.isMonthly) {
                        const days = parseInt($('#r-days').value, 10);
                        if (!(days >= 1)) {
                            return null;
                        }
                        payload.days = days;
                    }

                    if (needsBattery()) {
                        const raw = $('#r-battery').value.trim();
                        if (raw === '') {
                            return null;
                        }
                        const pct = parseInt(raw, 10);
                        if (!(pct >= 0 && pct <= 100)) {
                            return null;
                        }
                        payload.battery_level = pct;
                    }

                    return payload;
                }

                function scheduleQuote() {
                    clearTimeout(rent.quoteTimer);
                    rent.quoteTimer = setTimeout(updateQuote, 200);
                }

                /** Total biaya dihitung oleh server dan ditampilkan sebelum disimpan. */
                async function updateQuote() {
                    syncDates();
                    const payload = rentPayload();
                    if (!payload) {
                        resetQuote(needsBattery() && rent.garage ? 'Isi sisa baterai untuk menghitung total.' : '–');
                        return;
                    }

                    const seq = ++rent.quoteSeq;
                    resetQuote('Menghitung…');

                    try {
                        const res = await api('/api/customer/rentals/quote', { method: 'POST', body: JSON.stringify(payload) });
                        if (seq !== rent.quoteSeq) {
                            return;
                        }
                        const q = res.data;
                        $('#r-end').value = q.end_date;
                        $('#r-total').textContent = rp(q.total);
                        const lines = [
                            q.is_monthly
                                ? 'Sewa bulanan (' + q.days + ' hari): 25 × ' + rp(q.daily_price) + ' = ' + rp(q.rent_price)
                                : 'Sewa harian: ' + q.days + ' hari × ' + rp(q.daily_price) + ' = ' + rp(q.rent_price),
                        ];
                        if (q.battery_level !== null) {
                            lines.push('Isi listrik hingga penuh: ' + rent.vehicle.battery_capacity + ' kWh × (100% − ' + q.battery_level + '%) = ' +
                                Number(q.charge_energy).toLocaleString('id-ID') + ' kWh × ' + rp(rent.garage.charge_price_kwh) + ' = ' + rp(q.charge_price));
                        }
                        lines.push('Periode: ' + fmtDate(q.start_date) + ' – ' + fmtDate(q.end_date));
                        $('#r-breakdown').innerHTML = lines.map((l) => '<li>' + esc(l) + '</li>').join('');
                    } catch (e) {
                        if (seq === rent.quoteSeq) {
                            resetQuote(e.message);
                        }
                    }
                }

                async function submitRent(event) {
                    event.preventDefault();
                    $('#rent-msg').hidden = true;

                    const payload = rentPayload();
                    const file = $('#r-proof').files[0];

                    if (!rent.vehicle) { rentMsg('Pilih kendaraan terlebih dahulu.'); return; }
                    if (!rent.garage) { rentMsg('Pilih garasi yang tersedia.'); return; }
                    if (!payload) { rentMsg(needsBattery() ? 'Isi sisa baterai (0–100 %).' : 'Isi jumlah hari sewa.'); return; }
                    if (!file) { rentMsg('Bukti pelunasan biaya wajib diunggah.'); return; }
                    if (file.size > 5 * 1024 * 1024) { rentMsg('Ukuran bukti pelunasan maksimal 5 MB.'); return; }

                    const form = new FormData();
                    Object.entries(payload).forEach(([key, value]) => form.append(key, String(value)));
                    form.append('transaction_proof', file);

                    const btn = $('#r-submit');
                    btn.disabled = true;
                    btn.textContent = 'Menyimpan…';

                    try {
                        const res = await api('/api/customer/rentals', { method: 'POST', body: form });
                        closeRent();
                        flash($('#global-msg'), res.message, 'ok');
                        window.scrollTo({ top: 0, behavior: 'smooth' });
                        await loadRentals();
                    } catch (e) {
                        rentMsg(e.message);
                        loadAvailable(); // garasi mungkin baru saja diambil penyewa lain
                    } finally {
                        btn.disabled = false;
                        btn.textContent = 'Simpan sewa';
                    }
                }

                function wireRent() {
                    $('#new-rent-btn').addEventListener('click', openRent);
                    $('#rent-cancel').addEventListener('click', (event) => { event.preventDefault(); closeRent(); });
                    $('#go-vehicles').addEventListener('click', (event) => {
                        event.preventDefault();
                        showTab('kendaraan');
                        history.pushState({ tab: 'kendaraan' }, '', '?tab=kendaraan');
                    });

                    $('#r-vehicle').addEventListener('change', (event) => {
                        rent.vehicle = vehicles.find((v) => String(v.id) === event.target.value) || null;
                        $('#r-type').innerHTML = '';
                        $('#r-battery').value = '';
                        loadAvailable();
                    });

                    $('#r-type').addEventListener('change', loadAvailable);

                    document.querySelectorAll('#rent-form .seg button').forEach((btn) => {
                        btn.addEventListener('click', () => {
                            document.querySelectorAll('#rent-form .seg button').forEach((b) => b.classList.toggle('active', b === btn));
                            rent.isMonthly = btn.dataset.monthly === '1';
                            scheduleQuote();
                        });
                    });

                    $('#r-days').addEventListener('input', scheduleQuote);
                    $('#r-battery').addEventListener('input', scheduleQuote);

                    $('#r-garages').addEventListener('click', (event) => {
                        const card = event.target.closest('.garage-card');
                        if (!card) {
                            return;
                        }
                        rent.garage = rent.garages.find((g) => g.id === card.dataset.id) || null;
                        document.querySelectorAll('.garage-card').forEach((c) => c.classList.toggle('selected', c === card));
                        syncBatteryStep();
                        scheduleQuote();
                    });

                    $('#rent-form').addEventListener('submit', submitRent);
                    resetRent();
                }

                /* ===== List kendaraan ===== */
                async function loadVehicles() {
                    const body = $('#veh-body');

                    try {
                        const data = await api('/api/customer/vehicles');
                        vehicles = data.data || [];
                        $('#veh-empty-note').hidden = vehicles.length !== 0;
                        renderVehicles();
                        fillVehicleSelect();
                    } catch (e) {
                        body.innerHTML = '';
                        body.insertAdjacentHTML('beforeend', '<tr class="empty-row"><td colspan="5">' + esc(e.message) + '</td></tr>');
                    }
                }

                function renderVehicles() {
                    const body = $('#veh-body');
                    body.innerHTML = '';

                    vehicles.forEach((veh) => {
                        const battery = veh.battery_capacity === null ? '—' : Number(veh.battery_capacity).toLocaleString('id-ID') + ' kWh';

                        body.insertAdjacentHTML('beforeend', [
                            '<tr>',
                            '<td>' + (veh.license_plate ? esc(veh.license_plate) : '—') + '</td>',
                            '<td><span class="vehtype">' + esc(TYPE_TEXT[veh.type] || veh.type) + '</span></td>',
                            '<td>' + esc(veh.brand) + ' · ' + esc(veh.brand_type) + '</td>',
                            '<td>' + battery + '</td>',
                            '<td><button type="button" class="btn btn-sm act-edit" data-id="' + veh.id + '">Ubah</button></td>',
                            '</tr>',
                        ].join(''));
                    });
                }

                /* ===== Form tambah / ubah ===== */
                const plateInput = $('#f-plate');
                const kwhInput = $('#f-kwh');

                function setField(wrapId, message) {
                    $(wrapId).classList.toggle('invalid', Boolean(message));
                    $(wrapId + ' .field-err').textContent = message || '';
                }

                function clearErrors() {
                    ['#fwrap-brand', '#fwrap-brandtype', '#fwrap-type', '#fwrap-plate', '#fwrap-kwh'].forEach((id) => setField(id, null));
                }

                const hasError = (sel) => $(sel + ' .field-err').textContent.trim() !== '';

                function syncTypeFields() {
                    const type = $('#f-type').value;
                    $('#fwrap-kwh').classList.toggle('hide', type !== 'mobil_listrik');
                    plateInput.disabled = type === 'sepeda';
                    plateInput.required = type !== 'sepeda';

                    if (type !== 'mobil_listrik') {
                        kwhInput.value = '';
                        kwhInput.disabled = true;
                    } else {
                        kwhInput.disabled = false;
                    }
                }

                function openForm(vehicle) {
                    editingId = vehicle ? vehicle.id : null;
                    $('#form-title').textContent = vehicle ? 'Ubah kendaraan' : 'Tambah kendaraan';
                    $('#veh-submit').textContent = vehicle ? 'Simpan perubahan' : 'Simpan';
                    $('#veh-cancel').classList.toggle('hide', !vehicle);

                    $('#f-brand').value = vehicle ? vehicle.brand : '';
                    $('#f-brandtype').value = vehicle ? vehicle.brand_type : '';
                    $('#f-type').value = vehicle ? vehicle.type : 'sepeda_motor';
                    plateInput.value = vehicle && vehicle.license_plate ? vehicle.license_plate : '';
                    kwhInput.value = vehicle && vehicle.battery_capacity !== null ? vehicle.battery_capacity : '';

                    clearErrors();
                    syncTypeFields();
                    $('#veh-form-panel').hidden = false;
                    $('#form-msg').hidden = true;
                    $('#veh-form-panel').scrollIntoView({ behavior: 'smooth', block: 'start' });
                }

                function closeForm() {
                    editingId = null;
                    $('#f-brand').value = '';
                    $('#f-brandtype').value = '';
                    syncTypeFields();
                    $('#veh-form-panel').hidden = true;
                }

                document.querySelector('#tab-kendaraan').addEventListener('click', (event) => {
                    const editBtn = event.target.closest('.act-edit');

                    if (editBtn) {
                        const vehicle = vehicles.find((v) => String(v.id) === editBtn.dataset.id);

                        if (vehicle) {
                            openForm(vehicle);
                        }
                    }
                });

                $('#f-type').addEventListener('change', syncTypeFields);
                $('#add-veh').addEventListener('click', () => openForm(null));
                $('#veh-cancel').addEventListener('click', (event) => { event.preventDefault(); closeForm(); });

                async function submitVehicle(event) {
                    event.preventDefault();
                    clearErrors();

                    const type = $('#f-type').value;
                    const brand = $('#f-brand').value.trim();
                    const brandType = $('#f-brandtype').value.trim();
                    const plate = plateInput.value.trim();

                    if (!brand) { setField('#fwrap-brand', 'Merk wajib diisi.'); }
                    if (!brandType) { setField('#fwrap-brandtype', 'Model / varian wajib diisi.'); }
                    if (type !== 'sepeda' && !plate) { setField('#fwrap-plate', 'Nomor plat wajib diisi.'); }

                    if (hasError('#fwrap-brand') || hasError('#fwrap-brandtype') || (type !== 'sepeda' && hasError('#fwrap-plate'))) {
                        return;
                    }

                    const payload = { type, brand, brand_type: brandType };

                    if (type !== 'sepeda') {
                        payload.license_plate = plate;
                    }

                    if (type === 'mobil_listrik') {
                        const kwh = parseInt(kwhInput.value, 10);

                        if (!Number.isFinite(kwh) || kwh < 1 || kwh > 300) {
                            setField('#fwrap-kwh', 'Kapasitas baterai harus antara 1–300 kWh.');
                            return;
                        }

                        payload.battery_capacity = kwh;
                    }

                    const submitBtn = $('#veh-submit');
                    submitBtn.disabled = true;
                    const wasEditing = editingId !== null;

                    try {
                        if (wasEditing) {
                            await api('/api/customer/vehicles/' + editingId, {
                                method: 'PATCH',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(payload),
                            });
                            flash($('#form-msg'), 'Kendaraan berhasil diubah.', 'ok');
                        } else {
                            await api('/api/customer/vehicles', {
                                method: 'POST',
                                headers: { 'Content-Type': 'application/json' },
                                body: JSON.stringify(payload),
                            });
                            flash($('#form-msg'), 'Kendaraan berhasil ditambahkan.', 'ok');
                        }

                        await loadVehicles();
                        closeForm();
                    } catch (e) {
                        const msg = e.message || 'Terjadi kesalahan.';

                        if (msg.includes('plat')) { setField('#fwrap-plate', msg); }
                        if (msg.toLowerCase().includes('baterai')) { setField('#fwrap-kwh', msg); }
                        flash($('#form-msg'), msg, 'error');
                    } finally {
                        submitBtn.disabled = false;
                    }
                }

                $('#veh-form').addEventListener('submit', submitVehicle);

                /* ===== Keluar ===== */
                $('#logout-btn').addEventListener('click', (event) => {
                    event.preventDefault();
                    window.Api.logout();
                });

                /* ===== Boot ===== */
                async function boot() {
                    if (!localStorage.getItem(TOKEN_KEY)) {
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

                    if (me.is_admin) {
                        window.location.assign('/admin');
                        return;
                    }

                    const name = me.customer && me.customer.name ? me.customer.name : 'Pelanggan';
                    $('#hello-name').textContent = name;
                    $('#who-name').textContent = me.email || '';

                    showTab(currentTab());
                    wireRent();
                    loadRentals();
                    loadVehicles();
                }

                boot();
            })();
        </script>
    </body>
</html>
