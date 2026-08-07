<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;


class AuthController extends Controller
{
    public function login(Request $request)
    {
        try {
            $request->validate([
                'email' => 'required|email',
                'password' => 'required',
            ]);
            // Autentica si existe un usuario en la bd con esas credenciales
            if (! Auth::attempt($request->only('email', 'password'))) {
                return response()->json([
                    'message' => 'Credenciales inválidas',
                ], 401);
            }

            $user = User::where('email', $request->email)
                ->with(['landlord:id,user_id,optional_company', 'tenant:id,user_id,search_preference'])
                ->firstOrFail();
            $tokenResult = $user->createToken('auth_token');
            $token = $tokenResult->plainTextToken;

            // Guardar metadata solo si existe token y columnas
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



            return response()->json([
                'status' => 'success',
                'message' => 'Inicio de sesión exitoso',
                'user' => $user,
                'token' => $token,
                'token_type' => 'Bearer',
            ], 200);
        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Error al iniciar sesión',
                'error' => $e->getMessage(),
            ], 500);
        }
    }

    public function me(Request $request)
    {
        return response()->json([
            'user' => $request->user(),
        ]);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();

        return response()->json([
            'message' => 'Sesión cerrada correctamente',
        ], 200);
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'phone' => 'required|string|max:20',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'name' => $validated['name'],
            'lastname' => $validated['lastname'],
            'phone' => $validated['phone'],
            'email' => $validated['email'],
            'password' => Hash::make($validated['password']),
        ]);
        // ya con token de acceso
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

        return response()->json([
            'status' => 'success',
            'message' => 'Usuario creado correctamente',
            'user' => $user,
            'token' => $token,
            'token_type' => 'Bearer',
        ], 201);
    }
}
