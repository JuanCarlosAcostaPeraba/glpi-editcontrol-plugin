# Edit Control for GLPI

[![Checks](https://github.com/JuanCarlosAcostaPeraba/glpi-editcontrol-plugin/actions/workflows/checks.yml/badge.svg)](https://github.com/JuanCarlosAcostaPeraba/glpi-editcontrol-plugin/actions/workflows/checks.yml)
[![Release](https://img.shields.io/github/v/release/JuanCarlosAcostaPeraba/glpi-editcontrol-plugin)](https://github.com/JuanCarlosAcostaPeraba/glpi-editcontrol-plugin/releases/latest)
[![License: GPL v3+](https://img.shields.io/badge/License-GPLv3%2B-blue.svg)](LICENSE)

**0.3.1 — corrective release candidate; integrated retest pending.**

Coordinate ticket editing in GLPI: see who has control, request it, hand it over,
or take it directly when your permissions and the configured policy allow it.

**[Documentación en español](docs/README.es.md)** ·
[Download](https://github.com/JuanCarlosAcostaPeraba/glpi-editcontrol-plugin/releases/latest) ·
[Changelog](CHANGELOG.md) · [Report an issue](https://github.com/JuanCarlosAcostaPeraba/glpi-editcontrol-plugin/issues)

![Strict mode: blocked technicians can request or take control](docs/images/strict-mode.png)

*Actual plugin JavaScript and CSS with fictitious users and a simulated server.*

## Features

- Automatic control acquisition by the first eligible visible ticket tab.
- Current technician displayed in the ticket, with request and inline handoff.
- Strict mode: other technicians can read and request control; protected writes require the owning tab's credentials.
- Fuchsia alert and read-only fields for blocked technicians; drafts remain when control changes.
- Configurable direct takeover: authorized administrators, or all technicians allowed to edit that ticket.
- Persistent control without idle expiry; request timeout, activity heartbeats and audit history.
- A compact floating indicator when the main bar scrolls out of view, with hover, keyboard and click access.
- Independent plugin tables, no GLPI core modifications and no email dependency.

## Compatibility

| Component | Verified environment |
|---|---|
| GLPI | **11.0.4** |
| PHP | **8.2.35** (production), 8.5.6 (local tests) |
| Database | MariaDB **11.8.3**, single writer with session affinity |
| Interface language | Spanish |

Version 0.3.0 passed an initial two-technician workflow on 17 November 2026.
Later reports identified idle lockout and task-editor problems; the plugin was
disabled. Version 0.3.1 addresses these regressions and adds real TinyMCE tests
with a 300-task timeline. Integrated retesting on the affected tickets is pending.
The existing runtime accepts GLPI 11 and 12; **GLPI 12 is unverified**. Catalog
metadata deliberately limits distribution to the verified GLPI 11.0.4 version.
GLPI 10 is unsupported. Broader support requires integrated testing.

## Installation

1. Back up GLPI files and database.
2. Download `editcontrol-0.3.1.tar.gz` or `.zip` from the release and verify its SHA-256 checksum.
3. Extract into GLPI's `plugins` directory. The path must be `plugins/editcontrol/setup.php`.
4. Disable native ObjectLock for Ticket; the plugin refuses that coexistence.
5. Install and enable **Control de edición** in Setup → Plugins.
6. Open its configuration. Fresh installs default to **warning mode**, a 120-second request timeout and administrators-only direct takeover.
7. Validate your workflows, then select strict mode and the desired takeover policy.
8. Reload existing ticket tabs once after an update, preserving unsaved text first.

Control does not expire when a tab is idle, hidden or suspended. Navigation/close
and successful protected saves attempt to release it. After a crash or failed
release, another authorized technician can take control directly. GLPI session
and permission checks still apply. A former owner can explicitly recover a free,
unchanged ticket without needing takeover permission.

Use the release asset; GitHub's automatic source archives have the repository's
directory name and cannot be extracted directly into GLPI as an installable plugin.

To update, deactivate the plugin, replace its files, run GLPI's installation/update
action and reactivate. Back up both files and database first. See [maintenance](docs/MANTENIMIENTO.md).

## Protection boundaries

Strict mode covers central-interface CommonDBTM hooks for Ticket, followups,
tasks, solutions, actors, linked assets/documents, validations, satisfaction,
costs, ticket relationships and restoration. GLPI permissions remain mandatory.
Acquiring control requires UPDATE permission on the ticket.

Official API, CLI and self-service writes do not require the browser token; they
invalidate the revision of central-interface forms. Direct SQL, third-party
classes and cascades bypassing hooks are not fully covered. Bulk actions without
per-ticket credentials are rejected in strict mode. Warning mode allows writes
without control. Read the [known limitations](docs/LIMITACIONES.md) before deployment.

## Development and validation

```sh
php -d extension=pdo_sqlite tests/run.php
php tests/run-guards.php  # requires php-cgi and pdo_sqlite
node --check public/js/editcontrol.js
python tools/package.py
python tools/validate-release.py
```

Service/concurrency and CGI guard suites complement 12 original browser scenarios,
large-ticket regressions with real TinyMCE, and free-ticket recovery checks.
[Test instructions](tests/README.md) distinguish simulations from integrated checks.

## Community and distribution

[Contributing](CONTRIBUTING.md) · [Support](SUPPORT.md) · [Security policy](SECURITY.md) ·
[Marketplace checklist](docs/MARKETPLACE.md)

Independent community plugin. Catalog registration and Marketplace approval are
pending. The checklist records remaining technical work, including GLPI database
abstraction and Twig templates.

**Author:** Juan Carlos Acosta Perabá. **License:** [GPL-3.0-or-later](LICENSE).
