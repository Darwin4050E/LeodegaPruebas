<?php

namespace App\Http\Controllers;

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
        $rules = [
            'reservation_id' => 'required|exists:reservations,id',
            'payment_method' => 'required|in:credit card,debit card',
            'payment_state' => 'required|in:paid,pending,failed',
            'payment_date' => 'date',
        ];

        return $this->storeModel($request, Payments::class, $rules);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'reservation_id' => 'sometimes|exists:reservations,id',
            'payment_method' => 'sometimes|in:credit card,debit card',
            'payment_state' => 'sometimes|in:paid,pending,failed',
            'payment_date' => 'sometimes|date',
        ];

        return $this->updateModel($request, Payments::class, $id, $rules);
    }

    public function destroy($id)
    {
        return $this->destroyModel(Payments::class, $id);
    }
}
