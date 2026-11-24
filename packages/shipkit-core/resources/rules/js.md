---
paths:
    - 'resources/js/**'
---

# Js

## Gate controls on server-computed abilities

Show a control only when the page's props say the actor may use it: `can.create` on the page, `role.can.update` on a row, `auth.can.viewRoles` in the shared props. The server computes each ability with the Gate from the same policy ability the controller authorizes, so the UI never shows a control the server refuses. Never decide visibility from permission or role names; the server does not send them, and `FrontendAbilitiesTest` fails on `permissions.includes('...')` and its variants. A control the server refuses for a reason outside the policy, such as a protected record's invariant, may also hide on the record's own fields (`!role.is_protected`).

## Use the shared Auth contract

The shared `auth` prop (`AuthProps`) holds the signed-in user, `null` for a guest, and `can`, the navigation abilities. A page a guest can reach handles `auth.user === null`; a page behind `auth` reads the user with `useAuthUser()`.

## Build screens from shadcn/ui primitives

Every control and data display comes from the shadcn/ui primitives in `resources/js/components/ui` (configured by `components.json`): `Button`, `Input`, `Label`, `Select`, `Textarea`, `Checkbox`, `RadioGroup`, `Table`, `Dialog`, and the rest. Never hand-build a `<button>`, `<input>`, `<label>`, `<select>`, `<textarea>`, `<table>`, or `<dialog>` in a page, partial, or component; a hidden form field is the one exception, because it has no look.

- When the primitive you need is missing, add it with the shadcn CLI, for example `npx shadcn@latest add table`, and commit the file it creates. Never write a look-alike by hand.
- Adapt a primitive through its variants (`variant="ghost"`, `size="icon"`) and `className`, which `cn()` merges over its defaults. Never fork it inside a page.
- Change a primitive itself in `components/ui` only for a change the whole app should share.

`FrontendPrimitivesTest` in `shipkit-core` fails on a hand-built element outside `components/ui`, naming the primitive to use and, when the project does not have it yet, the command that adds it. Record a file that genuinely needs raw markup, such as a printable receipt, in `shipkit.architecture.raw_element_exceptions` with the reason.

## Build list pages from the list components

A page that lists records (an `index` page) is built from the same pieces, so every list searches, filters, and pages the same way:

- `ListToolbar` from `@/components/list-toolbar`: a search box that searches as you type, with the page's filters as its children;
- `ListFilter` from `@/components/list-filter`: one choice of options, or all of them;
- `useListFilters(filters)` from `@/hooks/use-list-filters`: `apply({ search })` reloads the page with the filters in the URL, so a filtered list can be shared, bookmarked, and paged;
- the feature's table partial, built from `Table`, with an empty message that says when filters hide every row;
- `ListPagination` from `@/components/list-pagination` under a paginated list.

The page receives the filters it was rendered with as a `filters` prop (see the `responses` rule) and passes them straight to `useListFilters`. Settings → Users is the example: search by name or email, a role filter, and pagination.

## Organize pages by controller action

A file at `pages/{feature}/{action}.tsx` is a routable page named after the controller action that renders it, in its feature's folder and never an audience's such as `pages/staff/` or `pages/admin/` (see the `routes` rule): `index`, `create`, `edit`, or `show`. A page that serves more than one action, such as an index with create, edit, and delete, reads its props and composes partials; it does not hold form or table markup. A single-form page, such as an auth screen, may hold its own form.

## Keep files short by splitting them as they grow

A page stays at or under 200 lines and any other frontend file at or under 400; `FrontendFileLengthTest` fails above that. Split before you reach the limit, not after: move each section, dialog, table, or form into its own partial in the feature's `partials/` folder, and the page's state and handlers into a `use-{feature}.ts` hook beside them, so the page only reads its props and composes the pieces. The vendored primitives in `components/ui` are exempt. A file that must stay long goes in `shipkit.architecture.long_frontend_files` in `config/shipkit.php` with the reason.

## Use full pages when the feature edits on its own screens

A feature that creates and edits on dedicated screens instead of in dialogs uses `index.tsx`, `create.tsx`, `show.tsx`, and `edit.tsx`, and shares the form fields between create and edit through `partials/{model}-fields.tsx`. Dialog-based features such as roles keep `create-`, `edit-`, and `delete-{model}-dialog.tsx` partials.

## Keep page pieces in a partials folder

Place a feature's pieces in `pages/{feature}/partials/`, named for the action they submit to: `create-{model}-dialog.tsx` (store), `edit-{model}-dialog.tsx` (update), `delete-{model}-dialog.tsx` (destroy), and `{models}-table.tsx` (index list). A partial for a non-CRUD route is named for its action, such as `leave-team-dialog.tsx`, and a shared piece within the feature for what it shows, such as `team-role-select.tsx`. Partials use named exports and are never passed to `Inertia::render()`. A component used by a single page or feature lives in that feature's `partials/`. Move it to `components/` only when a second feature uses it; `components/ui` is for primitives.

## Import payload types generated from PHP

Every server-provided prop type is generated from its PHP props class into `resources/js/types/generated.ts` and exported through `@/types`. A page types its props as `{Page}Props` and reads shared props from `usePage().props`, which is typed as `SharedProps`. Hand-written files in `resources/js/types` hold only client-side types, such as layout props or a JSON response from a Fortify endpoint. To change a payload, change the PHP class and run `composer types:generate`; a partial that needs part of a payload takes a `Pick<>` of the generated type.
