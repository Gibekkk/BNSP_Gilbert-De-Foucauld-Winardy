<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Masuk · PNSP</title>
        <link rel="stylesheet" href="{{ asset('css/login.css') }}">
    </head>
    <body>
        <main class="page">
            <!-- ===== View: login form ===== -->
            <section id="view-form" class="card" aria-label="Formulir masuk">
                <header class="card-header">
                    <span class="brand-mark" aria-hidden="true">P</span>
                    <div>
                        <h1>Masuk</h1>
                        <p>Layanan sewa garasi PNSP. Gunakan email dan password Anda.</p>
                    </div>
                </header>

                <div id="form-error" class="msg error" role="alert" hidden></div>

                <form id="login-form">
                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" autocomplete="email" required
                               placeholder="nama@contoh.com">
                    </div>
                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" autocomplete="current-password" required
                               placeholder="••••••••">
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
                <p class="token-note" id="token-note"></p>

                <button type="button" class="btn btn-ghost-danger" id="logout-btn">Keluar</button>

                <p class="hint"><a href="{{ route('register') }}">Daftar pelanggan baru</a></p>
            </section>
        </main>

        <script>
            (function () {
                'use strict';

                const TOKEN_KEY = 'pnsps_token';
                const $ = (sel) => document.querySelector(sel);

                const formView = $('#view-form');
                const successView = $('#view-success');
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

                function renderUser(user, expiresIn) {
                    $('#welcome-email').textContent = user.email || '–';
                    $('#avatar-letter').textContent = (user.email?.charAt(0) || '?').toUpperCase();
                    const badge = $('#role-badge');
                    const isAdmin = Boolean(user.is_admin);
                    badge.textContent = isAdmin ? 'ADMIN' : 'PENYEWA';
                    badge.classList.toggle('badge-admin', isAdmin);
                    const note = $('#token-note');
                    if (expiresIn) {
                        note.textContent = 'Token sesi berlaku ± ' + Math.round(expiresIn / 60) + ' menit.';
                    } else {
                        note.textContent = '';
                    }
                }

                const showSuccess = () => { formView.hidden = true; successView.hidden = false; };
                const showForm = () => {
                    successView.hidden = true;
                    formView.hidden = false;
                    $('#password').value = '';
                    clearError();
                };

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
                        renderUser(data.user, data.expires_in);
                        showSuccess();
                    } catch (err) {
                        showError(err.message || 'Tidak dapat terhubung ke server.');
                    } finally {
                        setBusy(false);
                    }
                });

                $('#logout-btn').addEventListener('click', () => {
                    const token = localStorage.getItem(TOKEN_KEY);
                    localStorage.removeItem(TOKEN_KEY);
                    if (token) {
                        fetch('/api/logout', {
                            method: 'POST',
                            headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
                        }).catch(() => {});
                    }
                    showForm();
                });

                async function restoreSession() {
                    const token = localStorage.getItem(TOKEN_KEY);
                    if (!token) {
                        return;
                    }
                    try {
                        const res = await fetch('/api/me', {
                            headers: { 'Accept': 'application/json', 'Authorization': 'Bearer ' + token },
                        });
                        if (res.ok) {
                            const data = await res.json();
                            renderUser(data.user, null);
                            showSuccess();
                            return;
                        }
                    } catch (e) {
                        /* invalid/expired session */
                    }
                    localStorage.removeItem(TOKEN_KEY);
                }

                restoreSession();
            })();
        </script>
    </body>
</html>
