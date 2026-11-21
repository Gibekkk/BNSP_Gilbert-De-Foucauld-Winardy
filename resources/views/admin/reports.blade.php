<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Sewa · BNSP</title>
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
    <a href="{{ route('admin.rental-approvals') }}" class="tab-btn">Verifikasi sewa</a>
    <a href="{{ route('admin.rentals') }}" class="tab-btn">Perpanjang sewa</a>
    <a href="{{ route('admin.reports') }}" class="tab-btn active" aria-current="page">Laporan</a>
</nav>

<main class="layout">
    <h2 class="page-title">Laporan sewa garasi</h2>
    <p class="page-sub">Pendapatan setiap sewa (biaya sewa + isi listrik + seluruh perpanjangannya) dihitung pada tanggal mulai sewa.</p>

    <div id="global-msg" class="msgbox error" role="alert" hidden></div>

    <div class="seg" role="tablist" aria-label="Jenis laporan">
        <button type="button" role="tab" data-group="monthly" class="active">Per bulan</button>
        <button type="button" role="tab" data-group="daily">Per hari</button>
        <button type="button" role="tab" data-group="type">Per tipe garasi</button>
        <button type="button" role="tab" data-group="tenant">Per penyewa</button>
    </div>

    <section class="panel" aria-label="Hasil laporan">
        <div class="filter-bar">
            <label class="muted">Tahun
                <select id="f-year"></select>
            </label>
            <label class="muted" id="f-month-wrap">Bulan
                <select id="f-month">
                    <option value="">Semua bulan</option>
                    <option value="1">Januari</option>
                    <option value="2">Februari</option>
                    <option value="3">Maret</option>
                    <option value="4">April</option>
                    <option value="5">Mei</option>
                    <option value="6">Juni</option>
                    <option value="7">Juli</option>
                    <option value="8">Agustus</option>
                    <option value="9">September</option>
                    <option value="10">Oktober</option>
                    <option value="11">November</option>
                    <option value="12">Desember</option>
                </select>
            </label>
            <strong id="period-label" style="margin-left:auto"></strong>
        </div>

        <div class="chart-wrap"><canvas id="report-chart" aria-label="Grafik total pendapatan"></canvas></div>

        <div class="table-scroll">
            <table class="data-table">
                <thead>
                    <tr>
                        <th id="col-label">Bulan</th>
                        <th class="t-right">Jumlah sewa</th>
                        <th class="t-right">Biaya sewa</th>
                        <th class="t-right">Isi listrik</th>
                        <th class="t-right">Perpanjangan</th>
                        <th class="t-right">Total</th>
                    </tr>
                </thead>
                <tbody id="report-body">
                    <tr class="empty-row"><td colspan="6">Memuat…</td></tr>
                </tbody>
                <tfoot id="report-foot"></tfoot>
            </table>
        </div>
    </section>
</main>

<script src="{{ asset('vendor/chart.umd.min.js') }}"></script>
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
    const MONTHS_SHORT = ['Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun', 'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des'];

    const COLUMN_LABEL = { monthly: 'Bulan', daily: 'Tanggal', type: 'Tipe garasi', tenant: 'Penyewa' };

    const now = new Date();
    const state = {
        group: 'monthly',
        year: now.getFullYear(),
        month: '', // '' = semua bulan (hanya untuk per tipe / per penyewa)
    };
    let chart = null;
    let loadSeq = 0;

    function showMsg(text) {
        const box = $('#global-msg');
        box.textContent = text;
        box.className = 'msgbox error';
        box.hidden = false;
    }

    /** Isi pilihan tahun; per tipe/penyewa punya opsi "Semua tahun". */
    function fillYears(years) {
        const select = $('#f-year');
        const allowAll = state.group === 'type' || state.group === 'tenant';
        const options = (allowAll ? ['<option value="">Semua tahun</option>'] : [])
            .concat(years.map((y) => '<option value="' + y + '">' + y + '</option>'));
        select.innerHTML = options.join('');
        select.value = state.year === '' && allowAll ? '' : String(state.year || years[0]);
        state.year = select.value === '' ? '' : Number(select.value);
    }

    /** Atur filter bulan sesuai jenis laporan. */
    function syncFilters() {
        const monthWrap = $('#f-month-wrap');
        const monthSelect = $('#f-month');
        const allOption = monthSelect.querySelector('option[value=""]');

        if (state.group === 'monthly') {
            monthWrap.hidden = true;
        } else if (state.group === 'daily') {
            monthWrap.hidden = false;
            allOption.hidden = true;
            allOption.disabled = true;
            if (state.month === '') {
                state.month = now.getMonth() + 1;
            }
        } else {
            monthWrap.hidden = state.year === '';
            allOption.hidden = false;
            allOption.disabled = false;
            if (state.year === '') {
                state.month = '';
            }
        }

        monthSelect.value = String(state.month);
        if (state.group !== 'monthly' && state.group !== 'daily' && monthSelect.value !== String(state.month)) {
            monthSelect.value = '';
        }
    }

    async function load() {
        syncFilters();

        const params = new URLSearchParams({ group: state.group });
        if (state.year !== '') {
            params.set('year', state.year);
        }
        if (state.group !== 'monthly' && state.month !== '' && state.year !== '') {
            params.set('month', state.month);
        }

        const seq = ++loadSeq;
        let res;

        try {
            res = await api('/api/admin/reports?' + params.toString());
        } catch (e) {
            if (seq === loadSeq) {
                $('#report-body').innerHTML = '<tr class="empty-row"><td colspan="6">' + esc(e.message) + '</td></tr>';
                $('#report-foot').innerHTML = '';
                showMsg(e.message);
            }
            return;
        }

        if (seq !== loadSeq) {
            return; // ada permintaan yang lebih baru
        }

        $('#global-msg').hidden = true;
        fillYears(res.years || [now.getFullYear()]);
        syncFilters();
        render(res.data);
    }

    function render(report) {
        const rows = report.rows || [];
        $('#period-label').textContent = report.period || '';
        $('#col-label').textContent = COLUMN_LABEL[state.group];

        if (!rows.length) {
            $('#report-body').innerHTML = '<tr class="empty-row"><td colspan="6">Belum ada data sewa pada periode ini.</td></tr>';
        } else {
            $('#report-body').innerHTML = rows.map((r) => [
                '<tr>',
                '<td>' + esc(r.label) + '</td>',
                '<td class="t-right">' + Number(r.rentals) + '</td>',
                '<td class="t-right">' + rp(r.rent_income) + '</td>',
                '<td class="t-right">' + rp(r.charge_income) + '</td>',
                '<td class="t-right">' + rp(r.extension_income) + '</td>',
                '<td class="t-right"><strong>' + rp(r.total) + '</strong></td>',
                '</tr>',
            ].join('')).join('');
        }

        const t = report.totals || {};
        $('#report-foot').innerHTML = '<tr><td>Total</td><td class="t-right">' + Number(t.rentals || 0) + '</td><td class="t-right">' + rp(t.rent_income) +
            '</td><td class="t-right">' + rp(t.charge_income) + '</td><td class="t-right">' + rp(t.extension_income) + '</td><td class="t-right">' + rp(t.total) + '</td></tr>';

        drawChart(rows);
    }

    /** Label ringkas untuk sumbu grafik. */
    function chartLabel(row) {
        if (state.group === 'monthly') {
            return MONTHS_SHORT[Number(row.key) - 1];
        }
        if (state.group === 'daily') {
            return String(Number(row.key.slice(8, 10)));
        }
        if (state.group === 'tenant') {
            return row.label.split(' · ')[0];
        }
        return row.label;
    }

    function drawChart(rows) {
        if (!window.Chart) {
            return; // grafik opsional; tabel tetap tampil
        }

        const config = {
            type: 'bar',
            data: {
                labels: rows.map(chartLabel),
                datasets: [
                    { label: 'Biaya sewa', data: rows.map((r) => r.rent_income), backgroundColor: '#2452c7', stack: 'pendapatan' },
                    { label: 'Isi listrik', data: rows.map((r) => r.charge_income), backgroundColor: '#1aa36f', stack: 'pendapatan' },
                    { label: 'Perpanjangan', data: rows.map((r) => r.extension_income), backgroundColor: '#e0a21b', stack: 'pendapatan' },
                ],
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                scales: {
                    x: { stacked: true, grid: { display: false } },
                    y: { stacked: true, ticks: { callback: (v) => rp(v) } },
                },
                plugins: {
                    tooltip: { callbacks: { label: (ctx) => ctx.dataset.label + ': ' + rp(ctx.parsed.y) } },
                    legend: { position: 'bottom' },
                },
            },
        };

        if (chart) {
            chart.destroy();
        }
        chart = new window.Chart($('#report-chart'), config);
    }

    function wire() {
        document.querySelectorAll('.seg button').forEach((btn) => {
            btn.addEventListener('click', () => {
                document.querySelectorAll('.seg button').forEach((b) => b.classList.toggle('active', b === btn));
                state.group = btn.dataset.group;
                if ((state.group === 'monthly' || state.group === 'daily') && state.year === '') {
                    state.year = now.getFullYear();
                }
                load();
            });
        });

        $('#f-year').addEventListener('change', (ev) => {
            state.year = ev.target.value === '' ? '' : Number(ev.target.value);
            load();
        });

        $('#f-month').addEventListener('change', (ev) => {
            state.month = ev.target.value === '' ? '' : Number(ev.target.value);
            load();
        });

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
        fillYears([now.getFullYear()]);
        wire();
        load();
    }

    boot();
})();
</script>
</body>
</html>
