# Feature map

One file per user-facing feature. Each answers what the feature is, how a user reaches it, how to drive it with `scripts/drive.sh`, and what end state proves it works. Add a file when a feature ships, and keep its journeys runnable: a journey that fails because seed data lacks a precondition reports the failure and records the reason under Gotchas, rather than being weakened to pass.

| Feature                                     | Context        | Entry points                                      |
| ------------------------------------------- | -------------- | ------------------------------------------------- |
| [Roles](roles.md)                           | AccessControl  | `/roles`, `/roles/{role}/permissions`             |
| [Permissions](permissions.md)               | AccessControl  | `/permissions`                                    |
| [Account settings](account-settings.md)     | Settings       | `/settings/profile`, `/settings/security`         |
| [API authentication](api-authentication.md) | Authentication | `/api/v1/login`, `/api/v1/user`, `/api/v1/logout` |
