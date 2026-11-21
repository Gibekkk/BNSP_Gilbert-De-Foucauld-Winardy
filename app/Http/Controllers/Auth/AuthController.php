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
use Tymon\JWTAuth\Facades\JWTAuth as JWT;

class AuthController extends Controller
{
    /**
     * Register a new customer: create the Customer (pending) and its login account.
     * Email/NIK uniqueness is enforced here so declined accounts can re-register.
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
     * Authenticate with email + password and issue a JWT token.
     * The newest non-declined account for the email wins; admins always pass;
     * tenants only when their customer status is "accepted".
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
     * Logout: invalidate the current JWT so it can no longer be used.
     * A missing or already-invalid token still returns the same success.
     */
    public function logout(Request $request): JsonResponse
    {
        try {
            if ($request->bearerToken() !== null) {
                JWT::setToken($request->bearerToken())->invalidate();
            }
        } catch (\Throwable) {
            // The token was missing or already invalid — nothing to invalidate.
        }

        return response()->json(['message' => 'Anda telah keluar.']);
    }

    /**
     * True when a non-declined account (or an admin) already uses this email.
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
     * Declined accounts are terminal and can never log in again.
     */
    private function isDeclinedAccount(User $user): bool
    {
        return ! $user->is_admin && $user->customer?->status === Customer::STATUS_DECLINED;
    }

    /**
     * Keep NIK somewhat private (all but last 2 digits masked).
     */
    private function maskNik(string $nik): string
    {
        return str_repeat('*', max(0, strlen($nik) - 2)).substr($nik, -2);
    }
}
