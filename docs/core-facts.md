# Core facts OpenImport relies on

Checked during M0 against Magento Open Source 2.4.7, 2.4.8 and 2.4.9 source code and CI. Each line says what
OpenImport does with it.

| Fact | Consequence for OpenImport |
|---|---|
| The REST import endpoints `/V1/import/csv` and `/V1/import/json` (modules ImportCsvApi / ImportJsonApi) are **not part of Magento Open Source 2.4.9**; Adobe documents them for Adobe Commerce. | Starting an import remotely is a real gap on Open Source. OpenImport's REST trigger for profiles (1.1) fills it. |
| `Magento\ImportExport\Model\Import` validates and imports from any `AbstractSource`; the same instance keeps the validated bunch ids for `importSource()`. | `MappedSource` + `CoreImporter`, see [Core bridge](core-bridge.md). |
| Core's `ProcessingErrorAggregator` is shared (no `shared="false"`). | Cleared before every run. |
| `Import::getValidatedIds()` exists only from 2.4.9. | `CoreImport::getBunchIds()` reads the entity adapter's ids on every version. |
| Scheduled import/export is Adobe Commerce only. | OpenImport's schedules run on Open Source (own cron group). |
| `Export::export()` returns the whole file as a string; the entity adapter writes through a writer (`AbstractAdapter`: `setHeaderCols()`, `writeRow()`, `getContents()`). Admin exports go through the message queue. | OpenImport calls the entity adapter with its own streaming writers (CSV, XLSX, JSON, XML) in its runner; no queue consumer needed (M3). |
| `Magento\Framework\Filesystem\Io\Sftp` logs in with a password only and uses phpseclib 3. `magento/framework` does not declare phpseclib; every Magento project has `phpseclib/phpseclib ^3.0` through the product metapackage. | OpenImport talks to `phpseclib3\Net\SFTP` directly for password and private-key logins and declares `phpseclib/phpseclib ^3.0` (already installed in every store, so no new download). |
| The MSI import entity is `stock_sources` (`Magento_InventoryImportExport`); it does not exist when MSI is disabled. | The Stock & price entity writes through `stock_sources` with MSI and through product `qty` without it. |
| Core's CSV source only opens files inside the Magento root (`PathValidator`). | OpenImport keeps run files under `var/openimport/runs/<id>/`. |
| Core product import time is dominated by URL rewrites and indexers; see [Baseline](baseline.md). | Doctor warns about "Update on Save" indexers; delta mode skips unchanged rows. |
