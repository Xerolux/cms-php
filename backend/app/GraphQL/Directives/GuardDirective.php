<?php

namespace App\GraphQL\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\GuardDirective as GuardDirectiveContract;

class GuardDirective extends BaseDirective implements GuardDirectiveContract
{
    /**
     * Name of the directive.
     */
    public static function name(): string
    {
        return 'guard';
    }

    /**
     * Resolve the field with the guard directive applied.
     */
    public function handle($root, $args, $context, $info)
    {
        $guards = $this->directiveArgValue('with') ?? ['api'];

        $user = auth($guards[0])->user();

        if (!$user) {
            throw new \Exception('Authentication required.');
        }

        // Check if user is active
        if (isset($user->is_active) && !$user->is_active) {
            throw new \Exception('User account is inactive.');
        }

        return $root;
    }
}
