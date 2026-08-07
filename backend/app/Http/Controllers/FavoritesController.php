<?php

namespace App\Http\Controllers;

use App\Models\Favorites;
use Illuminate\Http\Request;

class FavoritesController extends ApiController
{
    //
    public function index()
    {
        return $this->indexModel(Favorites::class);
    }

    public function show($id)
    {
        return $this->showModel(Favorites::class, $id);
    }

    public function store(Request $request)
    {
        $rules = [
            'user_id' => 'required|exists:user,id',
            'store_id' => 'required|exists:storeRooms,id',
            'save_date' => 'date',
        ];

        return $this->storeModel($request, Favorites::class, $rules);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'user_id' => 'sometimes|required|exists:user,id',
            'store_id' => 'sometimes|required|exists:storeRooms,id',
            'save_date' => 'sometimes|required|date',
        ];

        return $this->updateModel($request, Favorites::class, $id, $rules);
    }

    public function destroy($id)
    {
        return $this->destroyModel(Favorites::class, $id);
    }
}
