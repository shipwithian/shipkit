# Context rule file format

Each bounded context's model lives in `.ai/rules/contexts/{context}.md`, scaffolded by `php artisan make:context`. Keep its three sections in this order.

## Vocabulary

```md
- **Role**: a named set of permissions assigned to users (`Models\Role`).
  _Avoid_: group, user type
- **Protected record**: a role, permission, or user with `is_protected = true`, created only by the seeder.
```

- **Be opinionated.** When multiple words exist for the same concept, pick the one the code uses and list the others under `_Avoid_`.
- **Keep definitions tight.** One sentence. Define what it IS, not what it does.
- **Only business terms.** General programming concepts (timeouts, error types, utility patterns) don't belong, even when the context uses them a lot.

## Invariants

```md
| Rule                                       | Enforced by                  | Exception     |
| ------------------------------------------ | ---------------------------- | ------------- |
| A role assigned to users cannot be deleted | `Role::ensureCanBeDeleted()` | `RoleIsInUse` |
```

- One row per rule that must hold for every caller, independent of who is asking.
- **Enforced by** names the `ensure...()` method (run by the model's `guardCreate()`/`guardUpdate()`/`guardDelete()`) or the Action that checks it.
- **Exception** names the domain exception it throws, or `—` when the rule is applied silently.

## Public surface

```md
- Actions: `CreateRoleAction`, `DeleteRoleAction`.
- Queries: `RoleListQuery`.
```

List every Action, Query, and domain exception class by its exact name; `ArchitectureTest` checks this.
