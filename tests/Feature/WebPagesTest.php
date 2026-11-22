<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Feature test halaman web: setiap halaman dapat dibuka (autentikasi dilakukan di sisi API).
 */
class WebPagesTest extends TestCase
{
    /**
     * Halaman utama diarahkan ke halaman login.
     */
    public function test_home_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    /**
     * Semua halaman portal tampil (HTTP 200).
     */
    public function test_all_pages_render(): void
    {
        foreach (['/login', '/register', '/admin', '/admin/customers', '/admin/garages', '/admin/rental-approvals', '/admin/rentals', '/admin/reports', '/customer'] as $uri) {
            $this->get($uri)->assertOk();
        }
    }

    /**
     * Halaman login tidak lagi memiliki layar perantara setelah masuk.
     */
    public function test_login_page_redirects_straight_to_the_dashboard(): void
    {
        $this->get('/login')->assertDontSee('view-success', false)->assertSee('goByRole', false);
    }
}
