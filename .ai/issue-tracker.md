# Issue tracker

Work for this project is tracked in the GitHub Issues of the repository's `origin` remote, through the `gh` CLI. If `gh auth status` fails, ask the user to run `! gh auth login`.

## Labels

| Label             | Meaning                                                  |
| ----------------- | -------------------------------------------------------- |
| `ready-for-agent` | Fully specified; an agent can pick it up without asking. |
| `ready-for-human` | Needs a human decision or action before work starts.     |

Create a missing label before using it: `gh label create ready-for-agent --color 0e8a16 --description "Fully specified; an agent can pick it up"`.

## Operations

- **Read an issue:** `gh issue view <number> --comments`
- **Create an issue:** `gh issue create --title "<title>" --body-file <file> --label ready-for-agent`. Write the body to a temporary file first so Markdown survives shell quoting.
- **Find references in commits:** `#123` or `Closes #123` in `git log` messages.
- **Make a ticket a sub-issue of a parent:** `gh api repos/{owner}/{repo}/issues/<parent>/sub_issues -X POST -F sub_issue_id=<child issue id>`, where the child id comes from `gh api repos/{owner}/{repo}/issues/<child> --jq .id`.
- **Record blocking edges:** add a `## Blocked by` section listing `#<number>` for each blocker. Publish blockers first so their numbers exist.

Never close, reopen, or relabel an issue the current task did not create unless the user asks.
