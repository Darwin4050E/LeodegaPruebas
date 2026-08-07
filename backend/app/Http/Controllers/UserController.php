<?php

namespace App\Http\Controllers;

use App\Models\Landlords;
use App\Models\Tenants;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;

class UserController extends ApiController
{
    public function index()
    {
        return $this->indexModel(User::class);
    }

    public function show($id)
    {
        return $this->showModel(User::class, $id);
    }

    public function store(Request $request)
    {
        $rules = [
            'name' => 'required|string|max:255',
            'lastname' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:user',
            'phone' => 'required|string|unique:user|max:10',
            'password' => 'required|string|min:8',
            'role' => 'in:admin,landlord,tenant',
            'start_date' => 'date|default:now()',
            'state' => 'in:active,blocked,pending',
            'enable_messages' => 'required|boolean',
        ];

        return DB::transaction(function () use ($request, $rules) {
            $response = $this->storeModel($request, User::class, $rules);
            $data = $response->getData();

            if ($response->getStatusCode() === 201 && isset($data->item->id)) {
                $user = $data->item;

                if ($user->role === 'landlord') {
                    Landlords::create([
                        'user_id' => $user->id,
                        'optional_company' => null,
                    ]);
                } elseif ($user->role === 'tenant') {
                    Tenants::create([
                        'user_id' => $user->id,
                        'search_preference' => '',
                    ]);
                }
            }

            return $response;
        });
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'name' => 'sometimes|required|string|max:255',
            'lastname' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:user,email,' . $id,
            'phone' => 'sometimes|required|string|unique:user,phone,' . $id . '|max:10',
            'password' => 'sometimes|required|string|min:8',
            'role' => 'sometimes|in:admin,landlord,tenant',
            'start_date' => 'sometimes|date|nullable',
            'state' => 'sometimes|in:active,blocked,pending',
            'enable_messages' => 'sometimes|boolean',
        ];

        return $this->updateModel($request, User::class, $id, $rules);
    }

    public function destroy($id)
    {
        return $this->destroyModel(User::class, $id);
    }

    public function destroySelf()
    {
        $authId = Auth::id();
        if (!$authId) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        DB::transaction(function () use ($authId) {

            
            DB::table('conversation_user')->where('user_id', $authId)->delete();
            DB::table('messages')->where('sender_id', $authId)->delete();
            DB::table('personal_access_tokens')
                ->where('tokenable_id', $authId)
                ->where('tokenable_type', User::class)
                ->delete();

            User::where('id', $authId)->delete();
        });

        return response()->json(['message' => 'Cuenta eliminada correctamente'], 200);
    }

    //
}
