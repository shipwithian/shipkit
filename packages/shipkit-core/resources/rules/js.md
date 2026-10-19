---
paths:
    - 'resources/js/**'
---

# Js

## Drive frontend access control from auth permissions

Use `auth.permissions` for navigation and control visibility; never infer access from role names. Treat frontend checks as interface behavior only, with backend policies remaining authoritative.

## Use the shared Auth permission contract

The shared `Auth` contract exposes the authenticated user and direct plus role-inherited permission names in `permissions: string[]`. Frontend checks use the exact permission names supplied by the backend. Feature permission names may originate from feature seeders and are not expected to exist in `SystemPermission`.

## Organize pages by controller action

A file at `pages/{feature}/{action}.tsx` is a routable page named after the controller action that renders it: `index`, `create`, `edit`, or `show`. A page that serves more than one action, such as an index with create, edit, and delete, reads its props and composes partials; it does not hold form or table markup. A single-form page, such as an auth screen, may hold its own form.

## Use full pages when the feature edits on its own screens

A feature that creates and edits on dedicated screens instead of in dialogs uses `index.tsx`, `create.tsx`, `show.tsx`, and `edit.tsx`, and shares the form fields between create and edit through `partials/{model}-fields.tsx`. Dialog-based features such as roles keep `create-`, `edit-`, and `delete-{model}-dialog.tsx` partials.

## Keep page pieces in a partials folder

Place a feature's pieces in `pages/{feature}/partials/`, named for the action they submit to: `create-{model}-dialog.tsx` (store), `edit-{model}-dialog.tsx` (update), `delete-{model}-dialog.tsx` (destroy), and `{models}-table.tsx` (index list). A partial for a non-CRUD route is named for its action, such as `leave-team-dialog.tsx`, and a shared piece within the feature for what it shows, such as `team-role-select.tsx`. Partials use named exports and are never passed to `Inertia::render()`. A component used by a single page or feature lives in that feature's `partials/`. Move it to `components/` only when a second feature uses it; `components/ui` is for primitives.

## Share prop types per feature

Declare the types of server-provided props in `resources/js/types/{feature}.ts` and export them through `@/types`. Do not redeclare a model's type in each page.
