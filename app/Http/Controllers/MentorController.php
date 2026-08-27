<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMentorRequest;
use App\Http\Requests\UpdateMentorRequest;
use App\Models\Kelas;
use App\Models\Mentor;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

class MentorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Mentor::with(['user'])
            ->join('users', 'mentor.user_id', '=', 'users.id')->select(['mentor.*', 'users.name as user_name'])
            ->filter($request->only(['search', 'nama', 'kontak']));
        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), $request->query('order_by') ?? "created_at", $request->query('direction') ?? "desc");

        return response()->json([
            'message' => 'Successfully get mentor data.',
            'data' => $data
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(StoreMentorRequest $request)
    {
        DB::beginTransaction();

        try {
            $user = User::create([
                'name' => $request->input('name'),
                'username' => $request->input('username'),
                'email' => $request->input('email'),
                'password' => bcrypt($request->input('password'))
            ]);

            $mentor = Mentor::create([
                'user_id' => $user->id,
                'kontak' => $request->input('kontak'),
                'tempat_tanggal_lahir' => $request->input('tempat_tanggal_lahir'),
                'nik' => $request->input('nik'),
                'npwp' => $request->input('npwp'),
                'alamat' => $request->input('alamat')
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Successfully store mentor data.',
                'data' => $mentor
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to store mentor data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Mentor $mentor)
    {
        $mentor->load(['user']);

        return response()->json([
            'message' => 'Successfully get mentor data.',
            'data' => $mentor
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMentorRequest $request, Mentor $mentor)
    {
        DB::beginTransaction();

        try {
            $user = $mentor->user;

            // Bangun payload user tanpa password dulu
            $userData = [
                'name'     => $request->input('name'),
                'email'    => $request->input('email'),
                'username' => $request->input('username'),
            ];

            // Hanya update password jika diisi
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->input('password'));
            }

            $user->update($userData);

            // Update Mentor profile
            $mentor->update([
                'kontak'               => $request->input('kontak'),
                'tempat_tanggal_lahir' => $request->input('tempat_tanggal_lahir'),
                'nik'                  => $request->input('nik'),
                'npwp'                 => $request->input('npwp'),
                'alamat'               => $request->input('alamat')
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Successfully update mentor data.',
                'data'    => $mentor->load('user'),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to update mentor data.',
                'error'   => $e->getMessage(),
            ], 500);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Mentor $mentor)
    {
        DB::beginTransaction();

        try {
            // Detach the mentor's classes, then remove the mentor and its user
            // explicitly. Relying on the users -> mentor cascade left the order
            // up to the driver, and failed on a foreign key under SQLite.
            Kelas::where('mentor_id', $mentor->id)->update(['mentor_id' => null]);

            $user = $mentor->user;
            $mentor->delete();

            if ($user) {
                // notifications.user_id is a RESTRICT foreign key, so the user
                // row cannot go while any notification still points at it.
                Notification::where('user_id', $user->id)->delete();
                $user->tokens()->delete();
                $user->delete();
            }

            DB::commit();

            return response()->json([
                'message' => 'Successfully delete mentor data.'
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return response()->json([
                'message' => 'Failed to delete mentor data.',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
