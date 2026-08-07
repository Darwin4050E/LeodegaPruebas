<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreStoreRoomRequest;
use App\Http\Requests\UpdateStoreRoomRequest;
use App\Models\Landlords;
use App\Models\Ratings;
use App\Models\StoreRooms;
use Illuminate\Http\Request;

class StoreRoomsController extends ApiController
{
    public function index()
    {
        return StoreRooms::with(['storePrices', 'storePhotos', 'landlord.user'])
            ->get()
            ->map(function ($room) {
                $ratings = Ratings::where('store_id', $room->id);
                $avg = round($ratings->avg('stars'), 1);
                $count = $ratings->count();

                return [
                    'id' => $room->id,
                    'title' => $room->title,
                    'city' => $room->city,
                    'size' => $room->size,
                    'publication_status' => $room->publication_status,
                    'landlord' => [
                        'id' => $room->landlord?->id,
                        'user' => [
                            'name' => $room->landlord?->user?->name,
                            'email' => $room->landlord?->user?->email,
                            'phone' => $room->landlord?->user?->phone,
                        ],
                    ],
                    'user_id' => $room->landlord?->user?->id,
                    'store_prices' => $room->storePrices,
                    'rating_avg' => $avg,
                    'rating_count' => $count,
                    'image' => $room->storePhotos->first()
                        ? asset('storage/'.$room->storePhotos->first()->photo_url)
                        : null,
                ];
            });
    }

    public function show($id)
    {
        return $this->showModel(StoreRooms::class, $id);
    }

    public function store(Request $request)
    {
        return $this->storeModel($request, StoreRooms::class, (new StoreStoreRoomRequest)->rules());
    }

    public function update(Request $request, $id)
    {
        return $this->updateModel($request, StoreRooms::class, $id, (new UpdateStoreRoomRequest)->rules());
    }

    public function destroy($id)
    {
        return $this->destroyModel(StoreRooms::class, $id);
    }

    public function getByLandlord($landlordId)
    {
        $landlord = Landlords::find($landlordId);
        if (! $landlord) {
            return response()->json(['message' => 'Landlord no encontrado'], 404);
        }

        $storeRooms = StoreRooms::with(['storePrices', 'storePhotos', 'storeDisponibility'])
            ->where('landlord_id', $landlordId)
            ->get()
            ->map(function ($room) {
                $firstPhoto = $room->storePhotos->first();

                return [
                    'id' => $room->id,
                    'title' => $room->title,
                    'direction' => $room->direction,
                    'city' => $room->city,
                    'size' => $room->size,
                    'publication_status' => $room->publication_status,
                    'storage_type' => $room->storage_type,
                    'room_type' => $room->room_type,
                    'store_prices' => $room->storePrices,
                    'image' => $firstPhoto ? asset('storage/'.$firstPhoto->photo_url) : null,
                ];
            });

        if ($storeRooms->isEmpty()) {
            return response()->json(['message' => 'No se encontraron bodegas para este landlord'], 404);
        }

        return response()->json($storeRooms, 200);
    }

    public function detail($id)
    {
        $room = StoreRooms::with([
            'storePrices',
            'storePhotos',
            'landlord.user',
        ])->find($id);

        if (! $room) {
            return response()->json(['message' => 'Bodega no encontrada'], 404);
        }

        return response()->json([
            'id' => $room->id,
            'title' => $room->title,
            'description' => $room->description,
            'direction' => $room->direction,
            'city' => $room->city,
            'size' => $room->size,
            'security' => $room->security,
            'room_type' => $room->room_type,
            'storage_type' => $room->storage_type,

            'prices' => $room->storePrices,

            'photos' => $room->storePhotos->map(fn ($p) => asset('storage/'.$p->photo_url)),

            'landlord' => [
                'id' => $room->landlord->id,
                'user_id' => $room->landlord->user->id,
                'name' => $room->landlord->user->name,
                'email' => $room->landlord->user->email,
            ],
        ]);
    }
}
