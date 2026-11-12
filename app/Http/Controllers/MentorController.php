<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMentorRequest;
use App\Http\Requests\UpdateMentorRequest;
use App\Models\Kelas;
use App\Models\Mentor;
use App\Models\Notification;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

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
        $data = $this->paginate($query, $request->query('limit') ?? 10, $request->query('paginate'), $request->query('order_by') ?? 'created_at', $request->query('direction') ?? 'desc');

        return response()->json([
            'message' => 'Data mentor berhasil diambil.',
            'data' => $data,
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
                'password' => bcrypt($request->input('password')),
            ]);

            $mentor = Mentor::create([
                'user_id' => $user->id,
                'kontak' => $request->input('kontak'),
                'tempat_tanggal_lahir' => $request->input('tempat_tanggal_lahir'),
                'nik' => $request->input('nik'),
                'npwp' => $request->input('npwp'),
                'alamat' => $request->input('alamat'),
            ]);

            DB::commit();

            return response()->json([
                'message' => 'Data mentor berhasil ditambahkan.',
                'data' => $mentor,
            ], 201);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->serverError('Data mentor gagal disimpan. Silakan coba lagi.', $e);
        }
    }

    /**
     * Display the specified resource.
     */
    public function show(Mentor $mentor)
    {
        $mentor->load(['user']);

        return response()->json([
            'message' => 'Data mentor berhasil diambil.',
            'data' => $mentor,
        ]);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMentorRequest $request, Mentor $mentor)
    {
        DB::beginTransaction();

        try {
            // REQ-024: change only what was sent. `input()` turned every absent
            // field into null, which broke NOT NULL account columns (500) and
            // silently wiped the profile (D15, G9, G10).
            $validated = $request->validated();

            $userData = Arr::only($validated, ['name', 'email', 'username']);
            if ($request->filled('password')) {
                $userData['password'] = Hash::make($request->input('password'));
            }

            if ($userData !== []) {
                $mentor->user->update($userData);
            }

            $profile = Arr::only($validated, ['kontak', 'tempat_tanggal_lahir', 'nik', 'npwp', 'alamat']);
            if ($profile !== []) {
                $mentor->update($profile);
            }

            DB::commit();

            return response()->json([
                'message' => 'Data mentor berhasil diperbarui.',
                'data' => $mentor->load('user'),
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->serverError('Data mentor gagal diperbarui. Silakan coba lagi.', $e);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Mentor $mentor)
    {
        // REQ-029: salary is attributed through kelas.mentor_id, so a mentor who
        // still teaches cannot go; their kelas must be reassigned first.
        $kelas = Kelas::where('mentor_id', $mentor->id)->orderBy('nama')->pluck('nama');
        if ($kelas->isNotEmpty()) {
            throw ValidationException::withMessages([
                'mentor' => 'Mentor tidak dapat dihapus karena masih mengampu kelas: '.$kelas->implode(', ').'. Pindahkan kelas tersebut ke mentor lain terlebih dahulu.',
            ]);
        }

        DB::beginTransaction();

        try {
            // Remove the mentor and its user explicitly. Relying on the users ->
            // mentor cascade left the order up to the driver, and failed on a
            // foreign key under SQLite.
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
                'message' => 'Data mentor berhasil dihapus.',
            ], 200);
        } catch (\Throwable $e) {
            DB::rollBack();

            return $this->serverError('Data mentor gagal dihapus. Silakan coba lagi.', $e);
        }
    }
}
