# Core bridge: how OpenImport writes data

OpenImport never writes catalog, customer or stock tables itself. It prepares rows (read the file, map columns,
transform values, filter rows) and hands them to Magento's own import engine, the one behind
System › Data Transfer › Import. Whatever core would write for an equivalent CSV file is what OpenImport writes.

## The pieces

| Class | Job |
|---|---|
| `Model\Source\MappedSource` | Core's `AbstractSource` over OpenImport's prepared rows. No temporary CSV file: core pulls rows from a generator. Missing columns become empty cells, like an empty CSV cell ("leave unchanged"). |
| `Model\Bridge\CoreImport` | Core's `Import` model plus `getBunchIds()`: the `importexport_importdata` rows this import validated (2.4.9 has `getValidatedIds()`, 2.4.7 and 2.4.8 do not). |
| `Model\Bridge\CoreImporter` | Validate, then (live runs only) add a core import history row, import, invalidate the entity's indexers, and always remove this run's bunches. |
| `Model\Bridge\ImportRequest` / `ImportResult` / `RowError` | What to run, and what came back (counts, errors with 0-based row positions, history id, time). |

## Why the bunches are removed

Core validation stores the validated rows in `importexport_importdata` and later imports the bunches whose ids the
entity adapter collected. A dry run validates and stops, so its bunches would stay in the table. They are deleted by
id at the end of every run, dry or live, and also when core throws.

## Import history

Core's import updates the most recent history row of the current admin user (2.4.7, 2.4.8) or the row named in
`_import_history_id` (2.4.9). A live OpenImport run adds its own history row right before the import and passes its
id, so core's updates land on OpenImport's row on every version, never on someone else's import. The run appears
under System › Data Transfer › Import History with its summary. The history row has no downloadable file yet
(M1 writes the mapped rows there).

## Evidence (filled from CI)

`Test/Integration/Bridge/CoreBridgeTest.php` imports the same rows twice, once through core's CSV source (the admin
path) and once through `MappedSource`, and compares the results field by field:

- simple products: new products with stock, special price, HTML description, disabled status, "Not Visible
  Individually", a store-view row; updates including `__EMPTY__VALUE__`; Replace and Delete;
- customers;
- dry run: nothing written, no bunch left behind;
- invalid rows: reported with their 0-based position; valid rows imported with "skip errors", nothing imported
  with "stop on error";
- import history row written and completed.

| Magento | PHP | Result | Decision |
|---|---|---|---|
| 2.4.7-p10 | 8.2, 8.3 | pending | |
| 2.4.8-p5 | 8.3, 8.4 | pending | |
| 2.4.9 | 8.3, 8.4 | pending | |

The temporary-CSV fallback planned in 02 · Architecture is only built if a version fails here.
