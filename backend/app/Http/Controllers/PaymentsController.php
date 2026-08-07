<?php

namespace App\Http\Controllers;

use App\Http\Requests\StorePaymentRequest;
use App\Http\Requests\UpdatePaymentRequest;
use App\Models\Payments;
use Illuminate\Http\Request;

class PaymentsController extends ApiController
{
    public function index()
    {
        return $this->indexModel(Payments::class);
    }

    public function show($id)
    {
        return $this->showModel(Payments::class, $id);
    }

    public function store(Request $request)
    {
        return $this->storeModel($request, Payments::class, (new StorePaymentRequest)->rules());
    }

    public function update(Request $request, $id)
    {
        return $this->updateModel($request, Payments::class, $id, (new UpdatePaymentRequest)->rules());
    }

    public function destroy($id)
    {
        return $this->destroyModel(Payments::class, $id);
    }
}
