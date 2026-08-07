<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileController extends Controller

{

    public function show(Request $request)
    {
        $user = $request->user();
        return response()->json([
            'id' => $user->id,
            'name' => $user->name,
            'lastname' => $user->lastname,
            'email' => $user->email,
            'phone' => $user->phone
        ]);
    }

    public function update(Request $request)
    {
        $user = $request->user();

        $data = $request->validate(
        [
            'name' => ['required', 'string', 'max:80'],
            'lastname' => ['required', 'string', 'max:80'],
            'email' => [
                'required',
                'email',
                Rule::unique('user', 'email')->ignore($user->id),
            ],
            'phone' => ['nullable', 'string', 'max:30'],
        ],
        [
            'email.unique' => 'Este correo electrónico ya está en uso.',
            'email.required' => 'El correo electrónico es obligatorio.',
            'email.email' => 'Debes ingresar un correo válido.',
        ]
    );

        $user->update($data);

        $user->name = $request->name;
        $user->lastname = $request->lastname;
        $user->email = $request->email;
        $user->phone = $request->phone;
        $user->save();

        return response()->json([
            'message' => 'Perfil actualizado correctamente',
            'user' => $user,
        ]);
    }
}
