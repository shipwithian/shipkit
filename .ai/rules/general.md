---
paths:
  - '**'
---

# General

## Version repositories with Git tags
Treat this repository as a versioned product. Use semantic versioning and publish stable releases as Git tags in `vMAJOR.MINOR.PATCH` form. Keep `main` for ongoing development and maintain release branches only when supporting an existing release line. When this repository is used as a template, each generated project must establish its own independent version sequence and release tags rather than inheriting the template’s version history. Use Git tags as the version source of truth.

## Commit only when explicitly requested
Do not create or stage commits automatically after making changes; leave the working tree available for review in Git source control. Commit only when the user explicitly requests it, and group separable changes into focused logical commits when practical.
