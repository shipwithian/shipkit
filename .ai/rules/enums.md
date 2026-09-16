---
paths:
  - 'app/Enums/**'
---

# Enums

## Name and reference system permissions consistently
Name consolidated CRUD permissions `manage {plural resource} resource`; give non-CRUD workflows independent verb permissions. Define protected system permission names in `SystemPermission` and reference its cases instead of duplicating strings.
