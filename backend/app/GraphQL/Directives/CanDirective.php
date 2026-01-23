<?php

namespace App\GraphQL\Directives;

use Nuwave\Lighthouse\Schema\Directives\BaseDirective;
use Nuwave\Lighthouse\Support\Contracts\DefinedDirective;

class CanDirective extends BaseDirective implements DefinedDirective
{
    /**
     * Name of the directive.
     */
    public static function name(): string
    {
        return 'can';
    }

    /**
     * Resolve the field with the permission directive applied.
     */
    public function handle($root, $args, $context, $info)
    {
        $ability = $this->directiveArgValue('ability');
        $modelClass = $this->directiveArgValue('model');

        $user = auth('api')->user();

        if (!$user) {
            throw new \Exception('Authentication required.');
        }

        // Check user role for simple permissions
        if ($ability === 'manage-posts') {
            if (!in_array($user->role, ['admin', 'editor'])) {
                throw new \Exception('You do not have permission to perform this action.');
            }
        } elseif ($ability === 'manage-users') {
            if ($user->role !== 'admin') {
                throw new \Exception('Only administrators can manage users.');
            }
        } elseif ($ability === 'publish-posts') {
            if (!in_array($user->role, ['admin', 'editor'])) {
                throw new \Exception('You do not have permission to publish posts.');
            }
        } elseif ($ability === 'moderate-comments') {
            if (!in_array($user->role, ['admin', 'editor'])) {
                throw new \Exception('You do not have permission to moderate comments.');
            }
        }

        return $root;
    }

    /**
     * Directive definition.
     */
    public static function definition(): string
    {
        return /** @lang GraphQL */ <<<'GRAPHQL'
"""
Check if the user has permission to perform an action.
"""
directive @can(
  """
  The ability to check.
  """
  ability: String!

  """
  The model class name.
  """
  model: String
) on FIELD_DEFINITION
GRAPHQL;
    }
}
