# PROGRESS

Fine-grained checkpoint for the OpenImport build. Updated after every slice. The Notion build log
(07 · Build Brief, section 10) holds the per-milestone summary. GitHub: the working-hours rule does **not** apply to
OpenImport (Iman, 2026-10-13); every slice is pushed as soon as it is done.

Work split (Iman, 2026-10-13): code, CI and Notion run in cloud threads; browser checks and Playwright run locally on
Iman's Mac (`~/Projects/openimport-dev-env`, Warden) whenever the laptop is open. Local testing never blocks cloud work.

## Current milestone

**M0 · Foundation + core-bridge spike** (started 2026-10-13 14:10).

| # | Slice | State |
|---|---|---|
| S1 | Skeleton on module-base: settings section `openimport` (runs, safety), ACL under System › Data Transfer, `Model\Config`, en_US + de_DE, MIT headers, NOTICE, mkdocs, CI (PHPCS, PHPStan 6, unit × 7, coverage gate, integration × 6 with OpenSearch, docs) | pushed: PR #1 `feat/m0-s1-skeleton`, CI running |
| S2 | Core-bridge spike: `MappedSource` + `CoreImporter`, oracle tests vs core CSV import (products, customers), error rows, history; `docs/core-bridge.md` | pushed: `feat/m0-s2-core-bridge` (stacked on S1), CI running |
| S3 | `openimport-dev-env`: Warden fixture, SFTP + HTTP services, dataset generator, Playwright admin smoke, e2e workflow | open |
| S4 | Baselines: benchmark workflow (10k / 50k products, 20k customers), `docs/baseline.md`, `docs/core-facts.md` | open |
| S5 | Local browser and Playwright check on the Mac (non-blocking) | open |
| S6 | Close: branch protection, merge, build log, M0 report | open |

## Exact next step

Watch CI on PR #1 and the S2 PR; fill the evidence table in `docs/core-bridge.md` from the integration matrix. Then S3 (dev-env).

## Open questions

- D3 (01 · Decisions First): own XLSX reader/writer (recommended) or openspout. Default until answered: own reader.

## Failing or skipped tests

None yet.
