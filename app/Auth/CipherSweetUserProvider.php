<?php

namespace App\Auth;

use Illuminate\Auth\EloquentUserProvider;
use Illuminate\Contracts\Auth\Authenticatable;

class CipherSweetUserProvider extends EloquentUserProvider
{
    #[\Override]
    public function retrieveByCredentials(array $credentials): ?Authenticatable
    {
        if ($credentials === [] || ! isset($credentials['email'])) {
            return null;
        }

        $query = $this->newModelQuery();

        // Use CipherSweet blind index for email lookup
        $query->whereBlind('email', 'email_index', $credentials['email']);

        /** @var (\Illuminate\Contracts\Auth\Authenticatable&\Illuminate\Database\Eloquent\Model)|null */
        return $query->first();
    }
}
