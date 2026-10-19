---
name: correct
description: 'Find the mistakes agents keep repeating in this repo and make each one impossible: architecture first, then a failing check, then a test, docs last. Prove each check fails on a real past mistake. Run after the user corrects an agent for the same thing twice.'
disable-model-invocation: true
license: MIT
metadata:
    source: https://github.com/cursor/plugins/tree/main/pstack/skills/correct
---

# Correct

The user keeps correcting agents in this repo for the same mistakes. Change the repo so the next agent can't make them.

Assume every contributor is an agent that sees only the files it opened, copies the nearest example, and takes the shortest path that compiles. Design the repo so a change that looks right from one file is right for the whole repo.

## Find the mistake classes

Read recent commits, reverts, review comments, `vendor/shipwithian/shipkit-core/resources/boost/guidelines/architecture.md`, the `.ai/rules` files, and comments that explain workarounds. If the user named a mistake, start there. Group the mistakes into classes. A class counts once it has happened twice.

## Fix each class at the highest level that works

1. **Eliminate it with architecture.** Give each piece of state one owner and each task one supported way. Put aggregate invariants in the model's `guardCreate()`/`guardUpdate()`/`guardDelete()` with `GuardsInvariants` so no caller can skip them. Hide context internals so the wrong import fails `ArchitectureTest`. Replace hand-synced lists with discovery (see `ProjectLayout::contexts()` in `vendor/shipwithian/shipkit-core/src/Architecture/ProjectLayout.php`). Delete old ways and dead code an agent would copy.
2. **Fail the build.** Make the bad state unwritable with PHP types and PHPStan (`composer types:check`). If bad code still passes, add a Pest `arch()` rule or a check under `tests/` whose failure message names the file and the class or method to use instead. A rule every ShipKit project needs belongs in `shipkit-core`'s checks (`vendor/shipwithian/shipkit-core/tests/`), changed in ShipKit itself, never in `vendor/`.
3. **Test the behavior.** Add a feature test under `tests/Feature/{Context}/`. Fix or delete any test that would still pass if every function it calls returned nothing.
4. **Write a rule last, only for judgment calls.** Add it to the matching rule file mapped in `.ai/rules/index.md` (a project rule in `.ai/rules`, or a shared one in `shipkit-core`'s `resources/rules`, changed in ShipKit itself) or the context's `.ai/rules/contexts/{context}.md`. Nothing fails when an agent skips a rule.

## Fix and prove

Fix the most frequent classes now, one logical change each. Prove each new check fails on a real past mistake: reintroduce the mistake, watch the check go red, then restore the code. `composer ci:check` runs the same checks locally and in CI. An exception goes on the offending line with a reason and needs the user's approval.

## Keep the enforcement table

`.ai/rules/enforcement.md` pairs each rule with what enforces it. When the user corrects you, fix the mistake and add the row. If the row was already there and nothing enforces it, that's a repeat: fix it at the highest level in the same change. Drop a row once its mistake can't happen.

**Reply:** each class with its evidence, the level you picked, the proof that the check fails on the past mistake, and why a higher level didn't work.
