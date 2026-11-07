# Review checklist

The reviewer's standards beyond what `composer ci:check` already enforces. Rows of `.ai/rules/enforcement.md` that name a check are covered by that check; spend review attention on the rest.

- [ ] Each new class is in its standard folder: Actions in `app/Actions/{Feature}`, Queries in `app/Queries/{Feature}`, models in `app/Models`, policies in `app/Policies`, exceptions in `app/Exceptions`, under the feature whose vocabulary it uses.
- [ ] `ArchitectureTest` passes.
- [ ] Each Action describes one clear use case and receives only the explicit typed values it needs.
- [ ] Controllers coordinate HTTP rather than own application workflows; every protected action calls `Gate::authorize()` first; every read goes through a Query object.
- [ ] Routes carry no permission middleware, and Form Requests validate and normalize input only.
- [ ] Invariants hold independently of actor authorization: model rules as `ensure...()` model methods called from `guardCreate()`/`guardUpdate()`/`guardDelete()` with `GuardsInvariants`, relationship rules on a pivot with `GuardsPivotInvariants`, multi-model rules in Actions, each throwing a named `DomainException`.
- [ ] The feature's `.ai/rules/features/{feature}.md` lists any new term, invariant, Action, Query, or exception.
- [ ] Multi-step writes run in one transaction, and side effects fire after it succeeds.
- [ ] Eloquent models own their state and invariants; services have a focused, reusable responsibility.
- [ ] API resources and Inertia props expose only intentional, explicitly listed data.
- [ ] Ownership and authorization boundaries are enforced, and tests prove another actor cannot reach the record.
- [ ] Every invariant change and bug fix has a regression test recorded as failing without the production change.
- [ ] Feature tests prove observable behavior and persisted state, grouped by feature with a `describe()` block per action.
- [ ] Pages compose partials named for their controller action; payload types come from `app/Http/Props` through `composer types:generate`, never hand-written.
- [ ] Supporting classes exist because they clarify a real responsibility.
