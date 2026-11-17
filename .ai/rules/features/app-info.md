---
paths:
    - 'app/Actions/AppInfo/**'
    - 'app/Queries/AppInfo/**'
    - 'app/Mcp/Tools/AppInfo/**'
---

# App Info

## Vocabulary

- **App info**: the public facts about the app that anyone may read without an account: its name, the description in `mcp.public_description` (`MCP_PUBLIC_DESCRIPTION`), and the address of the signed-in MCP server.
- **Public server**: `App\Mcp\Servers\PublicServer` at `/mcp/public`, marked `#[ForGuests]`, for AI clients of visitors who have not signed in. The `mcp` rule covers what its tools may do.

## Invariants

| Rule                                         | Enforced by                                                               | Exception |
| -------------------------------------------- | ------------------------------------------------------------------------- | --------- |
| Every tool on the public server is read-only | "follows the MCP tool conventions" in `ArchitectureTest` (`#[ForGuests]`) | —         |
| Anyone may read app info, signed in or not   | The `viewAppInfo` ability in `AppServiceProvider`, which takes `?User`    | —         |

## Actions and Queries

- Actions: none yet.
- Queries: none yet.

## MCP tools

- `show_app_info` (`ShowAppInfoTool`, read-only), on the public server.
