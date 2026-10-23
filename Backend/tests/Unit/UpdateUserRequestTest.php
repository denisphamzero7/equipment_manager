<?php

namespace Tests\Unit;

use App\Modules\Core\Models\User;
use App\Modules\Core\Requests\UpdateUserRequest;
use PHPUnit\Framework\TestCase;

class UpdateUserRequestTest extends TestCase
{
    public function test_unique_rules_ignore_the_user_being_updated(): void
    {
        $user = new User;
        $user->setAttribute('id', 42);

        $request = new UpdateUserRequest;
        $request->setRouteResolver(fn () => new class($user)
        {
            public function __construct(private User $user) {}

            public function parameter(string $key, mixed $default = null): mixed
            {
                return $key === 'user' ? $this->user : $default;
            }
        });

        $rules = $request->rules();

        $this->assertSame('unique:users,email,42,id', (string) $rules['email'][2]);
        $this->assertSame('unique:users,user_name,42,id', (string) $rules['user_name'][4]);
    }
}
