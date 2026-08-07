<?php

namespace App\Http\Controllers;

use App\Enums\NotificationType;
use App\Models\Reports;
use App\Models\StoreRooms;
use App\Models\User;
use App\Services\NotificationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class ReportsController extends ApiController
{
    //
    public function index()
    {
        return Reports::with(['user', 'store', 'evidences'])->get();
    }


    public function show($id)
{
    $report = Reports::with(['user', 'store', 'evidences'])->findOrFail($id);
    return response()->json(['report' => $report]);
}


    public function store(Request $request)
    {
        $request->validate([
            'store_id' => 'required|exists:storeRooms,id',
            'title' => 'required|string|max:255',
            'report_type' => 'required|string|max:255',
            'priority' => 'required|in:low,medium,high',
            'description' => 'required|string|min:20',
            'files.*' => 'file|max:10240',
        ]);

        $report = Reports::create([
            'user_id' => auth()->user()?->id,
            'store_id' => $request->store_id,
            'title' => $request->title,
            'priority' => $request->priority,
            'report_type' => $request->report_type,
            'description' => $request->description,
            'status' => 'pending',
            'cancelation_reason' => null,
        ]);

        if ($request->hasFile('files')) {
            foreach ($request->file('files') as $file) {
                $path = $file->store('reports', 'public');

                $report->evidences()->create([
                    'file_path' => $path,
                    'file_type' => $file->getClientMimeType(),
                ]);
            }
        }

        $admins = User::where('role', 'admin')->get();

        foreach ($admins as $admin) {
            NotificationService::send(
                auth()->id(),
                $admin->id,
                NotificationType::STORE_REPORTED,
                'Nuevo Reporte Creado',
                $report->title,
                [
                    'report_id' => $report->id,
                    'store_id' => $report->store_id,
                    'priority' => $report->priority,
                ]
            );
        }

        $store = StoreRooms::with('landlord.user')->find($request->store_id);

        if ($store && $store->landlord && $store->landlord->user) {
            $landlordUserId = $store->landlord->user->id;

            // Evitar duplicar si también es admin
            if (! $admins->contains('id', $landlordUserId)) {
                Log::info('Notificación a landlord', [
                    'to' => $landlordUserId,
                    'report_id' => $report->id,
                ]);

                NotificationService::send(
                    auth()->id(),
                    $landlordUserId,
                    NotificationType::STORE_REPORTED,
                    'Reporte en tu bodega',
                    $report->title,
                    [
                        'report_id' => $report->id,
                        'store_id' => $report->store_id,
                        'priority' => $report->priority,
                    ]
                );
            }
        }


        return response()->json([
            'message' => 'Reporte creado correctamente',
            'report' => $report
        ], 201);
    }


    public function update(Request $request, $id)
    {
        $rules = [
            'store_id' => 'sometimes|exists:storeRooms,id',
            'reported_user_id' => 'nullable|exists:users,id',
            'title' => 'sometimes|string|max:255',
            'report_type' => 'sometimes|string|max:255',
            'priority' => 'sometimes|in:low,medium,high',
            'description' => 'sometimes|string|min:20',
            'status' => 'sometimes|in:pending,confirmed,canceled, in_review',
            'cancelation_reason' => 'nullable|string|max:1000',
        ];

        return $this->updateModel($request, Reports::class, $id, $rules);
    }

    public function updateStatus(Request $request, Reports $report)
    {
        if ($request->user()->role !== 'admin') {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'status' => ['required', 'in:resolved,canceled'],
            'cancelation_reason' => ['nullable', 'string', 'max:1000'],
        ]);

        if ($data['status'] === 'resolved') {
            $report->update([
                'status' => 'resolved',
                'cancelation_reason' => null,
            ]);

            return response()->json([
                'message' => 'Reporte resuelto',
                'report' => $report->load(['user', 'store', 'evidences']),
            ]);
        }

        $report->update([
            'status' => 'canceled',
            'cancelation_reason' => $data['cancelation_reason'] ?? 'Canceled by admin',
        ]);

        return response()->json([
            'message' => 'Reporte cancelado',
            'report' => $report->load(['user', 'store', 'evidences']),
        ]);
    }

    public function destroy($id)
    {
        return $this->destroyModel(Reports::class, $id);
    }
}
