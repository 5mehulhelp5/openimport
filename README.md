# OpenImport

Import and export for Magento 2 and Mage-OS: profiles, column mapping, value transforms, a dry run that shows every
change before anything is written, schedules, and remote sources (SFTP, FTP, URL). Built on Magento's own import
engine. Free forever, MIT, no licence keys, no telemetry.

> **Status: in development.** Milestone M0 (foundation). The first release, 1.0.0, is planned for the end of
> October 2026. Watch the repository or the [milestones](https://github.com/iranimij/openimport/milestones).

## What 1.0 will do

- Saved import and export profiles, run by hand, on a schedule, or from the command line.
- Sources and destinations: upload, server folder, HTTP(S) URL (including published Google Sheets), SFTP, FTP.
- CSV, XLSX, JSON and XML files, read as streams.
- Map your file's columns to Magento fields, map values (`rot` → `red`), transform them (trim, replace, decimal
  comma, dates, maths, rounding, price adjustments), filter rows, and put store-view columns like `name_de` where they
  belong.
- A dry run that shows, field by field, what would change. New profiles always start with one.
- Only changed rows are imported when you turn delta mode on.
- Products, advanced pricing, customers, addresses and MSI stock through Magento's own importer, a fast stock and
  price update, and an order export.
- Run history with counts, an error report with the line numbers of your own file, and email when something fails.

## Requirements

Magento Open Source 2.4.7, 2.4.8 or 2.4.9 (or Mage-OS), PHP 8.2 to 8.4.
Adobe Commerce should work but is not tested.

## Support

Issues are triaged within 7 days. There is no SLA. Please attach a 10-row sample of your file.

## Licence

MIT. See [LICENSE](LICENSE) and [NOTICE](NOTICE).
