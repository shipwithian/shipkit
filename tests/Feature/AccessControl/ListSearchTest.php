<?php

use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

describe('users', function () {
    beforeEach(function () {
        $this->actor = userWithPermissions(['assign roles']);
        $this->barista = Role::create(['name' => 'barista', 'guard_name' => 'web']);

        User::factory()->create(['name' => 'Anaxquill Reyes', 'email' => 'anaxquill@cafe.test'])->assignRole($this->barista);
        User::factory()->create(['name' => 'Ben Cruz', 'email' => 'ben@cafe.test']);
        User::factory()->create(['name' => 'Cora Lim', 'email' => 'cora@guests.cafe.test'])->assignRole($this->barista);
    });

    it('lists everyone, with no filter applied', function () {
        $this->actingAs($this->actor)
            ->get(route('users.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', fn ($users) => collect(['Anaxquill Reyes', 'Ben Cruz', 'Cora Lim'])->diff(collect($users)->pluck('name'))->isEmpty())
                ->where('pagination.total', User::query()->count())
                ->where('filters', ['search' => null, 'role' => null]));
    });

    it('finds users by name or email, ignoring case', function (string $search, array $names) {
        $this->actingAs($this->actor)
            ->get(route('users.index', ['search' => $search]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', fn ($users) => collect($users)->pluck('name')->all() === $names)
                ->where('filters.search', $search));
    })->with([
        'part of a name' => ['ANAXQUILL', ['Anaxquill Reyes']],
        'part of an email' => ['guests.cafe', ['Cora Lim']],
        'nobody' => ['zzqxnobody', []],
    ]);

    it('narrows to the holders of one role, and combines with search', function () {
        $this->actingAs($this->actor)
            ->get(route('users.index', ['role' => $this->barista->id]))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', fn ($users) => collect($users)->pluck('name')->all() === ['Anaxquill Reyes', 'Cora Lim'])
                ->where('filters.role', $this->barista->id));

        $this->actingAs($this->actor)
            ->get(route('users.index', ['role' => $this->barista->id, 'search' => 'cora']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', fn ($users) => collect($users)->pluck('name')->all() === ['Cora Lim']));
    });

    it('treats a wildcard in the search as text', function () {
        User::factory()->create(['name' => '100% Arabica', 'email' => 'beans@cafe.test']);

        $this->actingAs($this->actor)
            ->get(route('users.index', ['search' => '%']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('users', fn ($users) => collect($users)->pluck('name')->all() === ['100% Arabica']));
    });

    it('keeps the filters on the pagination links', function () {
        User::factory()->count(30)->create(['name' => 'Regular Customer']);

        $this->actingAs($this->actor)
            ->get(route('users.index', ['search' => 'regular']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('pagination.total', 30)
                ->where('pagination.next_page_url', fn (string $url) => str_contains($url, 'search=regular')));
    });

    it('rejects a filter it cannot use', function (array $query, string $field) {
        $this->actingAs($this->actor)
            ->from(route('users.index'))
            ->get(route('users.index', $query))
            ->assertRedirect(route('users.index'))
            ->assertSessionHasErrors($field);
    })->with([
        'a search too long to mean anything' => [['search' => str_repeat('a', 101)], 'search'],
        'a role that does not exist' => [['role' => 999999], 'role'],
    ]);
});

describe('roles', function () {
    it('finds roles by name', function () {
        Role::create(['name' => 'barista', 'guard_name' => 'web']);
        Role::create(['name' => 'cashier', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['manage roles resource']))
            ->get(route('roles.index', ['search' => 'BARI']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('roles', fn ($roles) => collect($roles)->pluck('name')->all() === ['barista'])
                ->where('filters', ['search' => 'BARI']));
    });
});

describe('permissions', function () {
    it('finds permissions by name', function () {
        Permission::create(['name' => 'edit menu', 'guard_name' => 'web']);
        Permission::create(['name' => 'refund orders', 'guard_name' => 'web']);

        $this->actingAs(userWithPermissions(['manage permissions resource']))
            ->get(route('permissions.index', ['search' => 'menu']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('permissions', fn ($permissions) => collect($permissions)->pluck('name')->all() === ['edit menu'])
                ->where('filters', ['search' => 'menu']));
    });
});
