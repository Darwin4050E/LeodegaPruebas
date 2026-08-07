<?php

namespace App\Http\Controllers;

use App\Models\Admin;
use Illuminate\Http\Request;

class AdminController extends ApiController
{
    //
    public function index()
    {
        return $this->indexModel(Admin::class);
    }

    public function show($id)
    {
        return $this->showModel(Admin::class, $id);
    }

    public function store(Request $request)
    {
        $rules = [
            'user_id' => 'required|exists:user,id',
            'admin_level' => 'required|integer|min:1|max:2',
        ];

        return $this->storeModel($request, Admin::class, $rules);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'user_id' => 'sometimes|required|exists:user,id',
            'admin_level' => 'sometimes|required|integer|min:1|max:2',
        ];

        return $this->updateModel($request, Admin::class, $id, $rules);
    }

    public function destroy($id)
    {
        return $this->destroyModel(Admin::class, $id);
    }
}
