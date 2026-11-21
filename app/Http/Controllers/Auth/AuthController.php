<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Requests\RegisterRequest;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tymon\JWTAuth\Facades\JWTAuth as JWT;

class AuthController extends Controller
{
    /**
     * Register a new customer: create the Customer (pending) and its login account.
     */
    public function register(RegisterRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = DB::transaction(function () use ($data) {
            $customer = Customer::create([
                'nik' => $data['nik'],
                'phone' => $data['phone'],
                'name' => $data['name'],
                'status' => Customer::STATUS_PENDING,
            ]);

            return User::create([
                'email' => $data['email'],
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
     * Authenticate with email + password and issue a JWT token.
     * Admins always pass; tenants only when their customer status is "accepted".
     */
    public function login(LoginRequest $request): JsonResponse
    {
        $data = $request->validated();

        $user = User::with('customer')->firstWhere('email', $data['email']);

        if ($user === null || ! Hash::check($data['password'], $user->password)) {
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
     * Return the currently authenticated user from the Bearer token.
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
     * Logout: stateless JWT has no server store; the client discards its token.
     */
    public function logout(): JsonResponse
    {
        return response()->json(['message' => 'Anda telah keluar.']);
    }

    /**
     * Keep NIK somewhat private (all but last 2 digits masked).
     */
    private function maskNik(string $nik): string
    {
        return str_repeat('*', max(0, strlen($nik) - 2)).substr($nik, -2);
    }
}
