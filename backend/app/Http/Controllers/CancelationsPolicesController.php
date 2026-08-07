<?php

namespace App\Http\Controllers;

use App\Models\cancelations_polices;
use Illuminate\Http\Request;

class CancelationsPolicesController extends ApiController
{
    //
    public function index()
    {
        return $this->indexModel(cancelations_polices::class);
    }

    public function show($id)
    {
        return $this->showModel(cancelations_polices::class, $id);
    }

    public function store(Request $request)
    {
        $rules = [
            'landlord_id' => 'required|exists:landlords,id',
            'policy_name' => 'required|string|max:100',
            'description' => 'required|string|max:1000',
            'is_default' => 'required|boolean',
        ];

        return $this->storeModel($request, cancelations_polices::class, $rules);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'landlord_id' => 'sometimes|required|exists:landlords,id',
            'policy_name' => 'sometimes|required|string|max:100',
            'description' => 'sometimes|required|string|max:1000',
            'is_default' => 'sometimes|required|boolean',
        ];

        return $this->updateModel($request, cancelations_polices::class, $id, $rules);
    }

    public function destroy($id)
    {
        return $this->destroyModel(cancelations_polices::class, $id);
    }
}
