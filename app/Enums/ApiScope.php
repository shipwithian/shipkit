<?php

namespace App\Enums;

/**
 * The scopes an access token can carry. Each case is shown on the OAuth consent screen with its
 * description, so add a case here to offer a new scope.
 */
enum ApiScope: string
{
    case Api = 'api';

    public function description(): string
    {
        return match ($this) {
            self::Api => __('Use the API as you: read your account and manage your API tokens.'),
        };
    }

    /**
     * Every scope with its description, as Passport's `tokensCan()` expects.
     *
     * @return array<string, string>
     */
    public static function descriptions(): array
    {
        $descriptions = [];

        foreach (self::cases() as $scope) {
            $descriptions[$scope->value] = $scope->description();
        }

        return $descriptions;
    }
}
