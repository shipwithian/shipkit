---
paths:
    - 'routes/**'
---

# Routes

## Keep permission checks out of route middleware

Retain authentication and verification middleware on protected routes, but do not add permission middleware. Controllers invoke policies for authorization.

## Point routes to controllers

Routes reference controller actions. Do not define route closures that contain logic; use an invokable controller for a single endpoint.

## Throttle API routes that change state

Every API route that creates, updates, or deletes something carries `throttle` middleware: a named limiter for the sensitive public flows (login, registration, password reset), and `throttle:api-write` for authenticated writes. Name routes `api.v1.{resource}.{action}`. `tests/Feature/ApiRouteConventionsTest.php` fails on an unthrottled write.

## Look up API records through the actor

An API route does not bind a model from its URL. Take the id and resolve it through the actor's relation or a Query scoped to the actor, so another user's record is indistinguishable from a missing one and answers 404. `ApiRouteConventionsTest` fails on a route-bound model unless the route is listed with a reason.
