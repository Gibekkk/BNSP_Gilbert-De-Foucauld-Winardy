<!DOCTYPE html>
<html lang="id">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Daftar · PNSP</title>
        <link rel="stylesheet" href="{{ asset('css/register.css') }}">
    </head>
    <body>
        <main class="page">
            <!-- ===== View: registration form ===== -->
            <section id="view-form" class="card" aria-label="Formulir pendaftaran pelanggan">
                <header class="card-header">
                    <span class="brand-mark" aria-hidden="true">P</span>
                    <div>
                        <h1>Daftar Pelanggan</h1>
                        <p>Isi data diri Anda. Akun aktif setelah diverifikasi admin.</p>
                    </div>
                </header>

                <div id="form-error" class="msg error" role="alert" hidden></div>

                <form id="register-form">
                    <div class="field">
                        <label for="nik">NIK (16 digit)</label>
                        <input type="text" id="nik" name="nik" inputmode="numeric" maxlength="16" required
                               placeholder="3201xxxxxxxxxxxx">
                        <div class="field-error" id="err-nik" hidden></div>
                    </div>

                    <div class="field">
                        <label for="phone">Nomor Telepon</label>
                        <input type="tel" id="phone" name="phone" autocomplete="tel" required
                               placeholder="081234567890">
                        <div class="field-error" id="err-phone" hidden></div>
                    </div>

                    <div class="field">
                        <label for="name">Nama Lengkap</label>
                        <input type="text" id="name" name="name" autocomplete="name" required maxlength="100"
                               placeholder="Nama sesuai identitas">
                        <div class="field-error" id="err-name" hidden></div>
                    </div>

                    <hr class="sep">

                    <p class="account-hint muted">Detail akun login</p>

                    <div class="field">
                        <label for="email">Email</label>
                        <input type="email" id="email" name="email" autocomplete="email" required
                               placeholder="nama@contoh.com">
                        <div class="field-error" id="err-email" hidden></div>
                    </div>

                    <div class="field">
                        <label for="password">Password</label>
                        <input type="password" id="password" name="password" autocomplete="new-password" required
                               minlength="8" placeholder="Minimal 8 karakter">
                        <div class="field-error" id="err-password" hidden></div>
                    </div>

                    <div class="field">
                        <label for="password_confirmation">Konfirmasi Password</label>
                        <input type="password" id="password_confirmation" name="password_confirmation"
                               autocomplete="new-password" required placeholder="Ulangi password">
                        <div class="field-error" id="err-password_confirmation" hidden></div>
                    </div>

                    <button type="submit" class="btn btn-primary" id="register-submit">Daftar</button>
                </form>

                <p class="hint">Sudah punya akun? <a href="{{ route('login') }}">Masuk di sini</a></p>
            </section>

            <!-- ===== View: success ===== -->
            <section id="view-success" class="card" aria-label="Pendaftaran berhasil" hidden>
                <div class="success-wrap">
                    <span class="done-icon" aria-hidden="true">✓</span>
                    <h1>Pendaftaran Berhasil</h1>
                    <p class="muted">
                        Akun Anda sedang menunggu verifikasi admin. Anda dapat masuk
                        setelah status akun menjadi <strong>diterima</strong>.
                    </p>
                    <a class="btn btn-ghost" href="{{ route('login') }}">Kembali ke halaman masuk</a>
                </div>
            </section>
        </main>

        <script>
            (function () {
                'use strict';

                const $ = (sel) => document.querySelector(sel);
                const FIELD_KEYS = ['nik', 'phone', 'name', 'email', 'password', 'password_confirmation'];

                function setFieldError(field, message) {
                    const box = document.querySelector('#err-' + field);
                    if (!box) {
                        return;
                    }
                    box.textContent = message || '';
                    box.hidden = !message;
                }

                function clearFieldErrors() {
                    FIELD_KEYS.forEach((field) => setFieldError(field, ''));
                    const top = $('#form-error');
                    top.textContent = '';
                    top.hidden = true;
                }

                $('#register-form').addEventListener('submit', async (event) => {
                    event.preventDefault();
                    clearFieldErrors();
                    const btn = $('#register-submit');
                    btn.disabled = true;
                    btn.textContent = 'Memproses…';

                    try {
                        const res = await fetch('/api/register', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json', 'Accept': 'application/json' },
                            body: JSON.stringify({
                                nik: $('#nik').value.trim(),
                                phone: $('#phone').value.trim(),
                                name: $('#name').value.trim(),
                                email: $('#email').value.trim(),
                                password: $('#password').value,
                                password_confirmation: $('#password_confirmation').value,
                            }),
                        });

                        const data = await res.json().catch(() => ({}));

                        if (!res.ok) {
                            if (data.errors && typeof data.errors === 'object') {
                                FIELD_KEYS.forEach((field) => {
                                    setFieldError(field, Array.isArray(data.errors[field]) ? data.errors[field][0] : '');
                                });
                            }
                            const top = $('#form-error');
                            top.textContent = data.message || 'Pendaftaran gagal. Periksa kembali isian Anda.';
                            top.hidden = false;

                            let firstInvalid = '';
                            for (const field of FIELD_KEYS) {
                                if (!document.querySelector('#err-' + field)?.hidden) {
                                    firstInvalid = field;
                                    break;
                                }
                            }
                            if (firstInvalid) {
                                document.querySelector('#' + firstInvalid).focus();
                                top.scrollIntoView({ behavior: 'smooth', block: 'center' });
                            }
                            return;
                        }

                        $('#view-form').hidden = true;
                        $('#view-success').hidden = false;
                    } catch (err) {
                        const top = $('#form-error');
                        top.textContent = err.message || 'Tidak dapat terhubung ke server.';
                        top.hidden = false;
                    } finally {
                        btn.disabled = false;
                        btn.textContent = 'Daftar';
                    }
                });
            })();
        </script>

        <style>
            /* small local additions for this module */
            .sep {
                border: none;
                border-top: 1px solid var(--border);
                margin: 20px 0 14px;
            }

            .account-hint {
                font-size: 12.5px;
                font-weight: 600;
                letter-spacing: .04em;
                text-transform: uppercase;
            }
        </style>
    </body>
</html>
