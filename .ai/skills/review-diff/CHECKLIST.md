# Review checklist

The reviewer's standards beyond what `composer ci:check` already enforces. Rows of `.ai/rules/enforcement.md` that name a check are covered by that check; spend review attention on the rest.

- [ ] New code lives in the bounded context whose vocabulary it uses, under `app/Domain/{Context}`.
- [ ] Contexts use each other only through Actions and Query objects; `ArchitectureTest` passes.
- [ ] Each Action describes one clear use case and receives only the explicit typed values it needs.
- [ ] Controllers coordinate HTTP rather than own application workflows; every protected action calls `Gate::authorize()` first; every read goes through a Query object.
- [ ] Routes carry no permission middleware, and Form Requests validate and normalize input only.
- [ ] Invariants hold independently of actor authorization: aggregate rules as `ensure...()` model methods called from `guardCreate()`/`guardUpdate()`/`guardDelete()` with `GuardsInvariants`, relationship rules on a pivot with `GuardsPivotInvariants`, cross-aggregate rules in Actions, each throwing a named `DomainException`.
- [ ] The context's `.ai/rules/contexts/{context}.md` lists any new term, invariant, or public class.
- [ ] Multi-step writes run in one transaction, and side effects fire after it succeeds.
- [ ] Eloquent models remain the aggregates; services have a focused, reusable responsibility.
- [ ] API resources and Inertia props expose only intentional, explicitly listed data.
- [ ] Ownership and authorization boundaries are enforced, and tests prove another actor cannot reach the record.
- [ ] Feature tests prove observable behavior and persisted state, grouped by context with a `describe()` block per action.
- [ ] Pages compose partials named for their controller action; shared prop types live in `types/{feature}.ts`.
- [ ] Supporting classes exist because they clarify a real responsibility.
