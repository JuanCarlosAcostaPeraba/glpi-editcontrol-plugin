# Changelog

## [0.3.0] — 2026-11-17

### Added

- Strict editing protection and fuchsia read-only alert for other technicians.
- Direct takeover configurable for administrators or all ticket editors, without a request.
- Audit identifying the previous owner and the technician taking control.
- Generation checks rejecting takeover if the displayed owner has changed.
- Guards for actors, assets, documents, relationships, validations, satisfaction, costs and restoration.
- Same-origin fetch credentials and TinyMCE activity tracking.
- Publication metadata, bilingual documentation, issue templates and automated checks.

### Changed

- Losing control preserves drafts and makes the previous owner's form read-only.
- A tab that loses control does not automatically take it back.

### Validation

- 52 service checks, 17 CGI scenarios and 12 simulated browser scenarios.
- MariaDB temporary-table checks for handoff, takeover and stale credentials.
- Two-technician production workflow confirmed on GLPI 11.0.4 / PHP 8.2.35 / MariaDB 11.8.3.

## [0.2.0] — 2026-11-17

- Inline request notifications and atomic handoff without reloading the page.
- Schema migration to version 2 for requester credentials.

## [0.1.3] — 2026-11-16

- Automatic acquisition, simplified ticket bar and GLPI 11 AJAX CSRF compatibility.

## [0.1.2] — 2026-11-16

- Initial warning-mode production installation with independent leases and audit.

[0.3.0]: https://github.com/JuanCarlosAcostaPeraba/glpi-editcontrol-plugin/releases/tag/0.3.0
