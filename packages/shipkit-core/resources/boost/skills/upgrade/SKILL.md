---
name: upgrade
description: Move a project's template files to a newer ShipKit release with `php artisan shipkit:upgrade`, resolve what conflicts, and prove the result. Use when the user asks to upgrade ShipKit, adopt a new release, or bring in template changes.
---

# Upgrade

A project created from ShipKit gets two kinds of update. `shipkit-core` (checks, rules, skills) comes from Composer. The template's files (controllers, pages, config, workflows) are the project's own, and `php artisan shipkit:upgrade` brings a newer release's changes into them as a three-way merge.

Work on a branch from the integration branch (`vendor/shipwithian/shipkit-core/resources/rules/branches.md`), with a clean working tree.

## 1. Update the package

Move `shipkit-core` to the target release first, because the command's default target is the installed core version:

```bash
composer update shipwithian/shipkit-core
```

For a new major version, require it instead (`composer require shipwithian/shipkit-core:^6.0`), and read its section in `vendor/shipwithian/shipkit-core/UPGRADE.md` for the checks that may now fail.

## 2. Read the report

```bash
php artisan shipkit:upgrade
```

It changes nothing. It lists every template file that changed between the project's recorded version (`shipkit.json`) and the target, with what applying does to it, then the upgrade notes for each version in between. A project with no recorded version needs `--from=<the release it was created from>`. Show the user the counts and every file marked `conflicts` before applying.

## 3. Apply

```bash
php artisan shipkit:upgrade --apply
```

It writes the changes into the working tree, stamps the new version in `shipkit.json`, and never stages or commits. `git diff` shows exactly what it did, and `git restore --source=HEAD --staged --worktree .` undoes it.

## 4. Resolve conflicts

For each file it lists:

- **Conflict markers** (`<<<<<<< yours`, `>>>>>>> template after`): keep what the project needs from both sides. Read the release's changelog entry for why the template changed it.
- **A file the template removed that the project edited:** it is still there. Decide with the user whether the project still uses it.
- **A binary file:** the project's version was kept. Compare it with the release's by hand.

A file marked `deleted in your project` stayed deleted on purpose; mention it, but do not bring it back.

## 5. Finish

Do what files cannot carry, from the upgrade notes the report printed: keys, environment variables, data. Then:

```bash
composer update
```

```bash
npm install
```

```bash
php artisan migrate
```

```bash
composer ci:check
```

Fix every failure. Leave the work uncommitted for the user to review, unless they asked you to commit; then open the pull request against the integration branch with the report's counts and the conflicts you resolved.
