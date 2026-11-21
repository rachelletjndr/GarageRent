<?php

namespace App\Actions\Fortify;

use App\Http\Requests\RegisterTenantRequest;
use App\Models\User;
use App\Services\AuthService;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    public function __construct(private AuthService $authService) {}

    /**
     * Validate and create a newly registered tenant user (FR-01).
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        /** @var array{nik: string, name: string, no_telp: string, email: string, password: string} $validated */
        $validated = Validator::make(
            $input,
            RegisterTenantRequest::rulesForRegistration(),
            RegisterTenantRequest::messagesForRegistration(),
        )->validate();

        return $this->authService->registerTenant($validated);
    }
}
