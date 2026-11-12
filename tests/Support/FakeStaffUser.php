<?php

namespace Dcplibrary\Notices\Tests\Support;

use Illuminate\Contracts\Auth\Authenticatable;

/**
 * Minimal Authenticatable used by package HTTP tests that render the
 * dcplibrary/ui staff layout (which expects auth()->user() to be an object).
 */
class FakeStaffUser implements Authenticatable
{
    public function __construct(
        public int $id = 1,
        public string $name = 'Test Admin',
        public string $email = 'admin@example.test',
        public string $role = 'admin',
    ) {
    }

    public function getAuthIdentifierName(): string
    {
        return 'id';
    }

    public function getAuthIdentifier(): mixed
    {
        return $this->id;
    }

    public function getAuthPassword(): string
    {
        return '';
    }

    public function getAuthPasswordName(): string
    {
        return 'password';
    }

    public function getRememberToken(): ?string
    {
        return null;
    }

    public function setRememberToken($value): void
    {
    }

    public function getRememberTokenName(): string
    {
        return 'remember_token';
    }

    public function isAdmin(): bool
    {
        return 'admin' === $this->role;
    }
}
