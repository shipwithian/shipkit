---
name: domain-modeling
description: Build and sharpen a bounded context's domain model. Use when discussing codebase terminology, defining a new context, writing a context's vocabulary or invariants in .ai/rules/contexts, or recording an ADR.
license: MIT
metadata:
    source: https://github.com/mattpocock/skills/tree/main/skills/engineering/domain-modeling
---

# Domain Modeling

Actively build and sharpen the project's domain model as you design. This is the _active_ discipline: challenging terms, inventing edge-case scenarios, and writing the vocabulary, invariants, and decisions down the moment they crystallise. (Merely _reading_ a context rule file for vocabulary is not this skill: that's a habit any task does. This skill is for when you're changing the model, not just consuming it.)

## Where the model lives

This repo has one bounded context per folder in `app/Domain/`, and each context's model lives in its rule file:

```
.ai/rules/contexts/
├── access-control.md     ← Vocabulary, Invariants, Public surface for app/Domain/AccessControl
├── settings.md
└── {context}.md
docs/adr/                  ← decisions, created lazily
```

A new context starts with `php artisan make:context {Name}`, which scaffolds its rule file. The context rule file is the single source of truth for the context's language, and `vendor/shipwithian/shipkit-core/tests/ArchitectureTest.php` fails when an Action, Query, or domain exception is missing from it.

## During the session

### Challenge against the vocabulary

When the user uses a term that conflicts with the context's **Vocabulary** section, call it out immediately. "Your Settings vocabulary defines 'account' as the user's own record, but you seem to mean the billing customer. Which is it?"

### Sharpen fuzzy language

When the user uses vague or overloaded terms, propose a precise canonical term. "You're saying 'account': do you mean the User or the Customer? Those are different things." If a term belongs to a different context than the one being discussed, say so: crossing a context boundary is a design decision, not a wording choice.

### Discuss concrete scenarios

When domain relationships are being discussed, stress-test them with specific scenarios. Invent scenarios that probe edge cases and force the user to be precise about the boundaries between concepts, and about which rules must hold for every caller (those are **invariants**).

### Cross-reference with code

When the user states how something works, check whether the code agrees. If you find a contradiction, surface it: "Your code cancels entire Orders, but you just said partial cancellation is possible. Which is right?"

### Update the context rule file inline

When a term is resolved, update the context's rule file right there. Don't batch these up: capture them as they happen. Follow [VOCABULARY-FORMAT.md](./VOCABULARY-FORMAT.md).

- **Vocabulary** holds business terms only, using the exact names the code uses. No implementation details.
- **Invariants** holds each rule that must always hold, the `ensure...()` method or Action that enforces it, and the domain exception it throws. An invariant with no enforcer yet is a task, not a finished entry: say so.
- **Public surface** lists the context's Actions and Queries.

### Offer ADRs sparingly

Only offer to create an ADR when all three are true:

1. **Hard to reverse**: the cost of changing your mind later is meaningful
2. **Surprising without context**: a future reader will wonder "why did they do it this way?"
3. **The result of a real trade-off**: there were genuine alternatives and you picked one for specific reasons

If any of the three is missing, skip the ADR. Use the format in [ADR-FORMAT.md](./ADR-FORMAT.md).
