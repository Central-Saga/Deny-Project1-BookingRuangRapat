<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\User;

class AdminUserController extends Controller
{
    public function index()
    {
        abort_unless(request()->user()->role === 'admin', 403, 'Hanya admin yang dapat mengelola pengguna.');

        $pagination = request()->validate([
            'page' => ['sometimes', 'integer', 'min:1'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        $users = User::query()->latest()->orderByDesc('id')->paginate(
            (int) ($pagination['per_page'] ?? 25),
            ['id', 'name', 'email', 'role', 'status', 'created_at']
        );

        return response()->json([
            'success' => true,
            'status' => true,
            'message' => 'Data pengguna berhasil dimuat.',
            'data' => $users->items(),
            'errors' => [],
            'meta' => [
                'current_page' => $users->currentPage(),
                'last_page' => $users->lastPage(),
                'per_page' => $users->perPage(),
                'total' => $users->total(),
            ],
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        $user = User::create($request->validated());

        return response()->json(['data' => $user->only(['id', 'name', 'email', 'role', 'status', 'created_at'])], 201);
    }

    public function update(UpdateUserRequest $request, User $user)
    {
        abort_unless($user->id !== $request->user()->id, 422, 'Akun admin yang sedang digunakan tidak dapat diubah.');

        $user->update($request->validated());

        if ($user->status === 'inactive') $user->tokens()->delete();

        return response()->json(['data' => $user->fresh()->only(['id', 'name', 'email', 'role', 'status', 'created_at'])]);
    }
}