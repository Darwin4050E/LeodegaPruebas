<?php

namespace App\Http\Controllers;

use App\Models\StoreModeration;
use Illuminate\Http\Request;

class StoreModerationController extends ApiController
{
    //
    public function index()
    {
        return $this->indexModel(StoreModeration::class);
    }

    public function show($id)
    {
        return $this->showModel(StoreModeration::class, $id);
    }

    public function store(Request $request)
    {
        $rules = [
            'store_id' => 'required|exists:storeRooms,id',
            'status' => 'required|in:pending,approved,rejected',
            'reason_rejected' => 'required|string',
            'moderation_date' => 'sometimes|date',
        ];

        return $this->storeModel($request, StoreModeration::class, $rules);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'store_id' => 'sometimes|exists:storeRooms,id',
            'status' => 'sometimes|in:pending,approved,rejected',
            'reason_rejected' => 'required|string',
            'moderation_date' => 'sometimes|date',
        ];

        return $this->updateModel($request, StoreModeration::class, $id, $rules);
    }

    public function destroy($id)
    {
        return $this->destroyModel(StoreModeration::class, $id);
    }
}
