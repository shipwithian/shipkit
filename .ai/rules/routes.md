---
paths:
  - 'routes/**'
---

# Routes

## Keep permission checks out of route middleware
Retain authentication and verification middleware on protected routes, but do not add permission middleware. Controllers invoke policies for authorization.
