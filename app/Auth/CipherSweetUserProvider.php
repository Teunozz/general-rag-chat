<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class CipherSweetUserProvider extends EloquentUserProvider
{
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if (empty($credentials) || ! isset($credentials['email'])) {
            return null;
        }

        $query = $this->newModelQuery();

        // Use CipherSweet blind index for email lookup
        $query->whereBlind('email', 'email_index', $credentials['email']);

        return $query->first();
    }
}
