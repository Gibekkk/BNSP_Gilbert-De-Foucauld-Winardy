/**
 * BNSP · Helper API bersama untuk semua halaman portal.
 *
 * Tanggung jawab:
 *  - Selalu mengirim header Authorization + Accept, dan MENGGABUNGKAN header
 *    tambahan dari pemanggil (bukan menimpanya). Sebelumnya Object.assign()
 *    menimpa seluruh objek headers sehingga request POST/PUT/PATCH yang
 *    menambahkan Content-Type kehilangan token → "Unauthenticated".
 *  - Saat server membalas 401 (token kedaluwarsa), mencoba /api/refresh satu
 *    kali lalu mengulang request. Jika gagal, sesi dihapus dan pengguna
 *    diarahkan ke halaman login.
 *  - Mendukung body FormData (upload bukti bayar) tanpa memaksa Content-Type.
 */
(function (window) {
    'use strict';

    const TOKEN_KEY = 'bnsps_token';
    let refreshing = null;

    /** Ambil token tersimpan (null bila tidak ada / storage diblokir). */
    function token() {
        try {
            return window.localStorage.getItem(TOKEN_KEY);
        } catch (e) {
            return null;
        }
    }

    /** Simpan token baru. */
    function setToken(value) {
        try {
            window.localStorage.setItem(TOKEN_KEY, value);
        } catch (e) { /* storage diblokir */ }
    }

    /** Hapus token tersimpan. */
    function clearToken() {
        try {
            window.localStorage.removeItem(TOKEN_KEY);
        } catch (e) { /* storage diblokir */ }
    }

    /** Hapus sesi lalu arahkan ke halaman login (kecuali sudah di sana). */
    function goLogin() {
        clearToken();
        if (window.location.pathname !== '/login') {
            window.location.replace('/login');
        }
    }

    /**
     * Tukar token lama (yang masih dalam masa refresh) dengan token baru.
     * Beberapa request paralel yang sama-sama 401 berbagi satu proses refresh.
     */
    function refresh() {
        if (refreshing) {
            return refreshing;
        }

        refreshing = (async function () {
            const current = token();
            if (!current) {
                return false;
            }

            try {
                const res = await fetch('/api/refresh', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + current },
                });
                if (!res.ok) {
                    return false;
                }
                const data = await res.json().catch(function () { return {}; });
                if (!data.token) {
                    return false;
                }
                setToken(data.token);
                return true;
            } catch (e) {
                return false;
            }
        })().finally(function () {
            refreshing = null;
        });

        return refreshing;
    }

    /** Gabungkan header bawaan dengan header dari pemanggil. */
    function buildHeaders(options) {
        const headers = new Headers(options.headers || {});
        headers.set('Accept', 'application/json');

        const current = token();
        if (current) {
            headers.set('Authorization', 'Bearer ' + current);
        }

        if (typeof options.body === 'string' && !headers.has('Content-Type')) {
            headers.set('Content-Type', 'application/json');
        }

        return headers;
    }

    /** Ubah respons error Laravel menjadi satu kalimat yang bisa ditampilkan. */
    function errorMessage(data, status) {
        if (data && data.errors && typeof data.errors === 'object') {
            return Object.values(data.errors).flat().join(' ');
        }
        return (data && data.message) || ('Terjadi kesalahan (HTTP ' + status + ').');
    }

    /**
     * Kirim request ke API dan kembalikan JSON-nya.
     * Melempar Error (dengan .status dan .data) untuk respons non-2xx.
     */
    async function request(path, options, isRetry) {
        options = options || {};

        const res = await fetch(path, Object.assign({}, options, { headers: buildHeaders(options) }));

        if (res.status === 401) {
            if (!isRetry && await refresh()) {
                return request(path, options, true);
            }
            goLogin();
            const expired = new Error('Sesi berakhir, silakan masuk kembali.');
            expired.status = 401;
            throw expired;
        }

        const data = await res.json().catch(function () { return {}; });

        if (!res.ok) {
            const err = new Error(errorMessage(data, res.status));
            err.status = res.status;
            err.data = data;
            throw err;
        }

        return data;
    }

    /** Keluar: invalidasi token di server (best effort) lalu ke halaman login. */
    async function logout() {
        const current = token();
        clearToken();

        if (current) {
            try {
                await fetch('/api/logout', {
                    method: 'POST',
                    headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + current },
                });
            } catch (e) { /* token sudah tidak valid */ }
        }

        window.location.replace('/login');
    }

    window.Api = {
        TOKEN_KEY: TOKEN_KEY,
        token: token,
        setToken: setToken,
        clearToken: clearToken,
        goLogin: goLogin,
        request: request,
        logout: logout,
    };
})(window);
