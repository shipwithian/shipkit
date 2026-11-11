---
paths:
    - 'app/Actions/Settings/**'
    - 'app/Queries/Settings/**'
    - 'app/Models/User.php'
    - 'app/Policies/UserPolicy.php'
    - 'app/Mcp/Tools/Settings/**'
---

# Settings

## Vocabulary

- **Account**: the authenticated user's own `App\Models\User` record, managed through the settings pages.
- **Protected account**: a user with `is_protected = true`, such as the seeded super admin.
- **Passkey**: a WebAuthn credential registered to the account.
- **API access**: the Security page also lists the account's personal access tokens and connected apps, read through Auth's Query objects and changed through Auth's Actions. `.ai/rules/features/auth.md` defines both.

## Invariants

| Rule                                       | Enforced by                                                | Exception                         |
| ------------------------------------------ | ---------------------------------------------------------- | --------------------------------- |
| A protected account cannot be deleted      | `User::ensureCanBeDeleted()`, run by `User::guardDelete()` | `ProtectedAccountCannotBeDeleted` |
| Changing the email clears its verification | `UpdateProfileAction`                                      | —                                 |

`UserPolicy` restricts every ability to the account owner; Laravel discovers it for `User` by name.

## Actions and Queries

- Actions: `UpdateProfileAction`, `UpdatePasswordAction`, `DeleteAccountAction`.
- Queries: `PasskeyListQuery`.
- MCP tools: `ShowMyProfileTool` (`show_my_profile`, read-only) and `UpdateMyProfileTool` (`update_my_profile`, destructive and idempotent; a name change applies at once, an email change confirms first, naming every change in the summary, and then marks the email unverified, as the web form does). `UpdateProfileAction` logs the `Channel` the change came from.
