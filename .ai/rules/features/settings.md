---
paths:
    - 'app/Actions/Settings/**'
    - 'app/Queries/Settings/**'
    - 'app/Policies/UserPolicy.php'
---

# Settings

## Vocabulary

- **Account**: the authenticated user's own `App\Models\User` record, managed through the settings pages.
- **Protected account**: a user with `is_protected = true`, such as the seeded super admin.
- **Passkey**: a WebAuthn credential registered to the account.

## Invariants

| Rule                                       | Enforced by                                                | Exception                         |
| ------------------------------------------ | ---------------------------------------------------------- | --------------------------------- |
| A protected account cannot be deleted      | `User::ensureCanBeDeleted()`, run by `User::guardDelete()` | `ProtectedAccountCannotBeDeleted` |
| Changing the email clears its verification | `UpdateProfileAction`                                      | —                                 |

`UserPolicy` restricts every ability to the account owner; Laravel discovers it for `User` by name.

## Actions and Queries

- Actions: `UpdateProfileAction`, `UpdatePasswordAction`, `DeleteAccountAction`.
- Queries: `PasskeyListQuery`.
