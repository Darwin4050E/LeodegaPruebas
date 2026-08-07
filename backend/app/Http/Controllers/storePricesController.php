<?php

namespace App\Http\Controllers;

use App\Models\StorePrices;
use Illuminate\Http\Request;

class StorePricesController extends ApiController
{
    public function index()
    {
        return $this->indexModel(StorePrices::class);
    }

    public function show($id)
    {
        return $this->showModel(StorePrices::class, $id);
    }

    public function store(Request $request)
    {
        $rules = [
            'store_room_id' => 'required|exists:storeRooms,id',
            'mode' => 'required|in:day,month,year',
            'price' => 'required|numeric|min:0.5',
            'disponibility' => 'boolean',
        ];

        return $this->storeModel($request, StorePrices::class, $rules);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'store_room_id' => 'exists:storeRooms,id',
            'mode' => 'in:day,month,year',
            'price' => 'numeric|min:0.5',
            'disponibility' => 'boolean',
        ];

        return $this->updateModel($request, StorePrices::class, $id, $rules);
    }

    public function destroy($id)
    {
        return $this->destroyModel(StorePrices::class, $id);
    }
}
