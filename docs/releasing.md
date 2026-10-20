# Releasing

ShipKit publishes two packages at the same version, and a release publishes both:

- **`shipwithian/shipkit-core`** is `packages/shipkit-core`, split out of this repository into a read-only repository that Packagist serves. Projects update it with `composer update`.
- **`shipwithian/shipkit`** is the template. `laravel new my-app --using=shipwithian/shipkit` installs a tag of it.

`main` develops against the copy in `packages/` through a path repository. A release tag cannot, because a new project has no `packages/` folder. So the tag points at a **release commit** built from `main` by `.github/scripts/prepare-release.sh`: it requires the published `^1.0`, removes the path repository and `packages/`, and re-locks. That commit is not part of `main`'s history, which is why `git log main` never shows a tag's commit. Nothing on `main` changes during a release.

## Set up once

Only the maintainer can do these. A release fails with a message that names the missing piece.

1. **Create the read-only repository.** An empty, public `shipwithian/shipkit-core` repository. Disable its issues and pull requests, and say in its description that it is a read-only mirror of `packages/shipkit-core` in `shipwithian/shipkit`.
2. **Let the workflows push to it.** Create a fine-grained personal access token whose only access is **Contents: read and write** on `shipwithian/shipkit-core`, and add it to this repository as the Actions secret `SHIPKIT_CORE_SPLIT_TOKEN`. Give it an expiry and put the renewal in your calendar.
3. **Register both packages on Packagist.** Submit `https://github.com/shipwithian/shipkit-core` and `https://github.com/shipwithian/shipkit`, and enable the GitHub update hook for each so a pushed tag is listed within seconds.
4. **Publish the first split.** Run the **split core** workflow from the Actions tab. Without it, `shipkit-core` has no `main` branch until a change under `packages/shipkit-core` is merged.
5. **Protect the tags.** Add a tag ruleset on `v*` that blocks deletion and updates, so a published version cannot be moved.

## Release

1. **Prepare the notes in a pull request.** In `CHANGELOG.md` and `packages/shipkit-core/CHANGELOG.md`, replace `(unreleased)` with the version and date. A major release must also have its section in `packages/shipkit-core/UPGRADE.md` (see below). Merge it.
2. **Run the release.** From the Actions tab, run **release** on `main` with the version, such as `1.0.0`. It does this in order and stops at the first failure:
    1. Splits `packages/shipkit-core` and tags the split `v1.0.0` in `shipwithian/shipkit-core`.
    2. Waits up to 10 minutes for Packagist to list it.
    3. Builds the release commit and creates a project from it with the package from Packagist, then runs `composer ci:check` in that project and checks that it fails when the checks are unregistered or a mapped model is unguarded.
    4. Pushes the tag `v1.0.0` to this repository.
3. **Check it as a user would.** In an empty folder, run `laravel new release-check --using=shipwithian/shipkit`, then `cd release-check && composer ci:check`. Delete the folder afterwards.
4. **Publish the notes.** Create the GitHub release for the tag from the matching `CHANGELOG.md` section:

```bash
gh release create v1.0.0 --title "ShipKit 1.0.0" --notes-file notes.md
```

If a step fails after the core tag exists and `packages/shipkit-core` has not changed since, rerun the workflow: pushing the same split to the same tag is a no-op. Otherwise, or if the failure is in the project check, fix `main` and release the next patch version; never move a tag.

## Versioning

Both packages use [semantic versioning](https://semver.org) and share a version number, so `shipwithian/shipkit` `1.4.0` requires `shipwithian/shipkit-core` `^1.4`.

- A release that **tightens a guard**, so that code which passed a check before can fail it, is a **major** version. Its section in `packages/shipkit-core/UPGRADE.md` names the failing check, the fix, and why, as that file describes. Review a pull request that changes a guard or a check for this before merging it.
- A release that adds a check or guard without failing any passing project is **minor**; a fix is **patch**.
- Template changes to project-owned code (controllers, pages, the Authentication and Settings contexts) are never merged into projects that already exist, so they need no upgrade notes.
- Before 1.0.0, changes broke freely with no compatibility code.
- A project created from ShipKit starts its own version sequence and tags; it does not inherit these.
