<?php

namespace Devdabour\LaravelLoggable\Tests;

use Illuminate\Auth\GenericUser;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Auth\UserProvider;

class TestUserProvider implements UserProvider
{
    protected $user;

    public function __construct(Authenticatable $user = null)
    {
        $this->user = $user ?? new GenericUser([
            'id' => 1,
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);
    }

    public function retrieveById($identifier)
    {
        return $this->user;
    }

    public function retrieveByToken($identifier, $token)
    {
        return $this->user;
    }

    public function updateRememberToken(Authenticatable $user, $token)
    {
        // Do nothing for testing
    }

    public function retrieveByCredentials(array $credentials)
    {
        return $this->user;
    }

    public function validateCredentials(Authenticatable $user, array $credentials)
    {
        return true;
    }
}
