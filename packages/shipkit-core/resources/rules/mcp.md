---
paths:
    - 'app/Mcp/**'
    - 'routes/ai.php'
---

# MCP tools

An MCP tool lets an AI client (Claude, ChatGPT, Codex) act as the signed-in user. A tool is delivery code, like a controller: it authorizes, validates, calls one Query or Action, and returns structured content.

## What the architecture check covers

`vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php` ("follows the MCP tool conventions" and "keeps each kind of class in its standard folder") fails on a class in `app/Mcp/Tools` that extends `Tool` and:

- sits outside `app/Mcp/Tools/{Feature}`, or is not named `{Name}Tool`;
- lacks a snake_case `#[Name]`, a `#[Title]`, or an explicit `#[IsReadOnly]` or `#[IsDestructive(true|false)]`;
- is not registered in a server under `app/Mcp/Servers`;
- does not call `Gate::forUser($user)->authorize()` before anything but `$user = $request->user();`;
- uses `DB`, or queries a model by import or by its full name;
- declares no `outputSchema()`;
- is `#[IsDestructive]` without type-hinting `ConsequentialActionConfirmation` and calling `confirmBeforeActing()`.

It does not see these, so review them: validating with the shared rules and writing messages a client can act on, listing every result field one by one, using `ReportsDomainExceptions`, never taking the acting user from arguments, and an honest `#[IsIdempotent]`.

## Put each tool in its feature's folder

A tool is `app/Mcp/Tools/{Feature}/{Name}Tool.php`, named after what it does (`ShowMyProfileTool`), with a snake_case `#[Name]` such as `show_my_profile` and a short `#[Title]`. Register it in the server under `app/Mcp/Servers`. Shared tool behaviour lives in `app/Mcp/Concerns` (traits) and `app/Mcp/Support` (classes a tool type-hints, such as `ConsequentialActionConfirmation`). Create a feature's tool folder with `php artisan make:feature {Name} --mcp`, which also works on a feature that already exists, or by hand when its first tool arrives.

## Authorize first, then read or write through the domain

In `handle()`, the first thing a tool does is `Gate::forUser($user)->authorize(...)` against the same policy the web app uses, with `$user = $request->user()` as the only statement before it. Naming the user explicitly keeps the check from depending on which guard is the default. `Gate::inspect()` never throws, so it does not count. Then validate the arguments with the same rules the matching Form Request uses (share them through a trait such as `App\Concerns\ProfileValidationRules`), with messages an AI client can act on: say what to send, with an example. Then call one Query object to read or one Action to write. A tool never uses `DB` or queries a model, and never takes the acting user from its arguments.

## Describe the tool and its result

Declare `schema()` for the input and `outputSchema()` for the result, and return `Response::structured()` listing every exposed field one by one, never a whole model, a secret, or another user's data. Annotate explicitly, because MCP clients treat a tool that is not read-only and does not say otherwise as destructive: `#[IsReadOnly]` for a tool that changes nothing, `#[IsDestructive]` for a consequential one, `#[IsDestructive(false)]` for a harmless write, and `#[IsIdempotent]` when repeating the call has no further effect.

## Report broken rules as `field: message`

Laravel MCP turns any exception other than validation and authorization into "An internal server error occurred.". A tool that writes runs its work through `ReportsDomainExceptions::reportingDomainExceptions()`, so a `DomainException` reaches the AI client as `{field}: {message}` and the client can explain it. A read-only tool that cannot break a rule does not need it.

## Confirm consequential actions

A tool is **consequential** when its effect costs money, cannot be undone, or affects someone other than the actor: placing an order, charging a card, cancelling a booking, deleting a record. Mark it `#[IsDestructive]`, so AI clients ask the user before calling it, type-hint `App\Mcp\Support\ConsequentialActionConfirmation` in `handle()`, and run its action through `confirmBeforeActing()`:

- The first call returns a `summary` and a `confirmation_token` and changes nothing.
- The call with the token acts. The token lasts ten minutes, is bound to the tool, the exact arguments, and the user, and is single-use.
- Repeating the confirmed call returns the first result without acting again, so a retry never orders twice. The token is the idempotency key. A repeat that arrives while the first is still running answers "still being processed"; the lock lasts at most two minutes, so a crashed worker cannot block its token for longer.

The token proves the model saw this exact summary; the human gate is the AI client's own approval prompt for destructive tools. Single use needs a cache every worker shares and that does not evict early (database or Redis), because the lock and the first result live there.

## Confirm one risky argument

A tool need not confirm every call. When only some calls are consequential, such as `update_my_profile` changing the email while a name change is harmless, run only that branch through `confirmBeforeActing()` and apply the rest directly. The tool is still `#[IsDestructive]`, because a client cannot tell which calls confirm. Declare `confirmation_token` in the input schema, let the output schema describe both the result and the confirmation fields, and make the summary name every change the confirmed call will make.

## Record which client acted

`App\Http\Middleware\ShareChannel` puts the request's `ShipKit\Core\Channel` into Laravel's `Context` (kind and OAuth client id), so every log line records where a change came from. A controller or tool type-hints `Channel` to receive it, with the client's name filled in, and passes it to the Action as an explicit parameter after the subject, as `UpdateProfileAction` does. The Action records it, in a log or on its own record; Actions never read the request or the context to find it. Trust the `client_id`, never the `client_name`: the name is whatever the client called itself when it registered.

## Redirects and client identity

An AI client registers itself, so anything it declares is unverified. `config/mcp.php` limits where a client may send the user after approval to `https://claude.ai`, `https://chatgpt.com`, and localhost, plus `MCP_REDIRECT_DOMAINS` (comma-separated, with scheme); the consent screen shows that destination. A `*` in `MCP_REDIRECT_DOMAINS` is ignored, because it would let any site register as a trusted client and collect consent.

## Scopes

`/mcp` requires the `mcp:use` scope. A self-registered AI client is limited to `mcp:use`, so it never reaches `/api/v1`; a personal access token carries both `api` and `mcp:use`. Add a scope to `App\Enums\ApiScope` with a description the consent screen shows.
