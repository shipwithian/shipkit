# Upgrade notes

From 6.0, `php artisan shipkit:upgrade` brings a release's template changes into a project's own files as a three-way merge: it reports first, and `--apply` writes the result without committing. The sections below keep only what files cannot carry (keys, environment variables, data) and the checks a major release tightens. A project created before 6.0 passes `--from=<the release it was created from>` once; after that, `shipkit.json` records the version.

`shipkit-core` follows [semantic versioning](https://semver.org), and its major version is a promise about what your CI will do. A release that tightens a guard, so that code which passed before can now fail a check, is a **major** release. Minor and patch releases never make a passing project fail.

Every major release has a section below named for the version, and each entry in it names three things:

- **Fails:** the check or exception that now stops you, exactly as it appears (the test name or the exception class and message), so you can search for it.
- **Fix:** what to change in your project.
- **Why:** the hole the change closes, in one sentence.

Add the section in the pull request that makes the change, not afterwards. A major release without one is not ready.

```md
## Upgrading to 2.0

### A check or exception, named as it appears

- **Fails:** `GuardedTablesTest` › "never writes through the base query builder"
- **Fix:** Load the model, change it, and call `save()` instead of writing through `toBase()`.
- **Why:** A write through the base builder skipped every guard on the model.
```

## Upgrading to 6.0

`shipkit-core` 6.0 is a major release: the two checks below fail code that passed 5.2. Require it with `composer require shipwithian/shipkit-core:^6.0`, then run `php artisan shipkit:upgrade --from=<your current template version>` to see the template's changes, and `--apply` to take them; the `upgrade` skill walks an agent through it. Your project keeps working on the branch it uses today.

Once the project is on 6.0, have every pull request reviewed with `php artisan shipkit:review`, and add the `shipkit/review` status to the integration branch's required checks (`docs/branch-protection.md` in the template), so an unreviewed commit cannot merge.

To move to two branches, so that merging a pull request stops deploying:

1. Set `SHIPKIT_INTEGRATION_BRANCH=staging` in `.env` and `.env.example`. If `config/shipkit.php` has no `workflow` block yet, copy it from the template's.
2. Run `php artisan shipkit:doctor --fix`. It lists its changes and asks first, then creates `staging` from `main`, makes it GitHub's default branch, and turns on the deletion of merged branches.
3. Where the app is hosted, set the production environment's branch to `main`.
4. `shipkit:upgrade --apply` brings the template's `.github/workflows/pr-base.yml` and the `staging` push trigger of its workflows.

The template README's "Branches and deploys" section has the daily commands, the release, and the hotfix path.

### Hand-built controls instead of shadcn/ui primitives

- **Fails:** `FrontendPrimitivesTest`, with a message such as `resources/js/pages/orders/index.tsx:12: <table> is hand-built; use Table from @/components/ui/table (add it with npx shadcn@latest add table)`.
- **Fix:** replace each element with its primitive, adding a missing one with the command the message gives. Keep layout classes through `className`, and pick a variant (`ghost`, `link`, `icon`) instead of restyling. A file that genuinely needs raw markup goes in `shipkit.architecture.raw_element_exceptions` with the reason.
- **Why:** hand-built controls drift from the app's look, its focus states, and its accessibility, and agents kept writing them unless told otherwise.

### Routes named after a role

- **Fails:** `AudienceNamingTest`, with a message such as `staff/orders: the path names an audience ("staff")`.
- **Fix:** move the route to its resource's path and name (`/orders`, `orders.index`) and its pages to the feature's folder (`pages/orders/`). Keep the policy check in the controller, and show the link by ability. If two audiences see different lists, scope the list in its Query to the actor. If the path really is a resource, such as `staff` in an HR app, record it in `shipkit.architecture.audience_exceptions` with the reason.
- **Why:** a role in the URL protects nothing and splits one resource across several URLs. The policy and the abilities already decide who sees what.

To stay on one branch, change nothing. If you copy the new `pr-base.yml`, also run `gh variable set SHIPKIT_INTEGRATION_BRANCH --body main`, because the workflow now expects `staging` by default.

## Upgrading to 5.2

`shipkit-core` 5.2 adds commands and guidance; no check that passed on 5.1 can fail. `composer update shipwithian/shipkit-core` brings them. Then:

1. **Install the agent definitions.** Run `php artisan shipkit:agents`, and add `"@php artisan shipkit:agents --ansi"` after `boost:update` in `post-update-cmd` (and in `setup` and `post-create-project-cmd`) in `composer.json`, so `composer update` keeps them current. It installs `shipkit-standards-reviewer`, `shipkit-spec-reviewer`, and `shipkit-explorer` into `.claude/agents` when `boost.json` lists Claude Code, and leaves your own agents alone. `review-diff` and `diagnosing-bugs` now spawn them, so their sub-agents run on a pinned model and effort instead of inheriting yours.
2. **Name your integration branch.** Add the `workflow` and `agents` blocks to `config/shipkit.php` (copy them from the template's), and set `SHIPKIT_INTEGRATION_BRANCH` in `.env` when pull requests target `staging` rather than `main`.
3. **Turn on "Automatically delete head branches"** in the GitHub repository's settings (General → Pull Requests), or run `gh api -X PATCH repos/{owner}/{repo} -F delete_branch_on_merge=true`. GitHub retargets a stacked pull request only when the branch below it is deleted.
4. **Run `php artisan shipkit:doctor`.** It checks the GitHub setting, that the integration branch exists, and that the agent definitions are current. It needs `gh`, and skips with a warning where it cannot run, so keep it out of CI.
5. **Optional:** copy `.github/workflows/pr-base.yml` from the template, which fails a pull request whose base branch was already merged. Set the `SHIPKIT_INTEGRATION_BRANCH` repository variable if it isn't `main`.

6. **Optional, from the template:** Settings → AI assistants and the public MCP server. Copy:
    - for the page: `AiAssistantController`, `AiAssistantsEditProps`, `resources/js/pages/settings/ai-assistants.tsx`, its route in `routes/settings.php`, and its link in the settings layout, then drop `connectedApps` from the Security page and its props;
    - for the public server: `app/Mcp/Servers/PublicServer.php`, `app/Mcp/Tools/AppInfo`, the `/mcp/public` route in `routes/ai.php`, the `mcp-public` rate limiter and the `viewAppInfo` ability in `AppServiceProvider`, and `public_description` in `config/mcp.php`.

To change a reviewer's model or effort, set it in `config/shipkit.php` (`'agents' => ['shipkit-standards-reviewer' => ['model' => 'sonnet', 'effort' => 'medium']]`) and run `shipkit:agents`; an edit to the installed file is overwritten on the next update. A user's own instructions about models still win over ShipKit's defaults.

To make Claude Code refuse a sub-agent call that names neither a ShipKit agent nor a model and an effort, add this `PreToolUse` hook to your own `.claude/settings.json` (it needs `jq`). A hook that exits with code 2 blocks the call and shows the agent its message. ShipKit doesn't install it: Boost cannot sync hook settings, and agent settings are yours to own.

```json
{
    "hooks": {
        "PreToolUse": [
            {
                "matcher": "Agent",
                "hooks": [
                    {
                        "type": "command",
                        "command": "jq -e '(.tool_input.subagent_type // \"\" | startswith(\"shipkit-\")) or ((.tool_input.model // \"\") != \"\" and (.tool_input.effort // \"\") != \"\")' > /dev/null || { echo 'Spawn a shipkit-* agent, or pass model and effort explicitly (see the subagents guideline).' >&2; exit 2; }"
                    }
                ]
            }
        ]
    }
}
```

## Upgrading to 5.1

`shipkit-core` 5.1 is a minor release: only a project that has MCP tools in `app/Mcp/Tools` can fail its checks, and only when a tool breaks a convention the 5.0.0 check did not yet enforce. A project without tools updates with `composer update shipwithian/shipkit-core` and nothing else. The template's own changes (below) are code you own, so `composer update` does not bring them.

### Stricter MCP tool checks

- **Fails:** `ArchitectureTest` › "follows the MCP tool conventions", with a message such as `declare a snake_case #[Name('show_my_profile')]`, `declare a #[Title(...)]`, `declare #[IsReadOnly] or #[IsDestructive(true|false)] explicitly`, `register ListInvoicesTool in a server under app/Mcp/Servers`, or `authorize before anything else in handle()`; and "keeps each kind of class in its standard folder" for a tool class outside `app/Mcp/Tools/{Feature}` whatever its file is named.
- **Fix:** give each tool a snake_case `#[Name]`, a `#[Title]`, and `#[IsReadOnly]`, `#[IsDestructive]` (with `ConsequentialActionConfirmation`), or `#[IsDestructive(false)]`; list it in a server's `$tools`; and start `handle()` with `$user = $request->user();` and `Gate::forUser($user)->authorize(...)` (replace `Gate::inspect()` and a plain `Gate::authorize()`). Use `ChannelKind::Mcp` instead of the removed `Channel::MCP` constant.
- **Why:** an unannotated tool is destructive to an MCP client, a tool nobody registered cannot be called, and a check that accepted `Gate::inspect()` or work before authorizing let a tool skip its policy.

### Template changes to copy from 5.1.0

Compare the `v5.0.0` and `v5.1.0` tags, or copy these:

- `config/mcp.php` (the redirect allowlist; 5.0.0 accepted any redirect, so any site could register as a trusted client), with `MCP_REDIRECT_DOMAINS=` in `.env.example`.
- `app/Http/Middleware/LimitRegisteredClientsToMcp.php` (fails closed, acts only on registration), `ShareChannel`, the `Channel` binding in `AppServiceProvider`, `OAuthClientNameQuery`, and the `Channel` parameter of `UpdateProfileAction` with its callers.
- `ConsequentialActionConfirmation` at `App\Mcp\Support`, with its separate token, lock, and result durations, and the `mcp.confirmation_lock_wait_seconds` setting.
- `update_my_profile`'s confirmation of an email change.
- From 5.1.1, `package.json`: `vite-plus` 1.6.0 clears eight Dependabot alerts in dev tooling. Set `"vite": "npm:@voidzero-dev/vite-plus-core@1.6.0"` in `dependencies`, add `"vite": "$vite"` and `"vitest": "5.1.0"` to `overrides`, set `"vite-plus": "1.6.0"`, and run `npm install`. Do not use `vp migrate`: it converts a project to pnpm.

## Upgrading to 5.0

`shipkit-core` 5.0 adds only checks for MCP tools, which pass on a project without them. The major version belongs to the template, whose API moves from Sanctum to Passport. A project created before 5.0 keeps working on Sanctum; follow these steps only to adopt the OAuth server.

### Sanctum → Passport

- **Fails:** every API request with an existing token returns `401 Unauthenticated.`, because Sanctum tokens mean nothing to Passport.
- **Fix:**
    1. `composer remove laravel/sanctum`, `composer require laravel/passport:^13.11`, delete `config/sanctum.php` and the `personal_access_tokens` migration, and publish Passport's migrations and config (`php artisan vendor:publish --tag=passport-migrations` and `--tag=passport-config`).
    2. Copy from ShipKit 5.0: `app/Providers/PassportServiceProvider.php` (and register it in `bootstrap/providers.php`), `app/Models/OAuthClient.php`, `app/Models/OAuthToken.php`, `app/Enums/ApiScope.php`, the migration that creates the personal access client, the `last_used_at` column and cascades on the `oauth_*` tables, and the `api` guard in `config/auth.php`.
    3. On `User`, replace Sanctum's `HasApiTokens` with Passport's and implement `Laravel\Passport\Contracts\OAuthenticatable`.
    4. In `routes/api.php`, replace `auth:sanctum` with `auth:api`, `CheckToken::using(ApiScope::Api->value)`, and `RecordApiTokenUse`.
    5. Replace `SANCTUM_TOKEN_EXPIRATION` (minutes) with `PERSONAL_ACCESS_TOKEN_LIFETIME_DAYS` (days).
    6. Run `php artisan migrate` and `php artisan passport:keys`, and provide `PASSPORT_PRIVATE_KEY` and `PASSPORT_PUBLIC_KEY` in production.
    7. Copy the Auth Actions and Queries, the consent page (`auth/oauth-authorize`), and the Security page sections, or compare the `v4.0.0` and `v5.0.0` tags for the full change.
    8. Tell API clients to sign in again: their old tokens no longer work, and token ids are now strings.
- **Why:** Sanctum only issues tokens to the app's own clients. Passport adds an OAuth server, so other apps, including AI clients, can act for a user with their consent, and Sanctum and Passport cannot share the `User` model.

## Upgrading to 4.0

### Laravel's standard folders

- **Fails:** `ArchitectureTest` › "keeps each kind of class in its standard folder", with `app/Domain exists.`
- **Fix:** Move each context's classes to Laravel's folder for their kind, and rename their namespaces to match:

    | 3.x                                                             | 4.0                                                                                                     |
    | --------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------- |
    | `app/Domain/{Context}/Actions` (`App\Domain\{Context}\Actions`) | `app/Actions/{Feature}` (`App\Actions\{Feature}`)                                                       |
    | `app/Domain/{Context}/Queries`                                  | `app/Queries/{Feature}`                                                                                 |
    | `app/Domain/{Context}/Models`                                   | `app/Models`                                                                                            |
    | `app/Domain/{Context}/Policies`                                 | `app/Policies` (drop `#[UsePolicy]` where the name matches the model)                                   |
    | `app/Domain/{Context}/Exceptions`                               | `app/Exceptions`                                                                                        |
    | `.ai/rules/contexts/{context}.md`                               | `.ai/rules/features/{feature}.md`, with `paths` pointing at the new folders                             |
    | `database/factories/Domain/{Context}/Models/{Model}Factory.php` | `database/factories/{Model}Factory.php`, and remove the factory-name resolver from `AppServiceProvider` |

    In `config/shipkit.php`, rename `contexts_using_framework_exceptions` to `features_using_framework_exceptions` and `non_context_folders` to `non_feature_folders`, and point `models` at `App\Models`. Run `php artisan make:feature {Name}` instead of `make:context`.

- **Why:** The bounded-context layout added a second vocabulary and special cases (the shared `User`, Fortify's actions) on top of Laravel, while the protection came from Actions, Query objects, and model guards, which work the same in Laravel's own folders.

## Upgrading to 3.0

### Frontend files over the length limit

- **Fails:** `FrontendFileLengthTest` › "keeps every frontend file short enough to read in one sitting", with `resources/js/pages/{feature}/{page}.tsx has N lines, over the 200-line limit for a page.`
- **Fix:** Split the file: move each section, dialog, table, or form into its own partial in the feature's `partials/` folder, and state and handlers into a `use-{feature}.ts` hook beside it. Any other frontend file has a 400-line limit. To keep a file long on purpose, list its path in `architecture.long_frontend_files` in `config/shipkit.php` with the reason.
- **Why:** Nothing limited a page's size, so pages grew past 2,000 lines that neither people nor agents could review.

## Upgrading to 2.0

### The `assign roles` system permission

- **Fails:** `AccessControlSeederTest` › "seeds protected access control records idempotently", with `Failed asserting that 4 is identical to 3.`
- **Fix:** Count the system permissions instead of hardcoding them: `->toBe(count(SystemPermission::cases()))`. Then run `php artisan db:seed --class=AccessControlSeeder` in every environment so the database has the new permission and `super-admin` holds it.
- **Why:** `SystemPermission::AssignRoles` was added, so the bootstrap now creates four protected permissions instead of three.

### `SystemRecordIsNotProtected` when seeding

- **Fails:** `AccessControlSeeder` throws `SystemRecordIsNotProtected` for the permission `assign roles`.
- **Fix:** Your project already has its own unprotected `assign roles` permission. Rename it or delete it, then run the seeder again. Roles that held it need the new protected permission instead.
- **Why:** Only the bootstrap may create a protected record, and it refuses to adopt an existing unprotected one.

### The Users page is template code

- **Fails:** nothing. Updating the package adds the permission, not the feature.
- **Fix:** To assign roles from the app, copy the feature from the ShipKit template: `SyncUserRolesAction`, `RoleExceedsActorPermissions`, `UserRoleListQuery`, `RolePolicy::assignToUsers`, `UserRoleController` and its request, props, and routes, `resources/js/pages/users`, the `viewUsers` navigation ability, and their tests. Then run `composer types:generate`.
- **Why:** Projects are one-time copies of the template, and only enforcement ships in `shipkit-core`.

## Upgrading to 1.0

The first release. There is nothing to upgrade from.
