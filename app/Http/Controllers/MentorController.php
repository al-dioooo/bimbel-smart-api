<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreMentorRequest;
use App\Http\Requests\UpdateMentorRequest;
use App\Models\Mentor;
use Illuminate\Http\Request;

class MentorController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $data = Mentor::filter($request->only(['search', 'kontak']))->latest()->get();

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
        //
    }

    /**
     * Display the specified resource.
     */
    public function show(Mentor $mentor)
    {
        //
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(UpdateMentorRequest $request, Mentor $mentor)
    {
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Mentor $mentor)
    {
        //
    }
}
