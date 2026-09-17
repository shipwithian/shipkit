---
paths:
    - 'app/Enums/**'
---

# Enums

## Reserve SystemPermission for access-control bootstrap

Reserve `SystemPermission` for protected bootstrap permissions required to administer roles and permissions and prevent access-control deadlocks. Ordinary feature and project permissions belong to their owning feature seeders, not this enum.
