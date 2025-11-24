<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreNotificationRequest;
use App\Http\Requests\UpdateNotificationRequest;
use App\Models\Notification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class NotificationController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Notification::filter($request->only(['user_id', 'is_read']));
        $data = $query->get();

        return response()->json([
            'message' => 'Successfully get notification data.',
            'data' => $data
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
                'message' => 'Successfully store notification data.',
                'data' => $notification
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to store notification data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Notification $notification)
    {
        return response()->json([
            'message' => 'Successfully get notification data.',
            'data' => $notification
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateNotificationRequest $request, Notification $notification)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Notification $notification)
    {
        DB::beginTransaction();

        try {
            $notification->delete();

            DB::commit();

            return response()->json([
                'message' => 'Successfully delete not$notification data.'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete not$notification data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
