<?php

namespace App\Http\Controllers;

use App\Models\StoreDisponibility;
use Illuminate\Http\Request;

class StoreDisponibilityController extends ApiController
{
    public function index()
    {
        return $this->indexModel(StoreDisponibility::class);
    }

    public function show($id)
    {
        return $this->showModel(StoreDisponibility::class, $id);
    }

    public function store(Request $request)
    {
        $rules = [
            'store_id' => 'required|exists:storeRooms,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ];

        return $this->storeModel($request, StoreDisponibility::class, $rules);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'store_id' => 'required|exists:storeRooms,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ];

        return $this->updateModel($request, StoreDisponibility::class, $id, $rules);
    }

    public function destroy($id)
    {
        return $this->destroyModel(StoreDisponibility::class, $id);
    }
}
