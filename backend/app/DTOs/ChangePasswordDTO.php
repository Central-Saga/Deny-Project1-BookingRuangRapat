<?php

namespace App\DTOs;

use Illuminate\Http\Request;

class ChangePasswordDTO
{
    public function __construct(
        public readonly string $currentPassword,
        public readonly string $newPassword,
        public readonly ?string $passwordConfirmation = null,
    ) {}

    public static function fromRequest(Request $request): self
    {
        return new self(
            currentPassword: (string) ($request->input('current_password') ?? $request->input('old_password') ?? ''),
            newPassword: (string) ($request->input('password') ?? $request->input('new_password') ?? ''),
            passwordConfirmation: $request->input('password_confirmation') ?? $request->input('new_password_confirmation'),
        );
    }

    public static function fromArray(array $data): self
    {
        return new self(
            currentPassword: (string) ($data['current_password'] ?? $data['old_password'] ?? ''),
            newPassword: (string) ($data['password'] ?? $data['new_password'] ?? ''),
            passwordConfirmation: $data['password_confirmation'] ?? $data['new_password_confirmation'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'current_password' => $this->currentPassword,
            'password' => $this->newPassword,
            'password_confirmation' => $this->passwordConfirmation,
        ];
    }
}
