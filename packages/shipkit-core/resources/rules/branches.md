---
paths:
    - '**'
---

# Branches and pull requests

The integration branch is the one every pull request targets: `php artisan config:show shipkit.workflow.integration_branch`. A project created from ShipKit uses `staging`: work collects there without deploying, and `main` holds what is released, so the host can deploy on every change to `main`. A project that works on one branch sets it to `main`. `php artisan shipkit:doctor` checks that the branch exists, that it is GitHub's default branch, and that GitHub deletes merged branches; `php artisan shipkit:doctor --fix` sets all three up.

## Target the integration branch

Start a branch from the integration branch (`git switch -c <branch> origin/<integration-branch>`) and open every pull request against it. Never merge one into a feature branch or another pull request's branch, except the bottom of a stack below. When the integration branch is not `main`, only the release and a hotfix, below, target `main`.

## Stack only on a real dependency

Base a pull request on another one's branch only when it cannot work without it, such as editing the same file or calling code the lower one adds. Independent work gets its own pull request on the integration branch, even when it was written in the same session.

## Merge a stack bottom-up, with merge commits

Merge the lowest pull request first, then the next, each with a merge commit rather than a squash: a squash rewrites the lower work, so the upper pull request conflicts with its own history.

## Retarget before merging

Before merging, read the pull request's base with `gh pr view <n> --json baseRefName`. When it is not the integration branch and that branch's own pull request is already merged, the work would land on a leftover branch and never reach the integration branch. Retarget it first with `gh pr edit <n> --base <integration-branch>`, wait for its checks, then merge. GitHub retargets a stacked pull request on its own only when the merged branch is deleted.

## Delete the head branch after merging

Delete the merged branch (`gh pr merge <n> --merge --delete-branch`), and turn on "Automatically delete head branches" in the repository's settings so a forgotten one is removed too.

## Release to `main`

When the integration branch is not `main`, a release is one pull request from the integration branch into `main`: `gh pr create --base main --head <integration-branch> --title "Release <date or version>"`. Open it when the maintainer asks, and list the pull requests it carries. Merging it is the maintainer's, never an agent's: merging is what deploys. Merge it with a merge commit, never a squash or a rebase, or the two branches diverge and the next release conflicts with itself. Never delete the integration branch after the release merges.

## Hotfix

A fix that cannot wait for the next release starts from `main` on a `hotfix/<name>` branch and targets `main`, with the maintainer's agreement. After it merges, bring it back with a pull request from `main` into the integration branch, so the next release does not undo it.
