<?php

namespace App\Http\Controllers;

use App\Models\Landlords;
use Illuminate\Http\Request;

class LandlordsController extends ApiController
{
    //
    public function index()
    {
        return $this->indexModel(Landlords::class);
    }

    public function show($id)
    {
        return $this->showModel(Landlords::class, $id);
    }

    public function store(Request $request)
    {
        $rules = [
            'user_id' => 'required|exists:user,id',
            'optional_company' => 'sometimes|string|max:500|nullable',
        ];

        return $this->storeModel($request, Landlords::class, $rules);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'user_id' => 'sometimes|required|exists:user,id',
            'optional_company' => 'sometimes|required|string|max:500|nullable',
        ];

        return $this->updateModel($request, Landlords::class, $id, $rules);
    }

    public function destroy($id)
    {
        return $this->destroyModel(Landlords::class, $id);
    }
}
