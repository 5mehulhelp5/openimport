# Baseline: core import speed and the core bridge's overhead

Measured in M0 so every later speed claim compares against Magento's own importer, never against another vendor.

**Method.** `openimport-dev-env/ci/benchmark.php` in the dev-env's Benchmark workflow: Magento Open Source 2.4.9,
PHP 8.3, MariaDB 10.6, OpenSearch 2.12, Redis, no sample data, **all indexers on "Update by Schedule"**, a GitHub
Actions runner (4 vCPU, 16 GB). Datasets are generated deterministically by `ci/generate-datasets.php`:

- `products-10k`: 10,000 simple products, 3 categories each (created on the fly), stock, price, a special price on
  every 7th, an HTML description, plus a German store-view row on every 10th product (11,000 rows);
- `customers-20k`: 20,000 customers with one default address (`customer_composite`).

Each dataset is imported twice through core's CSV source (create, then the same file again = every entity updated,
no value changes) and twice through OpenImport's `MappedSource` (same rows, other SKUs / e-mails / names). All passes
run in one PHP process, so peak memory grows from pass to pass and is shown for orientation only.

## Results (run [38403542694](https://github.com/iranimij/openimport-dev-env/actions/runs/38403542694), 2026-10-13)

| Dataset | Path | Pass | Rows | Created | Updated | Seconds | Rows / min |
|---|---|---|---|---|---|---|---|
| products-10k | core CSV | create | 11,000 | 10,000 | 10 | 87.0 | 7,584 |
| products-10k | core CSV | update | 11,000 | 0 | 11,000 | 89.6 | 7,363 |
| products-10k | OpenImport | create | 11,000 | 10,000 | 10 | 86.1 | 7,670 |
| products-10k | OpenImport | update | 11,000 | 0 | 11,000 | 91.8 | 7,191 |
| customers-20k | core CSV | create | 20,000 | 20,000 | 0 | 24.5 | 49,077 |
| customers-20k | core CSV | update | 20,000 | 0 | 20,000 | 25.2 | 47,623 |
| customers-20k | OpenImport | create | 20,000 | 20,000 | 0 | 26.5 | 45,299 |
| customers-20k | OpenImport | update | 20,000 | 0 | 20,000 | 27.0 | 44,447 |

## What it says

- **The core bridge costs almost nothing:** products −1 % / +2.5 %, customers +8 % / +7 % (reading the CSV in PHP and
  building associative rows). The budget in 02 · Architecture is 15 % for the whole pipeline.
- **Re-importing an unchanged catalog costs as much as creating it** (90 s for 10k products). A nightly supplier
  file usually changes a few percent of the rows: delta mode (M1) skips unchanged rows before core sees them. Target:
  a run with 1 % changed rows takes at most 10 % of a full run.
- **Rate:** about 7,500 product rows or 48,000 customer rows per minute on a CI runner with indexers on schedule.
  Shops with "Update on Save" indexers will be slower; `openimport:doctor` warns about it.
- 50,000 products: see below. Configurable products and the full pipeline (readers, mapping, delta) are measured at
  the end of M1 (memory flat from 10k to 50k is an M1 done-when).

## 50,000 products (run [38403706606](https://github.com/iranimij/openimport-dev-env/actions/runs/38403706606), 2026-10-13)

Same method, `products-50k` (50,000 simple products plus 5,000 German store-view rows = 55,000 rows).

| Path | Pass | Rows | Created | Updated | Seconds | Rows / min |
|---|---|---|---|---|---|---|
| core CSV | create | 55,000 | 50,000 | 50 | 438.6 | 7,523 |
| core CSV | update | 55,000 | 0 | 55,000 | 471.6 | 6,997 |
| OpenImport | create | 55,000 | 50,000 | 50 | 453.8 | 7,272 |
| OpenImport | update | 55,000 | 0 | 55,000 | 497.1 | 6,639 |

- Core's rate stays flat from 10k to 50k (about 7,500 rows per minute): no slowdown with catalog size at this scale.
- Bridge overhead +3.5 % (create) and +5.4 % (update), inside the 15 % budget.
- Peak memory is not comparable between passes here (one process, four passes). The M1 memory check runs each pass in
  its own process.
