<?php

use App\Domain\AccessControl\Enums\SystemPermission;

it('reserves SystemPermission for permissions that keep access control recoverable', function () {
    $reserved = [
        'manage roles resource' => 'create, rename, and delete roles',
        'manage permissions resource' => 'create, rename, and delete permissions',
        'assign permissions' => 'put permissions on roles',
    ];

    $actual = array_column(SystemPermission::cases(), 'value');

    expect($actual)->toEqualCanonicalizing(array_keys($reserved), 'SystemPermission is reserved for permissions without which nobody could recover or change who may do what (.ai/rules/enums.md). A feature permission, even an access-control one such as viewing an audit log, belongs in its feature seeder. If this one genuinely qualifies, add it to $reserved in this test with the reason.');
});
