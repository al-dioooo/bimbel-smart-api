<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNotificationRequest;
use App\Http\Requests\UpdateNotificationRequest;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $filters = $request->only(['user_id', 'is_read']);

        // Mentors only ever see their own.
        if (! $request->user()->isAdmin()) {
            $filters['user_id'] = $request->user()->id;
        }

        $query = Notification::filter($filters);
        $data = $query->get();

        return response()->json([
            'message' => 'Data notifikasi berhasil diambil.',
            'data' => $data,
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreNotificationRequest $request)
    {
        DB::beginTransaction();

        try {
            $notification = Notification::create($request->validated());

            DB::commit();

            return response()->json([
                'message' => 'Data notifikasi berhasil ditambahkan.',
                'data' => $notification,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->serverError('Data notifikasi gagal disimpan. Silakan coba lagi.', $e);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Request $request, Notification $notification)
    {
        $this->authorizeRecipient($request, $notification);

        return response()->json([
            'message' => 'Data notifikasi berhasil diambil.',
            'data' => $notification,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateNotificationRequest $request, Notification $notification)
    {
        $this->authorizeRecipient($request, $notification);

        // A recipient can mark it read; only admins may rewrite the content.
        $validated = $request->user()->isAdmin()
            ? $request->validated()
            : Arr::only($request->validated(), ['is_read']);

        DB::beginTransaction();

        try {
            $notification->update($validated);

            DB::commit();

            return response()->json([
                'message' => 'Data notifikasi berhasil diperbarui.',
                'data' => $notification,
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->serverError('Data notifikasi gagal diperbarui. Silakan coba lagi.', $e);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Request $request, Notification $notification)
    {
        $this->authorizeRecipient($request, $notification);

        DB::beginTransaction();

        try {
            $notification->delete();

            DB::commit();

            return response()->json([
                'message' => 'Data notifikasi berhasil dihapus.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->serverError('Data notifikasi gagal dihapus. Silakan coba lagi.', $e);
        }
    }

    /** 403 unless the user is an admin or the notification's recipient. */
    private function authorizeRecipient(Request $request, Notification $notification): void
    {
        $user = $request->user();

        abort_unless(
            $user->isAdmin() || (int) $notification->user_id === (int) $user->id,
            403,
            'Anda tidak memiliki akses ke notifikasi ini.'
        );
    }
}
