<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\Reservations;
use App\Models\StoreRooms;
use App\Models\Tenants;
use App\Models\Landlords;
use App\Services\NotificationService;
use Illuminate\Http\Request;

class ReservationsController extends Controller
{

    public function store(Request $request)
    {
        $data = $request->validate([
            'store_room_id' => ['required', 'exists:storeRooms,id'],
            'start_date'    => ['required', 'date'],
            'end_date'      => ['required', 'date', 'after_or_equal:start_date'],
            'total_mount'   => ['required', 'numeric', 'min:0'],
        ]);

        $user = $request->user();
        $tenant = Tenants::where('user_id', $user->id)->firstOrFail();

        $room = StoreRooms::findOrFail($data['store_room_id']);
        $hasConflict = Reservations::where('store_room_id', $room->id)
            ->where('status', 'confirmed')
            ->whereDate('start_date', '<=', $data['end_date'])
            ->whereDate('end_date', '>=', $data['start_date'])
            ->exists();

        if ($hasConflict) {
            return response()->json([
                'message' => 'La bodega ya está reservada en esas fechas.'
            ], 409);
        }

        $reservation = Reservations::create([
            'store_room_id' => $room->id,
            'tenant_id' => $tenant->id,
            'start_date' => $data['start_date'],
            'end_date' => $data['end_date'],
            'status' => 'pending',
            'total_mount' => $data['total_mount'],
            'cancelation_reason' => null,
            'creation_date' => now(),
        ]);

        $room->load('landlord.user');
        if ($room->landlord && $room->landlord->user) {
            NotificationService::send(
                auth()->id(),
                $room->landlord->user->id,
                NotificationType::RESERVATION_REQUEST,
                'Nueva solicitud de reserva',
                'Han solicitado reservar tu bodega',
                [
                    'reservation_id' => $reservation->id,
                    'store_room_id' => $room->id,
                ]
            );
        }

        return response()->json([
            'message' => 'Solicitud enviada',
            'reservation' => $reservation
        ], 201);
    }


    public function landlordIndex(Request $request)
    {
        $user = $request->user();
        $landlord = Landlords::where('user_id', $user->id)->firstOrFail();

        $items = Reservations::with([
            'storeRooms:id,title,direction,city,size,room_type,landlord_id',
            'tenants.user:id,name,lastname,email,phone'
        ])
            ->whereHas('storeRooms', fn($q) => $q->where('landlord_id', $landlord->id))
            ->orderByDesc('created_at')
            ->get();

        return response()->json($items);
    }


    public function updateStatus(Request $request, Reservations $reservation)
    {
        $data = $request->validate([
            'status' => ['required', 'in:confirmed,canceled'],
            'cancelation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        $user = $request->user();
        $landlord = Landlords::where('user_id', $user->id)->firstOrFail();

        $reservation->load('storeRooms');


        if ($reservation->storeRooms->landlord_id !== $landlord->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }


        if ($data['status'] === 'confirmed') {


            $hasConfirmedConflict = Reservations::where('store_room_id', $reservation->store_room_id)
                ->where('status', 'confirmed')
                ->where('id', '!=', $reservation->id)
                ->whereDate('start_date', '<=', $reservation->end_date)
                ->whereDate('end_date', '>=', $reservation->start_date)
                ->exists();

            if ($hasConfirmedConflict) {
                return response()->json([
                    'message' => 'Ya existe una reserva confirmada en esas fechas.'
                ], 409);
            }


            $reservation->update([
                'status' => 'confirmed',
                'cancelation_reason' => null,
            ]);

            NotificationService::send(
                auth()->id(),
                $reservation->tenants->user->id,
                NotificationType::RESERVATION_CONFIRMED,
                'Reserva confirmada',
                'Tu reserva ha sido confirmada',
                [
                    'reservation_id' => $reservation->id,
                    'store_room_id' => $reservation->store_room_id,
                ]
            );

            Reservations::where('store_room_id', $reservation->store_room_id)
                ->where('status', 'pending')
                ->where('id', '!=', $reservation->id)
                ->whereDate('start_date', '<=', $reservation->end_date)
                ->whereDate('end_date', '>=', $reservation->start_date)
                ->update([
                    'status' => 'canceled',
                    'cancelation_reason' => 'Blocked by confirmed reservation',
                ]);

            return response()->json([
                'message' => 'Reserva confirmada y el resto bloqueado',
                'reservation' => $reservation->load(['storeRooms', 'tenants.user']),
            ]);
        }

        $reservation->update([
            'status' => 'canceled',
            'cancelation_reason' => $data['cancelation_reason'] ?? 'Rejected by landlord',
        ]);

        NotificationService::send(
            auth()->id(),
            $reservation->tenants->user->id,
            NotificationType::RESERVATION_CANCELED,
            'Reserva cancelada',
            $reservation->cancelation_reason,
            [
                'reservation_id' => $reservation->id,
                'store_room_id' => $reservation->store_room_id,
            ]
        );

        return response()->json([
            'message' => 'Reserva cancelada',
            'reservation' => $reservation->load(['storeRooms', 'tenants.user']),
        ]);
    }

    public function reservedDates($storeRoomId)
    {
        $ranges = Reservations::select('start_date', 'end_date')
            ->where('store_room_id', $storeRoomId)
            ->where('status', 'confirmed')
            ->orderBy('start_date')
            ->get();

        return response()->json($ranges);
    }

    public function tenantIndex(Request $request)
{
    $user = $request->user();
    $tenant = Tenants::where('user_id', $user->id)->firstOrFail();

    return Reservations::with('storeRooms')
        ->where('tenant_id', $tenant->id)
        ->orderBy('start_date')
        ->get();
}
}
