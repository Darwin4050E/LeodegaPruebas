<?php

namespace App\Http\Controllers;

use App\Models\Tenants;
use Illuminate\Http\Request;

class TenantsController extends ApiController
{
    //
    public function index()
    {
        return $this->indexModel(Tenants::class);
    }

    public function show($id)
    {
        return $this->showModel(Tenants::class, $id);
    }

    public function store(Request $request)
    {
        $rules = [
            'search_preference' => 'required|string',
            'user_id' => 'required|exists:users,id',
        ];

        return $this->storeModel($request, Tenants::class, $rules);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'search_preference' => 'sometimes|required|string',
            'user_id' => 'sometimes|required|exists:users,id',
        ];

        return $this->updateModel($request, Tenants::class, $id, $rules);
    }

    public function destroy($id)
    {
        return $this->destroyModel(Tenants::class, $id);
    }
}
