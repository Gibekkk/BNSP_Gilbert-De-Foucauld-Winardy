<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Masuk · BNSP</title>
        <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    </head>
    <body>
        <main class="page">
            <!-- ===== View: login form ===== -->
            <section id="view-form" class="card" aria-label="Formulir masuk">
                <header class="card-header">
                    <span class="brand-mark" aria-hidden="true">B</span>
                    <div>
                        <h1>Masuk</h1>
                        <p>Layanan sewa garasi BNSP. Gunakan email dan password Anda.</p>
                    </div>
                </header>

                <div id="form-error" class="msg error" role="alert" hidden></div>

                <form id="login-form">
                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" autocomplete="email" required placeholder="nama@contoh.com">
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" autocomplete="current-password" required placeholder="••••••••">
                    </div>

                    <button type="submit" class="btn btn-primary" id="login-submit">Masuk</button>
                </form>

                <p class="hint">Belum punya akun? <a href="{{ route('register') }}">Daftar di sini</a></p>
            </section>

            <!-- ===== View: logged in ===== -->
            <section id="view-success" class="card" aria-label="Sudah masuk" hidden>
                <header class="card-header">
                    <span class="brand-mark" id="avatar-letter" aria-hidden="true">–</span>
                    <div>
                        <h1>Selamat datang</h1>
                        <p class="muted" id="welcome-email">–</p>
                    </div>
                </header>

                <span class="badge" id="role-badge">Penyewa</span>
                <p class="hint" id="success-hint">Menyiapkan halaman Anda…</p>

                <button type="button" class="btn btn-primary" id="go-portal">Buka portal saya</button>
                <button type="button" class="btn btn-ghost-danger" id="logout-btn">Keluar</button>
            </section>
        </main>

        <script src="{{ asset('js/api.js') }}"></script>
        <script>
            (function () {
                'use strict';

                const TOKEN_KEY = 'bnsps_token';
                const URL_ADMIN = '/admin';
                const URL_TENANT = '/customer';
                const $ = (sel) => document.querySelector(sel);

                const formView = $('#view-form');
                const successView = $('#view-success');
                const form = $('#login-form');
                const errorBox = $('#form-error');
                const submitBtn = $('#login-submit');
                let portalUrl = URL_TENANT;

                const showError = (text) => {
                    errorBox.textContent = text;
                    errorBox.hidden = false;
                };

                const clearError = () => {
                    errorBox.textContent = '';
                    errorBox.hidden = true;
                };

                const setBusy = (busy) => {
                    submitBtn.disabled = busy;
                    submitBtn.textContent = busy ? 'Memproses…' : 'Masuk';
                };

                function renderUser(user) {
                    $('#welcome-email').textContent = user.email || '–';
                    $('#avatar-letter').textContent = ((user.email?.charAt(0)) || '?').toUpperCase();
                    const badge = $('#role-badge');
                    const isAdmin = Boolean(user.is_admin);
                    badge.textContent = isAdmin ? 'ADMIN' : 'PELANGGAN';
                    badge.classList.toggle('badge-admin', isAdmin);
                    portalUrl = isAdmin ? URL_ADMIN : URL_TENANT;
                }

                function showSuccess() {
                    formView.hidden = true;
                    successView.hidden = false;
                    window.scrollTo(0, 0);
                }

                function goByRole(user) {
                    portalUrl = Boolean(user && user.is_admin) ? URL_ADMIN : URL_TENANT;
                    setTimeout(() => window.location.assign(portalUrl), 500);
                }

                /* ===== Keluar ===== */
                async function logout() {
                    const token = localStorage.getItem(TOKEN_KEY);
                    localStorage.removeItem(TOKEN_KEY);

                    if (token) {
                        try {
                            await fetch('/api/logout', {
                                method: 'POST',
                                headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
                            });
                        } catch (e) { /* token sudah tidak valid */ }
                    }

                    successView.hidden = true;
                    formView.hidden = false;
                    $('#password').value = '';
                }

                /* ===== Kira-kira status sesi yang tersimpan ===== */
                async function restoreSession() {
                    const token = localStorage.getItem(TOKEN_KEY);

                    if (!token) {
                        return;
                    }

                    try {
                        // Api.request mencoba refresh token yang kedaluwarsa sebelum menyerah.
                        const data = await window.Api.request('/api/me');
                        renderUser(data.user);
                        showSuccess();
                        goByRole(data.user);
                    } catch (e) {
                        localStorage.removeItem(TOKEN_KEY);
                    }
                }

                /* ===== Submit ===== */
                form.addEventListener('submit', async (event) => {
                    event.preventDefault();
                    clearError();
                    setBusy(true);

                    try {
                        const res = await fetch('/api/login', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({
                                email: $('#email').value.trim(),
                                password: $('#password').value,
                            }),
                        });

                        const data = await res.json().catch(() => ({}));

                        if (!res.ok) {
                            throw new Error(data.message || 'Terjadi kesalahan saat masuk.');
                        }

                        localStorage.setItem(TOKEN_KEY, data.token);
                        renderUser(data.user);
                        showSuccess();
                        goByRole(data.user);
                    } catch (e) {
                        showError(e.message);
                    } finally {
                        setBusy(false);
                    }
                });

                $('#logout-btn').addEventListener('click', () => logout());
                $('#go-portal').addEventListener('click', () => window.location.assign(portalUrl));

                restoreSession();
            })();
        </script>
    </body>
</html>
