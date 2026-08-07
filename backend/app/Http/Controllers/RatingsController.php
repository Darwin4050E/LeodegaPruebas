<?php

namespace App\Http\Controllers;

use App\Models\Ratings;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class RatingsController extends ApiController
{
    public function index(Request $request)
    {
        $storeId = $request->query('store_id');

        if (!$storeId) {
            return response()->json(['message' => 'store_id requerido'], 400);
        }

        $ratings = Ratings::where('store_id', $storeId)->get();

        return response()->json([
            'average' => round($ratings->avg('stars'), 1),
            'total' => $ratings->count(),
            'ratings' => $ratings,
        ]);
    }

    public function show($id)
    {
        return $this->showModel(Ratings::class, $id);
    }

    public function store(Request $request)
    {
        $user = Auth::user();

        if (!$user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        $validated = $request->validate([
            'store_id' => 'required|exists:storeRooms,id',
            'stars' => 'required|integer|between:1,5',
            'comment' => 'required|string',
        ]);

        $alreadyRated = Ratings::where('store_id', $validated['store_id'])
            ->where('user_id', $user->id)
            ->exists();

        if ($alreadyRated) {
            return response()->json([
                'message' => 'Ya calificaste esta bodega'
            ], 409);
        }

        $rating = Ratings::create([
            'store_id' => $validated['store_id'],
            'user_id' => $user->id,
            'stars' => $validated['stars'],
            'comment' => $validated['comment'],
        ]);

        return response()->json([
            'message' => 'Rating creado correctamente',
            'rating' => $rating
        ], 201);
    }

    public function update(Request $request, $id)
    {
        $rules = [
            'store_id' => 'sometimes|exists:storeRooms,id',
            'stars' => 'sometimes|integer|between:1,5',
            'comment' => 'sometimes|string',
        ];

        return $this->updateModel($request, Ratings::class, $id, $rules);
    }

    public function destroy($id)
    {
        return $this->destroyModel(Ratings::class, $id);
    }
}
