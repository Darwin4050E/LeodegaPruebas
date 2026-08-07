<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoreModerationRequest;
use App\Http\Requests\UpdateStoreModerationRequest;
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
        return $this->storeModel($request, StoreModeration::class, (new StoreStoreModerationRequest)->rules());
    }

    public function update(Request $request, $id)
    {
        return $this->updateModel($request, StoreModeration::class, $id, (new UpdateStoreModerationRequest)->rules());
    }

    public function destroy($id)
    {
        return $this->destroyModel(StoreModeration::class, $id);
    }
}
