# Branch protection

Agents may merge project-owned feature code on their own once the checks pass. Only the maintainer may change the checks. `.github/CODEOWNERS` lists what the maintainer owns. The ruleset below makes GitHub enforce the split, so it no longer depends on agents following instructions.

Only a repository admin can apply it. This file is the runbook for doing that.

## Before you apply it: give agents their own identity

Branch protection can only tell an agent apart from the maintainer if they use different GitHub identities. If agents push and merge with the maintainer's token, they also get the maintainer's admin bypass, and the ruleset stops nothing.

Give agents an identity with **write** access but not admin:

- a GitHub App installed on the repository, with contents, pull requests, and checks permissions; or
- a machine user added as a collaborator with the Write role.

Agents' `gh` and `git` then authenticate as that identity, for example with a `GH_TOKEN` scoped to it. A pull request an agent opens is then authored by the agent, and the maintainer can approve it.

## The ruleset

| Setting                                                | Value                                                               | Why                                                                                                                                                              |
| ------------------------------------------------------ | ------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| Target                                                 | the default branch (`staging`)                                      |                                                                                                                                                                  |
| Restrict deletions, block force pushes                 | on                                                                  | Keeps history, and the evidence it carries, intact.                                                                                                              |
| Require a pull request before merging                  | on, **0** approvals                                                 | Every change goes through a pull request, but feature code needs no human approval.                                                                              |
| Require review from Code Owners                        | on                                                                  | A pull request that touches a path in `.github/CODEOWNERS` waits for the maintainer.                                                                             |
| Dismiss stale approvals, require approval of last push | on                                                                  | A code-owner approval covers the exact diff that merges.                                                                                                         |
| Require status checks to pass, branch up to date       | `ci`, `concurrency (mysql)`, `concurrency (pgsql)`, `fresh-project` | `composer ci:check`, the MySQL and Postgres concurrency group, and the fresh-project job must pass on the merged result.                                         |
| Bypass list                                            | Repository admin, **for pull requests only**                        | GitHub never lets the author approve their own pull request, so this lets the maintainer merge their own changes to owned paths. Nobody can push straight to it. |

Rename the required checks here whenever a job in `.github/workflows` is renamed. A required check that never reports blocks every merge.

## Apply it

Run this as a repository admin:

```bash
gh api repos/shipwithian/shipkit/rulesets --method POST --input - <<'EOF'
{
  "name": "main",
  "target": "branch",
  "enforcement": "active",
  "conditions": { "ref_name": { "include": ["~DEFAULT_BRANCH"], "exclude": [] } },
  "bypass_actors": [
    { "actor_id": 5, "actor_type": "RepositoryRole", "bypass_mode": "pull_request" }
  ],
  "rules": [
    { "type": "deletion" },
    { "type": "non_fast_forward" },
    {
      "type": "pull_request",
      "parameters": {
        "required_approving_review_count": 0,
        "require_code_owner_review": true,
        "dismiss_stale_reviews_on_push": true,
        "require_last_push_approval": true,
        "required_review_thread_resolution": false
      }
    },
    {
      "type": "required_status_checks",
      "parameters": {
        "strict_required_status_checks_policy": true,
        "required_status_checks": [
          { "context": "ci" },
          { "context": "concurrency (mysql)" },
          { "context": "concurrency (pgsql)" },
          { "context": "fresh-project" }
        ]
      }
    }
  ]
}
EOF
```

`actor_id` 5 is GitHub's built-in Repository admin role.

## The second ruleset: `main`

ShipKit works on two branches. `staging` is the default branch, so the ruleset above, which targets the default branch, protects it once `php artisan shipkit:doctor --fix` has made `staging` the default. `main` then needs its own ruleset, because only the release pull request and hotfixes go into it:

| Setting                                | Value                                                               | Why                                                                                                                                       |
| -------------------------------------- | ------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- |
| Target                                 | `main`                                                              |                                                                                                                                           |
| Restrict deletions, block force pushes | on                                                                  | `main` is what is released.                                                                                                               |
| Require a pull request before merging  | on, code-owner review, **merge commits only**                       | A release carries every path, so it always waits for the maintainer. A squash or rebase would make `staging` and `main` diverge.          |
| Require status checks to pass          | `ci`, `concurrency (mysql)`, `concurrency (pgsql)`, `fresh-project` | The same checks as `staging`.                                                                                                             |
| Branch up to date                      | **off**                                                             | Each release leaves a merge commit on `main` that `staging` does not have. It changes no file, so requiring it would block every release. |
| Bypass list                            | Repository admin, **for pull requests only**                        | The maintainer merges the release they opened.                                                                                            |

```bash
gh api repos/shipwithian/shipkit/rulesets --method POST --input - <<'EOF'
{
  "name": "main (releases)",
  "target": "branch",
  "enforcement": "active",
  "conditions": { "ref_name": { "include": ["refs/heads/main"], "exclude": [] } },
  "bypass_actors": [
    { "actor_id": 5, "actor_type": "RepositoryRole", "bypass_mode": "pull_request" }
  ],
  "rules": [
    { "type": "deletion" },
    { "type": "non_fast_forward" },
    {
      "type": "pull_request",
      "parameters": {
        "required_approving_review_count": 0,
        "require_code_owner_review": true,
        "dismiss_stale_reviews_on_push": true,
        "require_last_push_approval": true,
        "required_review_thread_resolution": false,
        "allowed_merge_methods": ["merge"]
      }
    },
    {
      "type": "required_status_checks",
      "parameters": {
        "strict_required_status_checks_policy": false,
        "required_status_checks": [
          { "context": "ci" },
          { "context": "concurrency (mysql)" },
          { "context": "concurrency (pgsql)" },
          { "context": "fresh-project" }
        ]
      }
    }
  ]
}
EOF
```

Then tell the `pull request base` workflow which branch is the integration branch. It expects `staging` by default, so this only records the choice:

```bash
gh variable set SHIPKIT_INTEGRATION_BRANCH --body staging
```

## Check that it works

1. As the agent identity, open a pull request that changes only feature code, such as a page under `resources/js/pages`. After CI passes it shows as mergeable with no review.
2. As the agent identity, open a pull request that touches `config/shipkit.php`. It shows "Review required" from a code owner, and the agent cannot merge it.
3. Try `git push origin staging` and `git push origin main` directly. GitHub rejects both.
4. Open a pull request from a feature branch into `main`. The `pull request base` check fails and says to retarget it.
