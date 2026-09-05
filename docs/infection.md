# `infection.json5.dist`

[← Reference](README.md)

The mutation floor. **Copied and adjusted, not extended** — Infection has no configuration
inheritance, so each repository carries its own file.

```json5
// infection.json5.dist — copy it, adjust `source`, keep the floor
```

## Contents

- [What to keep](#what-to-keep)
- [Why `minMsi` is absent](#why-minmsi-is-absent)

---

## What to keep

| key | value | |
| --- | --- | --- |
| `minCoveredMsi` | `100` | the mandated floor — never lowered to accommodate a survivor |
| `mutators` | `{"@default": true}` | the standard set |
| `source.directories` | `["src"]` | adjust to your tree |

A survivor is killed by strengthening the test, or proven equivalent and annotated at the narrowest
node that isolates it.

**Nothing enforces the number.** Infection obeys whatever it finds, and because the file is copied
rather than inherited, a repository that lowers it is green at the lower value. The coverage floor's
binary hard-floors at 95 and throws below it; the mutation floor has no equivalent.

[↑ Back to top](#infectionjson5dist)

---

## Why `minMsi` is absent

Mandating it would silently mandate literal-100% line coverage as well, which is a different
decision and belongs to `.coverage-floor`. A repository already at literal 100% may set it.

[↑ Back to top](#infectionjson5dist)
