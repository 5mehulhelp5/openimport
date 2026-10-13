# PROGRESS

Fine-grained checkpoint for the OpenImport build. Updated after every slice. The Notion build log
(07 · Build Brief, section 10) holds the per-milestone summary. GitHub: the working-hours rule does **not** apply to
OpenImport (Iman, 2026-10-13); every slice is pushed as soon as it is done.

Work split (Iman, 2026-10-13): code, CI and Notion run in cloud threads; browser checks and Playwright run locally on
Iman's Mac (`~/Projects/openimport-dev-env`, Warden) whenever the laptop is open. Local testing never blocks cloud work.

## Done

- **M0 · Foundation + core-bridge spike** (2026-10-13): skeleton, CI on 7 Magento/PHP pairs, core bridge with oracle
  tests (#1, #2, #3), dev-env with Warden fixture, Playwright baseline and benchmark workflow (dev-env #1), baselines
  in `docs/baseline.md` (10k/20k/50k).

## Current milestone

**M1 · Engine** (started 2026-10-13).

| # | Slice | State |
|---|---|---|
| S1 | Readers: CSV/TSV, XLSX (own), JSON, XML, format detection, `docs/file-formats.md`; 50k baseline | PR #4 |
| S2 | Transforms and the safe expression language, value maps, number parsing, `docs/transforms.md` | PR open: `feat/m1-s2-transforms` |
| S3 | Mapping, row filters, store-view expansion, behaviours (only add / only update) | open |
| S4 | Schema: profile, run, run_error, run_preview, row_hash, lock; repositories | open |
| S5 | Pipeline runner, errors mapped to source rows, dry-run preview, delta hashes | open |
| S6 | `openimport_stock_price` entity (MSI or legacy stock) | open |
| S7 | CLI: `openimport:run`, `openimport:profile:list`, `openimport:run:status` | open |
| S8 | Oracle integration tests, coverage gate on the engine, 50k memory check, close | open |

## Exact next step

Merge S1 and S2 when CI is green; S3 (mapping, row filters, store views, behaviours) on `feat/m1-s3-mapping`.

## Open questions

- D3 (01 · Decisions First): own XLSX reader/writer (recommended) or openspout. Default until answered: own reader.
- Repository settings (squash only, auto-merge, delete branch on merge, `main` protected with `ci-ok`) cannot be
  written from cloud threads (proxy refuses settings writes): Iman sets them once, or the repo-factory workflow does.

## Failing or skipped tests

None.
