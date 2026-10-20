---
paths:
    - 'app/Domain/*/Policies/**'
---

# Policies

## Authorize exclusively through permissions

Policies map abilities to `$user->can()` checks. Do not authorize by role name and do not add a Super Admin or other role-based bypass. The only exceptions are the two record-based checks below: the user's own account, and a record the actor owns or belongs to.

## Separate resource and workflow permissions

Use `manage {plural resource} resource` for standard resource CRUD abilities. Give non-CRUD policy abilities their own verb permissions, with only the minimum supporting read access they require.

## Allow identity checks for self-service abilities

Abilities that act on the authenticated user's own account, such as viewing or updating their profile, may compare identity with `$user->is($model)` instead of a permission.

## Allow ownership and membership checks for record-scoped abilities

Abilities that depend on the actor's relationship to one record may check that relationship through a method on the record's model: owning it (`$note->isOwnedBy($user)` for a user's private note) or belonging to it (`$team->hasOwner($user)` for a team owner managing that team's members). Use this only when a global permission cannot express the rule because the answer differs per record. Abilities that any authorized user may perform, such as creating a team, return `true` or check a permission; every other ability stays permission-based.

## Hide records the actor may not know about

When a record's existence is private to its members or tenant, deny outsiders with `Response::denyAsNotFound()` so they receive a 404 and cannot probe for it. A private, user-owned resource follows this pattern: `viewAny` and `create` return `true` for any authenticated user, and `view`, `update`, and `delete` return `Response::denyAsNotFound()` unless the actor owns the record. Use the default 403 when the actor may know the record exists but may not act on it, such as an administrator resource without the needed permission, or a member who is not an owner.

## Hand the UI the abilities the controller authorizes

A page with a gated control receives each ability as a boolean computed with `Gate::allows()` against the same policy ability, and the same model or class, that the controller authorizes for that control: `Gate::allows('update', $role)` for a row's edit button, `Gate::allows('create', Role::class)` for the page's create button. The policy stays the only place the rule lives; never recompute it from permission names in the controller or the UI.
