---
paths:
    - '.github/**'
---

# Branches and pull requests

The integration branch is the one every pull request targets: `php artisan config:show shipkit.workflow.integration_branch` (`main` unless the project sets `SHIPKIT_INTEGRATION_BRANCH`, for example to `staging`). `php artisan shipkit:doctor` checks that it exists and that GitHub deletes merged branches.

## Target the integration branch

Open every pull request against the integration branch. Never merge one into a feature branch or another pull request's branch, except the bottom of a stack below.

## Stack only on a real dependency

Base a pull request on another one's branch only when it cannot work without it, such as editing the same file or calling code the lower one adds. Independent work gets its own pull request on the integration branch, even when it was written in the same session.

## Merge a stack bottom-up, with merge commits

Merge the lowest pull request first, then the next, each with a merge commit rather than a squash: a squash rewrites the lower work, so the upper pull request conflicts with its own history.

## Retarget before merging

Before merging, read the pull request's base with `gh pr view <n> --json baseRefName`. When it is not the integration branch and that branch's own pull request is already merged, the work would land on a leftover branch and never reach the integration branch. Retarget it first with `gh pr edit <n> --base <integration-branch>`, wait for its checks, then merge. GitHub retargets a stacked pull request on its own only when the merged branch is deleted.

## Delete the head branch after merging

Delete the merged branch (`gh pr merge <n> --merge --delete-branch`), and turn on "Automatically delete head branches" in the repository's settings so a forgotten one is removed too.
