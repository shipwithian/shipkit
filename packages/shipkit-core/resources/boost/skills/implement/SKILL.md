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

Then call the Skill tool with "review-diff" to review the work against the spec and the `.ai` rules.

Leave the work uncommitted for the user to review unless they asked you to commit, as `vendor/shipwithian/shipkit-core/resources/rules/general.md` requires.
