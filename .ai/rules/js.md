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

A file at `pages/{feature}/{action}.tsx` is a routable page named after the controller action that renders it: `index`, `create`, `edit`, or `show`. A page reads its props and composes partials; it does not hold form or table markup.

## Keep page pieces in a partials folder

Place a feature's pieces in `pages/{feature}/partials/`, named for the action they submit to: `create-{model}-dialog.tsx` (store), `edit-{model}-dialog.tsx` (update), `delete-{model}-dialog.tsx` (destroy), and `{models}-table.tsx` (index list). Partials use named exports and are never passed to `Inertia::render()`. Move a partial to `components/` only when a second feature uses it; `components/ui` is for primitives.

## Share prop types per feature

Declare the types of server-provided props in `resources/js/types/{feature}.ts` and export them through `@/types`. Do not redeclare a model's type in each page.
