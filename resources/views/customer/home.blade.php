<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Portal Pelanggan · BNSP</title>
        <link rel="stylesheet" href="{{ asset('css/customer.css') }}">
    </head>
    <body>
        <header class="topbar">
            <div class="topbar-left">
                <span class="brand-mark" aria-hidden="true">B</span>
                <div class="brand-text">
                    <strong>BNSP</strong>
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
            <h2 class="page-title">Hello, <span id="hello-name">…</span></h2>
            <p class="page-sub">Pantau garasi dan kelola kendaraan Anda di satu tempat.</p>

            <div id="global-msg" class="msgbox error" role="alert" hidden></div>

            <!-- ===== Tab: Garasi (default, stub) ===== -->
            <section id="tab-garage" class="panel" aria-label="Sewa garasi">
                <div class="panel-head">
                    <h3>Sewa Garasi</h3>
                </div>

                <div class="stub-box">
                    <h4>Pemesanan segera hadir</h4>
                    <p>Dari tab ini Anda akan dapat memilih tipe garasi (sepeda, mobil, atau mobil listrik), melihat tarif harian, lalu memulai masa sewa. Sementara berikut daftar tipenya.</p>
                </div>

                <table class="type-table">
                    <thead>
                        <tr>
                            <th>Tipe</th>
                            <th>Sewa harian</th>
                            <th>Tarif listrik</th>
                        </tr>
                    </thead>
                    <tbody id="type-body">
                        <tr class="empty-row"><td colspan="3">Memuat data…</td></tr>
                    </tbody>
                </table>

                <button type="button" class="btn btn-disabled" disabled>Mulai sewa</button>
                <span class="stub-note">Fitur pemesanan akan segera tersedia.</span>
            </section>

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

                /* ===== Data garasi (stub) ===== */
                async function loadGarages() {
                    const body = $('#type-body');

                    try {
                        const data = await api('/api/customer/garage-preview');

                        body.innerHTML = '';

                        if (!data.data.length) {
                            body.insertAdjacentHTML('beforeend', '<tr class="empty-row"><td colspan="3">Belum ada garasi kosong.</td></tr>');
                            return;
                        }

                        data.data.forEach((type) => {
                            const charge = type.charge_price_kwh === null ? '—' : rp(type.charge_price_kwh) + ' /kWh';

                            body.insertAdjacentHTML('beforeend', [
                                '<tr>',
                                '<td><strong>' + esc(type.id) + '</strong> · ' + esc(type.label) + '</td>',
                                '<td>' + rp(type.rent_price) + '</td>',
                                '<td>' + charge + '</td>',
                                '</tr>',
                            ].join(''));
                        });
                    } catch (e) {
                        body.innerHTML = '';
                        body.insertAdjacentHTML('beforeend', '<tr class="empty-row"><td colspan="3">' + esc(e.message) + '</td></tr>');
                    }
                }

                /* ===== List kendaraan ===== */
                async function loadVehicles() {
                    const body = $('#veh-body');

                    try {
                        const data = await api('/api/customer/vehicles');
                        vehicles = data.data || [];
                        $('#veh-empty-note').hidden = vehicles.length !== 0;
                        renderVehicles();
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
                    loadGarages();
                    loadVehicles();
                }

                boot();
            })();
        </script>
    </body>
</html>
