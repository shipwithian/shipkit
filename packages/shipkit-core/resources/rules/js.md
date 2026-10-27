---
paths:
    - 'resources/js/**'
---

# Js

## Gate controls on server-computed abilities

Show a control only when the page's props say the actor may use it: `can.create` on the page, `role.can.update` on a row, `auth.can.viewRoles` in the shared props. The server computes each ability with the Gate from the same policy ability the controller authorizes, so the UI never shows a control the server refuses. Never decide visibility from permission or role names; the server does not send them, and `FrontendAbilitiesTest` fails on `permissions.includes('...')` and its variants. A control the server refuses for a reason outside the policy, such as a protected record's invariant, may also hide on the record's own fields (`!role.is_protected`).

## Use the shared Auth contract

The shared `auth` prop (`AuthProps`) holds the signed-in user, `null` for a guest, and `can`, the navigation abilities. A page a guest can reach handles `auth.user === null`; a page behind `auth` reads the user with `useAuthUser()`.

## Organize pages by controller action

A file at `pages/{feature}/{action}.tsx` is a routable page named after the controller action that renders it: `index`, `create`, `edit`, or `show`. A page that serves more than one action, such as an index with create, edit, and delete, reads its props and composes partials; it does not hold form or table markup. A single-form page, such as an auth screen, may hold its own form.

## Keep files short by splitting them as they grow

A page stays at or under 200 lines and any other frontend file at or under 400; `FrontendFileLengthTest` fails above that. Split before you reach the limit, not after: move each section, dialog, table, or form into its own partial in the feature's `partials/` folder, and the page's state and handlers into a `use-{feature}.ts` hook beside them, so the page only reads its props and composes the pieces. The vendored primitives in `components/ui` are exempt. A file that must stay long goes in `shipkit.architecture.long_frontend_files` in `config/shipkit.php` with the reason.

## Use full pages when the feature edits on its own screens

A feature that creates and edits on dedicated screens instead of in dialogs uses `index.tsx`, `create.tsx`, `show.tsx`, and `edit.tsx`, and shares the form fields between create and edit through `partials/{model}-fields.tsx`. Dialog-based features such as roles keep `create-`, `edit-`, and `delete-{model}-dialog.tsx` partials.

## Keep page pieces in a partials folder

Place a feature's pieces in `pages/{feature}/partials/`, named for the action they submit to: `create-{model}-dialog.tsx` (store), `edit-{model}-dialog.tsx` (update), `delete-{model}-dialog.tsx` (destroy), and `{models}-table.tsx` (index list). A partial for a non-CRUD route is named for its action, such as `leave-team-dialog.tsx`, and a shared piece within the feature for what it shows, such as `team-role-select.tsx`. Partials use named exports and are never passed to `Inertia::render()`. A component used by a single page or feature lives in that feature's `partials/`. Move it to `components/` only when a second feature uses it; `components/ui` is for primitives.

## Import payload types generated from PHP

Every server-provided prop type is generated from its PHP props class into `resources/js/types/generated.ts` and exported through `@/types`. A page types its props as `{Page}Props` and reads shared props from `usePage().props`, which is typed as `SharedProps`. Hand-written files in `resources/js/types` hold only client-side types, such as layout props or a JSON response from a Fortify endpoint. To change a payload, change the PHP class and run `composer types:generate`; a partial that needs part of a payload takes a `Pick<>` of the generated type.
