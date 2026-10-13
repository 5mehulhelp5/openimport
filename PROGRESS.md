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
| S1 | Skeleton on module-base: settings section `openimport` (runs, safety), ACL under System › Data Transfer, `Model\Config`, en_US + de_DE, MIT headers, NOTICE, mkdocs, CI (PHPCS, PHPStan 6, coverage gate, unit + integration on 7 Magento/PHP pairs with OpenSearch, docs) | merged: #1 |
| S2 | Core-bridge spike: `MappedSource` + `CoreImporter`, oracle tests vs core CSV import (products, customers, updates, Replace, Delete, dry run, skip/stop on error, history) | merged: #2, green on 2.4.7 / 2.4.8 / 2.4.9 |
| S3 | `openimport-dev-env`: Warden fixture, SFTP + HTTP services, dataset generator, Playwright admin baseline, E2E workflow | dev-env PR #1, E2E green |
| S4 | Baselines (benchmark workflow), `docs/baseline.md`, `docs/core-facts.md` | in progress: `feat/m0-close` |
| S5 | Local browser and Playwright check on the Mac | done 2026-10-13 14:50 (3/4, two test fixes taken into dev-env); rerun after the next sync |
| S6 | Close: build log, M0 report | open |

## Exact next step

When the 10k/20k and 50k benchmark runs finish: write `docs/baseline.md`, open the `feat/m0-close` PR, merge dev-env
PR #1 (set `.github/ci/openimport-ref` back to `main` first), M0 report. Then M1 in a new thread.

## Open questions

- D3 (01 · Decisions First): own XLSX reader/writer (recommended) or openspout. Default until answered: own reader.
- Repository settings (squash only, auto-merge, delete branch on merge, `main` protected with `ci-ok`) cannot be
  written from cloud threads (proxy refuses settings writes): Iman sets them once, or the repo-factory workflow does.

## Failing or skipped tests

None.
