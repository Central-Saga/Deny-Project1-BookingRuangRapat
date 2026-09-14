<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;

class AdminUserController extends Controller
{
    public function index()
    {
        abort_unless(request()->user()->role === 'admin', 403, 'Hanya admin yang dapat mengelola pengguna.');

        return response()->json(['data' => User::query()->latest()->get(['id', 'name', 'email', 'role', 'status', 'created_at'])]);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        abort_unless($user->id !== $request->user()->id, 422, 'Akun admin yang sedang digunakan tidak dapat diubah.');

        $user->update($request->validated());

        if ($user->status === 'inactive') $user->tokens()->delete();

        return response()->json(['data' => $user->fresh()->only(['id', 'name', 'email', 'role', 'status', 'created_at'])]);
    }
}