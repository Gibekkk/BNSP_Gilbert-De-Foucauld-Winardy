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
            <!-- ===== Formulir masuk ===== -->
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
        </main>

        <script src="{{ asset('js/api.js') }}"></script>
        <script>
            (function () {
                'use strict';

                const URL_ADMIN = '/admin';
                const URL_TENANT = '/customer';
                const $ = (sel) => document.querySelector(sel);

                const form = $('#login-form');
                const errorBox = $('#form-error');
                const submitBtn = $('#login-submit');

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

                /** Langsung ke dasbor sesuai peran (tanpa layar perantara). */
                function goByRole(user) {
                    window.location.replace(user && user.is_admin ? URL_ADMIN : URL_TENANT);
                }

                /* ===== Sesi tersimpan: langsung arahkan ke dasbor ===== */
                async function restoreSession() {
                    if (!window.Api.token()) {
                        return;
                    }

                    try {
                        // Api.request mencoba refresh token yang kedaluwarsa sebelum menyerah.
                        const data = await window.Api.request('/api/me');
                        goByRole(data.user);
                    } catch (e) {
                        window.Api.clearToken();
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

                        window.Api.setToken(data.token);
                        goByRole(data.user);
                    } catch (e) {
                        showError(e.message);
                        setBusy(false);
                    }
                });

                restoreSession();
            })();
        </script>
    </body>
</html>
