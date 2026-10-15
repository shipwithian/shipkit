---
paths:
    - 'app/Domain/Authentication/**'
---

# Authentication context

## Vocabulary

- **API user**: a user authenticating through the versioned JSON API with Sanctum tokens.
- **Device name**: the client-supplied label stored as the token name.
- **Token management**: an API user listing, creating, and revoking their own tokens; the secret is returned only by the create response.
- **Two-factor challenge**: a single-use, five-minute cache entry issued instead of a token when the user has two-factor authentication enabled.
- **API token**: a Sanctum personal access token with the `*` ability and the `sanctum.expiration` lifetime.

## Invariants

| Rule                                                                                                                      | Enforced by                           |
| ------------------------------------------------------------------------------------------------------------------------- | ------------------------------------- |
| Users with two-factor authentication get a challenge, never a token, at login                                             | `LoginApiUserAction`                  |
| A challenge is processed under a lock, expires, and is consumed on success                                                | `CompleteApiTwoFactorChallengeAction` |
| A used recovery code is replaced                                                                                          | `CompleteApiTwoFactorChallengeAction` |
| Registration creates the user and token in one transaction and sends verification after commit                            | `RegisterApiUserAction`               |
| A password reset revokes every API token                                                                                  | `ResetApiPasswordAction`              |
| A user can list and revoke only their own tokens, whichever device they were issued to; another user's token is not found | `ApiTokenListQuery`, `ApiTokenQuery`  |
| Email verification requires the matching hash and is idempotent                                                           | `VerifyApiEmailAction`                |

These rules mirror Fortify's web behaviour and report failures with Laravel's validation and authorization exceptions, so they do not use `DomainException`.

## Public surface

- Actions: `LoginApiUserAction`, `CompleteApiTwoFactorChallengeAction`, `RegisterApiUserAction`, `ResetApiPasswordAction`, `VerifyApiEmailAction`, `CreateApiTokenAction`, `RevokeApiTokenAction`.
- Queries: `ApiTokenListQuery`, `ApiTokenQuery`.

Internal actions, used only inside this context: `AuthenticateApiUserAction`, `CreateApiTwoFactorChallengeAction`.
