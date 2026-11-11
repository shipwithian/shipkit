<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Laravel\Passport\Client;

/**
 * An OAuth client: an application a user can connect, or the personal access client that issues
 * personal access tokens.
 *
 * Passport always enables the client-credentials grant and enables the device grant by default.
 * This model limits every client to the grants ShipKit supports, whatever its `grant_types`
 * column says, so the others are refused for every client.
 */
class OAuthClient extends Client
{
    /**
     * The grants a client may use.
     *
     * @var list<string>
     */
    public const array SUPPORTED_GRANTS = ['authorization_code', 'refresh_token', 'personal_access'];

    /**
     * @return Attribute<list<string>, never>
     */
    protected function grantTypes(): Attribute
    {
        return Attribute::get(function (?string $value): array {
            $grants = is_string($value) ? json_decode($value, true) : [];
            $grants = array_filter(is_array($grants) ? $grants : [], is_string(...));

            return array_values(array_intersect($grants, self::SUPPORTED_GRANTS));
        });
    }
}
