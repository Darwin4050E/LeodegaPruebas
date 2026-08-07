<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class SecurityController extends Controller
{
    public function changePassword(Request $request)
    {
        $user = $request->user();
        $request->validate(
    [
        'actual_p' => ['required'],
        'new_p' => ['required', 'min:8'],
        'new_p_c' => ['required', 'same:new_p'],
    ],
    [
        'actual_p.required' => 'La contraseña actual es obligatoria.',
        'new_p.required' => 'La nueva contraseña es obligatoria.',
        'new_p.min' => 'La nueva contraseña debe tener al menos 8 caracteres.',
        'new_p_c.required' => 'Debes confirmar la nueva contraseña.',
        'new_p_c.same' => 'Las contraseñas no coinciden.',
    ]);
    
        $data = $request->validate([
            'actual_p' => ['required'],
            'new_p' => ['required', 'min:8'],
            'new_p_c' => ['required', 'same:new_p'],

        ]);

        if (!Hash::check($data['actual_p'], $user->password)) {
            throw ValidationException::withMessages([
                'actual_p' => ['La contraseña actual no es correcta'],
            ]);
        }

        $user->password = Hash::make($data['new_p']);
        $user->save();

        return response()->json(['message' => 'Contraseña actualizada']);
    }
}
