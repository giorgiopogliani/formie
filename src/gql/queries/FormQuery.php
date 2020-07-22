<?php
namespace verbb\formie\gql\queries;

use verbb\formie\gql\arguments\FormArguments;
use verbb\formie\gql\interfaces\FormInterface;
use verbb\formie\gql\resolvers\FormResolver;

use craft\gql\base\Query;

use GraphQL\Type\Definition\Type;

class FormQuery extends Query
{
    // Public Methods
    // =========================================================================

    public static function getQueries($checkToken = true): array
    {
        return [
            'formieForms' => [
                'type' => Type::listOf(FormInterface::getType()),
                'args' => FormArguments::getArguments(),
                'resolve' => FormResolver::class . '::resolve',
                'description' => 'This query is used to query for forms.',
            ],
            'formieForm' => [
                'type' => FormInterface::getType(),
                'args' => FormArguments::getArguments(),
                'resolve' => FormResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a single form.'
            ],
            'forms' => [
                'type' => Type::listOf(FormInterface::getType()),
                'args' => FormArguments::getArguments(),
                'resolve' => FormResolver::class . '::resolve',
                'description' => 'This query is used to query for forms.',
            ],
            'form' => [
                'type' => FormInterface::getType(),
                'args' => FormArguments::getArguments(),
                'resolve' => FormResolver::class . '::resolveOne',
                'description' => 'This query is used to query for a single form.'
            ],
        ];
    }
}