---
paths:
    - 'routes/**'
---

# Routes

## Keep permission checks out of route middleware

Retain authentication and verification middleware on protected routes, but do not add permission middleware. Controllers invoke policies for authorization.

## Name routes after the resource, never the role that uses it

A URL, a route name, and a page folder say what the page is, never who it is for: `/orders`, `orders.index`, and `pages/orders/`, not `/staff/orders`, `admin.orders.index`, or `pages/staff/`. A role prefix protects nothing. What protects a page is:

- `auth` on the route;
- the policy the controller authorizes with;
- the abilities the page receives to show or hide its links, as the roles, permissions, and users pages do.

When different people see different things on one resource, keep one URL and let its Query scope the list to the actor: a customer sees their own orders, and staff with `view orders` see every order. When they are genuinely different resources, name each for what it is (`/orders` and `/kitchen/queue`), not for who opens it. `AudienceNamingTest` in `shipkit-core` fails on an application route whose path or name has a segment naming an audience (`admin`, `staff`, `manager`, `employee`, the system role names, and `shipkit.architecture.audience_words`) and on a page folder with such a name. Record a path that really is a resource, such as `staff` in an HR app, in `shipkit.architecture.audience_exceptions` with the reason.

## Point routes to controllers

Routes reference controller actions. Do not define route closures that contain logic; use an invokable controller for a single endpoint.

## Throttle API routes that change state

Every API route that creates, updates, or deletes something carries `throttle` middleware: a named limiter for the sensitive public flows (login, registration, password reset), and `throttle:api-write` for authenticated writes. Name routes `api.v1.{resource}.{action}`. `tests/Feature/ApiRouteConventionsTest.php` fails on an unthrottled write.

## Look up API records through the actor

An API route does not bind a model from its URL. Take the id and resolve it through the actor's relation or a Query scoped to the actor, so another user's record is indistinguishable from a missing one and answers 404. `ApiRouteConventionsTest` fails on a route-bound model unless the route is listed with a reason.
