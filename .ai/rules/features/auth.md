---
paths:
    - 'app/Actions/Auth/**'
    - 'app/Queries/Auth/**'
    - 'app/Actions/Fortify/**'
    - 'app/Models/OAuthClient.php'
    - 'app/Models/OAuthToken.php'
    - 'app/Providers/PassportServiceProvider.php'
---

# Auth

ShipKit is an OAuth 2.1 server built on Laravel Passport. Web sign-in stays session-based through Fortify; the API authenticates bearer tokens with Passport's `api` guard.

## Vocabulary

- **API user**: a user authenticating to the versioned JSON API with a bearer token.
- **Personal access token**: a token a user issues to themselves, through API login, registration, two-factor completion, `POST /api/v1/tokens`, or Settings → Security. It carries the `api` scope and expires after `passport.personal_access_token_lifetime_days` (90 by default). Its secret is shown once, by the response that creates it.
- **Device name**: the client-supplied label stored as a personal access token's name.
- **Personal access client**: the OAuth client Passport issues personal access tokens through, created by a migration in every database.
- **OAuth client**: an app that asks users for access, created by an operator with `php artisan passport:client` (`--public` for a client without a secret, which must use PKCE). An `OAuthClient` may use only the authorization-code, refresh-token, and personal-access grants.
- **Consent**: the `auth/oauth-authorize` screen where a signed-in user approves or denies an OAuth client's request, seeing the client, the scopes, and the account it will act as.
- **Connected app**: an OAuth client for which the user holds at least one non-revoked token. Its access tokens last an hour and its refresh tokens 30 days.
- **Scope**: a permission a token carries, listed in `App\Enums\ApiScope` with the description the consent screen shows. Every `/api/v1` route requires `api`; `/mcp` requires `mcp:use`. Personal access tokens carry both.
- **AI client**: an MCP client such as Claude or ChatGPT that finds the app through discovery (`/.well-known/oauth-*`), registers itself at `/oauth/register` (public, 10 a minute per address), and connects through the consent screen. A self-registered client is a public client limited to `mcp:use`, so it never reaches `/api/v1`.
- **Channel**: where a change came from (web, API, or MCP with the OAuth client), shared by `ShareChannel` and passed to Actions that record it. See the `mcp` rule.
- **Two-factor challenge**: a single-use, five-minute cache entry issued instead of a token when the user has two-factor authentication enabled.

## Invariants

| Rule                                                                                                                                                           | Enforced by                                                                                   |
| -------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- |
| Users with two-factor authentication get a challenge, never a token, at API login, and must pass two-factor sign-in before consenting to an app                | `LoginApiUserAction`; Fortify's login before `/oauth/authorize`                               |
| A challenge is processed under a lock, expires, and is consumed on success                                                                                     | `CompleteApiTwoFactorChallengeAction`                                                         |
| A used recovery code is replaced                                                                                                                               | `CompleteApiTwoFactorChallengeAction`                                                         |
| Registration creates the user and token in one transaction and sends verification after commit                                                                 | `RegisterApiUserAction`                                                                       |
| A password reset, on the web or the API, revokes every personal access token, every connected app's access and refresh tokens, and pending authorization codes | `ResetUserPassword` through `RevokeAllApiAccessAction`                                        |
| Deleting an account deletes its tokens, refresh tokens, and authorization codes                                                                                | foreign keys on the `oauth_*` tables                                                          |
| A user can list and revoke only their own personal access tokens and connected apps; another user's is not found                                               | `ApiTokenListQuery`, `ApiTokenQuery`, `ConnectedAppListQuery`, `ConnectedAppQuery`            |
| Disconnecting an app revokes only that app's tokens                                                                                                            | `RevokeConnectedAppAction`                                                                    |
| `/api/v1` accepts only tokens with the `api` scope                                                                                                             | `CheckToken` scope middleware on the route group                                              |
| `/mcp` accepts only tokens with `mcp:use`; a self-registered AI client can obtain only `mcp:use`                                                               | `CheckToken` on the MCP route; `LimitRegisteredClientsToMcp` through `LimitClientToMcpAction` |
| Only the authorization-code (PKCE for public clients), refresh-token, and personal-access grants are usable                                                    | `OAuthClient`'s grant filter and `PassportServiceProvider`                                    |
| Email verification requires the matching hash and is idempotent                                                                                                | `VerifyApiEmailAction`                                                                        |

These rules mirror Fortify's web behaviour and report failures with Laravel's validation and authorization exceptions, so they do not use `DomainException`.

## Gotchas

- Passport checks a token's expiry against the real clock, inside the signed token, so `travel()` does not expire one in a test. Issue a short-lived token instead.
- Approve and Deny on the consent screen are full-page form posts, not Inertia visits, because Passport redirects to the app's own URL.

## Actions and Queries

- Actions: `LoginApiUserAction`, `CompleteApiTwoFactorChallengeAction`, `RegisterApiUserAction`, `ResetApiPasswordAction`, `VerifyApiEmailAction`, `CreateApiTokenAction`, `RevokeApiTokenAction`, `RevokeConnectedAppAction`, `RevokeAllApiAccessAction`, `RecordApiTokenUseAction`, `LimitClientToMcpAction`.
- Queries: `ApiTokenListQuery`, `ApiTokenQuery`, `ConnectedAppListQuery`, `ConnectedAppQuery`.

Actions used only by other Auth Actions: `AuthenticateApiUserAction`, `CreateApiTwoFactorChallengeAction`.
