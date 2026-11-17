# Catalog and Marketplace publication

Prepared 17 November 2026. **Not yet submitted or approved.**

## Prepared

- Public GPL-3.0-or-later repository and author attribution.
- Release 0.3.0: ZIP and tar.gz with a single `editcontrol/` root and SHA-256 checksums.
- `editcontrol.xml`: English/Spanish descriptions, verified compatibility and download links.
- 40 × 40 logo and screenshot using fictitious fixture data.
- README, changelog, community files, issue templates and CI.
- Two-technician production workflow confirmed on GLPI 11.0.4.

Raw catalog metadata:

https://raw.githubusercontent.com/JuanCarlosAcostaPeraba/glpi-editcontrol-plugin/main/editcontrol.xml

## Technical work before Marketplace review

- Replace general-purpose raw SQL with GLPI database abstraction. The current
  lease service uses raw queries; GLPI 11 publication guidance requires framework
  methods. Preserve concurrency semantics. Document any unavoidable named-lock
  operations for discussion with GLPI reviewers.
- Move configuration-page HTML to Twig templates.
- Run GLPI coding-standard checks and resolve findings before claiming compliance.
- Complete integrated acceptance for permissions/entities, related objects, bulk
  actions, integrations and installed custom plugins.
- Test additional GLPI versions before widening catalog compatibility.
- Internationalize UI strings before advertising interface languages beyond Spanish.

Runtime metadata predates publication and accepts GLPI 11/12. The catalog offers
0.3.0 only for **11.0.4**, the verified version. Harmonize runtime requirements in
a subsequent tested release. This preparation does not change production code.

## Submission

1. Complete the technical work above and publish the corresponding release.
2. Register/sign in at https://plugins.glpi-project.org/ and submit the raw XML URL.
3. Wait for catalog review/publication by Teclib'.
4. Once listed, request Marketplace review from glpi@teclib.com, supplying catalog,
   repository, release, XML, test evidence and protection boundaries.
5. Catalog acceptance does not automatically enable Marketplace installation;
   the GLPI team decides on-premise and/or Cloud availability.

## Draft review email — send after catalog acceptance

Subject: Marketplace review request — Edit Control (editcontrol)

Hello GLPI team,

I would like to request Marketplace review for Edit Control, a community plugin
for coordinating ticket editing with expiring leases, inline handoff and configurable
takeover. Author: Juan Carlos Acosta Perabá.

Repository: https://github.com/JuanCarlosAcostaPeraba/glpi-editcontrol-plugin
Releases: https://github.com/JuanCarlosAcostaPeraba/glpi-editcontrol-plugin/releases
Metadata: https://raw.githubusercontent.com/JuanCarlosAcostaPeraba/glpi-editcontrol-plugin/main/editcontrol.xml

Compatibility, validation evidence and protection boundaries are documented in the
repository. The catalog entry and completed technical checklist will be supplied
with the final request.

Thank you for your review.

## Official source

[GLPI publishing documentation](https://glpi-developer-documentation.readthedocs.io/en/latest/plugins/tutorial.html#publishing-your-plugin)

No registration form or email has been submitted by this preparation.
