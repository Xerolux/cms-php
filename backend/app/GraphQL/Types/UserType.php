<?php

namespace App\GraphQL\Types;

use App\Models\User;

class UserType
{
    /**
     * Check if user has two-factor authentication enabled
     */
    public function hasTwoFactorEnabled($root): bool
    {
        return !is_null($root->two_factor_secret)
            && !is_null($root->two_factor_confirmed_at);
    }
}
