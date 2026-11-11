---
paths:
    - 'app/Mcp/**'
    - 'routes/ai.php'
---

# MCP tools

An MCP tool lets an AI client (Claude, ChatGPT, Codex) act as the signed-in user. A tool is delivery code, like a controller: it authorizes, validates, calls one Query or Action, and returns structured content. `vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php` checks every rule below except where a rule names another check.

## Put each tool in its feature's folder

A tool is `app/Mcp/Tools/{Feature}/{Name}Tool.php`, named after what it does (`ShowMyProfileTool`), with a snake_case `#[Name]` such as `show_my_profile` and a short `#[Title]`. Register it in the server under `app/Mcp/Servers`. Shared tool behaviour lives in `app/Mcp/Concerns`. Create a feature's tool folder with `php artisan make:feature {Name} --mcp`, or by hand when its first tool arrives.

## Authorize first, then read or write through the domain

In `handle()`, the first thing a tool does is `Gate::forUser($user)->authorize(...)` against the same policy the web app uses, with `$user = $request->user()`. Plain `Gate::authorize()` checks the web guard, which an MCP request does not use. Then validate the arguments with the same rules the matching Form Request uses (share them through a trait such as `App\Concerns\ProfileValidationRules`), with messages an AI client can act on: say what to send, with an example. Then call one Query object to read or one Action to write. A tool never uses `DB` or queries a model, and never takes the acting user from its arguments.

## Describe the tool and its result

Declare `schema()` for the input and `outputSchema()` for the result, and return `Response::structured()` listing every exposed field one by one, never a whole model, a secret, or another user's data. Annotate honestly: `#[IsReadOnly]` for a tool that changes nothing, and `#[IsIdempotent]` when repeating the call has no further effect.

## Report broken rules as `field: message`

Laravel MCP turns any exception other than validation and authorization into "An internal server error occurred.". Run the tool's work through `ReportsDomainExceptions::reportingDomainExceptions()`, so a `DomainException` reaches the AI client as `{field}: {message}` and the client can explain it.

## Confirm consequential actions

A tool is **consequential** when its effect costs money, cannot be undone, or affects someone other than the actor: placing an order, charging a card, cancelling a booking, deleting a record. Mark it `#[IsDestructive]`, so AI clients ask the user before calling it, type-hint `App\Mcp\Concerns\ConsequentialActionConfirmation` in `handle()`, and run its action through `confirmBeforeActing()`:

- The first call returns a `summary` and a `confirmation_token` and changes nothing.
- The call with the token acts. The token lasts ten minutes, is bound to the tool, the exact arguments, and the user, and is single-use.
- Repeating the confirmed call returns the first result without acting again, so a retry never orders twice. The token is the idempotency key.

The token proves the model saw this exact summary; the human gate is the AI client's own approval prompt for destructive tools. The check fails on a `#[IsDestructive]` tool that does not run its action through `ConsequentialActionConfirmation::confirmBeforeActing()`.

## Record which client acted

`App\Http\Middleware\ShareChannel` puts the request's `ShipKit\Core\Channel` (web, API, or MCP with the OAuth client's id and name) into Laravel's `Context`, so logs and queued jobs carry it. When a feature needs to know where a change came from, such as an order placed by a customer's Claude, the tool passes `ShareChannel::current()` to the Action as an explicit parameter after `$actor`, and the Action stores it on its own record. Actions never read the request or the context to find it.

## Confirm one risky argument

A tool need not be destructive as a whole to confirm. When only some calls are consequential, such as `update_my_profile` changing the email while a name change is harmless, run only that branch through `ConsequentialActionConfirmation::confirmBeforeActing()` and apply the rest directly. Declare `confirmation_token` in the input schema, and let the output schema describe both the result and the confirmation fields.

## Redirects and client identity

An AI client registers itself, so anything it declares is unverified. `config/mcp.php` limits where a client may send the user after approval to `https://claude.ai`, `https://chatgpt.com`, and localhost, plus `MCP_REDIRECT_DOMAINS` (comma-separated, with scheme); the consent screen shows that destination. Trust a channel's `client_id`, never its `client_name`, which is whatever the client called itself.

## Scopes

`/mcp` requires the `mcp:use` scope. A self-registered AI client is limited to `mcp:use`, so it never reaches `/api/v1`; a personal access token carries both `api` and `mcp:use`. Add a scope to `App\Enums\ApiScope` with a description the consent screen shows.
