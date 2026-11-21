<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Exceptions\JWTException;
use Tymon\JWTAuth\Facades\JWTAuth as JWT;

/**
 * Autentikasi JWT: daftar, login, profil, logout, dan refresh token.
 */
class AuthController extends Controller
{
    /**
     * Daftarkan pelanggan baru: buat Customer (pending) beserta akun login-nya.
     * Keunikan email/NIK dicek di sini agar akun yang ditolak dapat mendaftar ulang.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $email = mb_strtolower(trim($data['email']));
        $nik = trim($data['nik']);

        if ($this->hasActiveAccountForEmail($email)) {
            return response()->json([
                'message' => 'Email sudah terdaftar.',
                'errors' => ['email' => ['Email sudah terdaftar.']],
            ], 422);
        }

        if (Customer::query()->where('nik', $nik)->where('status', '!=', Customer::STATUS_DECLINED)->exists()) {
            return response()->json([
                'message' => 'NIK sudah terdaftar.',
                'errors' => ['nik' => ['NIK sudah terdaftar.']],
            ], 422);
        }

        $user = DB::transaction(function () use ($data, $email) {
            $customer = Customer::create([
                'nik' => $data['nik'],
                'phone' => $data['phone'],
                'name' => $data['name'],
                'status' => Customer::STATUS_PENDING,
            ]);

            return User::create([
                'email' => $email,
                'password' => $data['password'],
                'customer_id' => $customer->id,
                'is_admin' => false,
            ]);
        });

        return response()->json([
            'message' => 'Pendaftaran berhasil. Akun menunggu verifikasi admin.',
            'user' => [
                'email' => $user->email,
                'is_admin' => false,
            ],
        ], 201);
    }

    /**
     * Login dengan email + password lalu terbitkan token JWT.
     * Akun terbaru yang tidak ditolak untuk email tersebut dipakai; admin selalu lolos;
     * penyewa hanya bila status pelanggannya "accepted".
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $email = mb_strtolower(trim($data['email']));

        /** @var Collection<int, User> $candidates */
        $candidates = User::query()
            ->where('email', $email)
            ->with('customer')
            ->orderByDesc('id')
            ->get();

        if ($candidates->isEmpty()) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        $user = $candidates->first(fn (User $candidate): bool => ! $this->isDeclinedAccount($candidate));

        if ($user === null) {
            return response()->json(
                ['message' => 'Akun ditolak oleh admin. Anda dapat mendaftarkan ulang.'],
                403,
            );
        }

        if (! Hash::check($data['password'], $user->password)) {
            return response()->json(['message' => 'Email atau password salah.'], 401);
        }

        if (! $user->is_admin) {
            $customer = $user->customer;

            if ($customer === null || $customer->status !== Customer::STATUS_ACCEPTED) {
                return response()->json(['message' => 'Akun belum diverifikasi oleh admin.'], 403);
            }
        }

        $token = JWT::fromUser($user);

        return response()->json([
            'token' => (string) $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
            'user' => [
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'customer_status' => $user->customer?->status,
            ],
        ]);
    }

    /**
     * Kembalikan user yang sedang login berdasarkan token Bearer.
     */
    public function me(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user()->load('customer:id,nik,phone,name,status');

        return response()->json([
            'user' => [
                'email' => $user->email,
                'is_admin' => (bool) $user->is_admin,
                'customer' => $user->customer ? [
                    'nik' => $this->maskNik($user->customer->nik),
                    'phone' => $user->customer->phone,
                    'name' => $user->customer->name,
                    'status' => $user->customer->status,
                ] : null,
            ],
        ]);
    }

    /**
     * Logout: batalkan JWT saat ini agar tidak dapat dipakai lagi.
     * Token yang kosong atau sudah tidak valid tetap mengembalikan respons sukses.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            if ($request->bearerToken() !== null) {
                JWT::setToken($request->bearerToken())->invalidate();
            }
        } catch (\Throwable) {
            // Token kosong atau sudah tidak valid — tidak ada yang perlu dibatalkan.
        }

        return response()->json(['message' => 'Anda telah keluar.']);
    }

    /**
     * Tukar JWT yang kedaluwarsa (tetapi masih dalam masa refresh) dengan yang baru.
     *
     * Route ini sengaja di luar middleware auth:api, karena middleware tersebut
     * menolak token kedaluwarsa — padahal justru saat itulah browser
     * perlu memanggilnya. Token lama otomatis masuk blacklist saat refresh.
     */
    public function refresh(): JsonResponse
    {
        try {
            $token = JWT::parseToken()->refresh();

            /** @var User|null $user */
            $user = JWT::setToken($token)->authenticate();
        } catch (JWTException) {
            return response()->json(['message' => 'Sesi berakhir, silakan masuk kembali.'], 401);
        }

        // Penyewa yang ditolak (atau belum disetujui) tidak boleh mempertahankan sesi.
        if ($user === null || (! $user->is_admin && $user->customer?->status !== Customer::STATUS_ACCEPTED)) {
            return response()->json(['message' => 'Akun tidak lagi memiliki akses.'], 401);
        }

        return response()->json([
            'token' => (string) $token,
            'token_type' => 'Bearer',
            'expires_in' => config('jwt.ttl') * 60,
        ]);
    }

    /**
     * True bila email sudah dipakai akun yang tidak ditolak (atau oleh admin).
     */
    private function hasActiveAccountForEmail(string $email): bool
    {
        return User::query()
            ->where('email', $email)
            ->where(function ($query): void {
                $query->whereNull('customer_id')
                    ->orWhereHas('customer', fn ($c) => $c->where('status', '!=', Customer::STATUS_DECLINED));
            })
            ->exists();
    }

    /**
     * Akun yang ditolak bersifat final dan tidak dapat login lagi.
     */
    private function isDeclinedAccount(User $user): bool
    {
        return ! $user->is_admin && $user->customer?->status === Customer::STATUS_DECLINED;
    }

    /**
     * Samarkan NIK (semua kecuali 2 digit terakhir).
     */
    private function maskNik(string $nik): string
    {
        return str_repeat('*', max(0, strlen($nik) - 2)).substr($nik, -2);
    }
}
