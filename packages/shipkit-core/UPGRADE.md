# Upgrade notes

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

## Upgrading to 1.0

The first release. There is nothing to upgrade from.
