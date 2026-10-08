---
paths:
    - 'routes/**'
---

# Routes

## Keep permission checks out of route middleware

Retain authentication and verification middleware on protected routes, but do not add permission middleware. Controllers invoke policies for authorization.

## Point routes to controllers

Routes reference controller actions. Do not define route closures that contain logic; use an invokable controller for a single endpoint.
