# Contributing

Issues and pull requests in Spanish or English are welcome. Discuss substantial
changes in an issue first. Branch from `develop` and target pull requests there.
`main` contains published release work.

Include the problem, expected behavior, versions and reproduction steps. Use
fictitious data and redact credentials, personal data and internal hostnames.
Security issues belong in private reporting: see SECURITY.md.

Preserve GLPI permissions, CSRF checks, lease ownership, fencing generations and
revision checks. Never modify GLPI core. Use GLPI's supported database API and Twig
for new work. Explain schema migrations and rollback implications.

Run README checks and relevant browser scenarios from tests/README.md. Guard and
concurrency changes require service and CGI checks. Integrated GLPI checks are
required before widening compatibility metadata. Distinguish simulations from
integrated evidence. Contributions use the project's GPL-3.0-or-later license.
