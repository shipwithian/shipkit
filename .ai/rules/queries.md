---
paths:
    - 'app/Queries/**'
---

# Queries

## Own one read per Query object

A Query object lives in `app/Queries/{Feature}/`, is named for what it returns, such as `{Model}ListQuery`, and exposes a single `handle()` method. It builds the Eloquent query behind one page, list, or API response.

## Accept trusted values and return models

Pass the trusted actor, parent model, and typed filters; never a Form Request. Apply ownership, tenant, filtering, and ordering constraints, and eager load the relationships and counts the response needs. Return models, a collection, or a paginator, not arrays shaped for the client.

## Never write

Query objects do not create, update, or delete records and do not dispatch side effects.
