---
name: implement
description: 'Implement a piece of work based on a spec or set of GitHub issue tickets, test-first, finishing with composer ci:check and a review.'
disable-model-invocation: true
license: MIT
metadata:
    source: https://github.com/mattpocock/skills/tree/main/skills/engineering/implement
---

Implement the work described by the user in the spec or tickets.

If the user passes a ticket reference, fetch it with `gh issue view <number> --comments` (see `.ai/issue-tracker.md`) and state its title before starting. If the reference is ambiguous, ask.

Before writing code, open `.ai/rules/index.md` and read every rule file whose globs match the files you will touch, including the context rule file in `.ai/rules/contexts/`. If the work needs a new bounded context, run `php artisan make:context {Name}` first.

Work test-first at the seams agreed in the spec: call the Skill tool with "testing-best-practices", write a failing Pest test, then make it pass. New business rules become `ensure...()` methods called from the model's `guardCreate()`/`guardUpdate()`/`guardDelete()` (with `GuardsInvariants`), each throwing a named domain exception, and get a row in the context rule file.

Run `composer types:check` and the single test files you touch regularly (`php artisan test --compact <path>`). Once done, run `vendor/bin/pint --format agent` and then `composer ci:check`, which includes the full test suite and `vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php`. Fix every failure; never weaken an architecture check to get green.

Then call the Skill tool with "review-diff" to review the work against the spec and the `.ai` rules, and fix what it finds.

Leave the work uncommitted for the user to review unless they asked you to commit or ship it, as `vendor/shipwithian/shipkit-core/resources/rules/general.md` requires.

## Handoff evidence

Work ships on evidence, not on your summary. Collect all three items before you call the work done, and when you open a pull request, put them in its description:

1. **Regression test.** For a change to an invariant (an `ensure...()` method, a guard, a pivot rule, or a cross-aggregate rule in an Action) and for every bug fix, name the test that proves it. Revert only the production change, run that test, and record that it fails for the expected reason. Restore the change and record that it passes.
2. **`composer ci:check` passes**, locally and in CI on the pull request.
3. **An independent review.** A `review-diff` report on the full diff, produced by an agent session that did not write the code, with every hard finding fixed or answered. Your own review from the step above does not count. Ask the user to run it in a fresh session, or start one if you can.

## Merge rule

An agent may merge a pull request on its own only when the user asked it to ship the work, all three items above are present, and the diff touches no maintainer-owned path. The maintainer-owned paths are the ones listed in the repository's CODEOWNERS file. Without one, use the list in the architecture guideline's agent workflow section. Compare `git diff --name-only <base>...HEAD` against that list.

If the diff touches a maintainer-owned path, open the pull request with the evidence, request the maintainer's review, and stop. Never merge with an admin bypass, and never weaken, skip, or move a check to get a change merged.
