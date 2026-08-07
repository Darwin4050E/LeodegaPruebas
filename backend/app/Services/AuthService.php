<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class AuthService
{
    /**
     * Extraído de AuthController: este bloque vivía duplicado de forma
     * idéntica en login() y register(). Crea el token de acceso y, si es
     * posible, guarda IP/user-agent en el token recién creado. Un fallo al
     * guardar esa metadata se registra en el log pero no impide continuar
     * (mismo comportamiento que antes).
     */
    public function issueTokenFor(User $user, Request $request): string
    {
        $tokenResult = $user->createToken('auth_token');
        $token = $tokenResult->plainTextToken;

        try {
            $latestToken = $user->tokens()->latest('id')->first();
            if ($latestToken) {
                $latestToken->ip_address = $request->ip();
                $latestToken->user_agent = $request->userAgent();
                $latestToken->save();
            }
        } catch (\Throwable $e) {
            Log::warning('No se pudo guardar metadata del token', [
                'error' => $e->getMessage(),
            ]);
        }

        return $token;
    }
}
