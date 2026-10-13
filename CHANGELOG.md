# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Module skeleton on `iranimij/module-base`: settings under Stores › Configuration › iranimij › OpenImport, ACL
  under System › Data Transfer, English and German translations, CI.
- Core bridge: prepared rows go into Magento's own import engine without a temporary file (`MappedSource`,
  `CoreImporter`): validate-only dry runs that leave nothing behind, live runs recorded in core's import history,
  core's shared error aggregator cleared per run. Proven equal to core's own CSV import on Magento 2.4.7, 2.4.8 and
  2.4.9.
- File readers for CSV/TSV (delimiter and encoding detection, UTF-16, Windows-1252), Excel .xlsx (own streaming
  reader, dates, sheet choice), JSON (record path, nested fields as dotted columns) and XML (streaming, record path,
  attributes, no DOCTYPE); format detection from the file name or content. See docs/file-formats.md.
- Transforms for mapped fields (text, regex, HTML stripping, numbers with decimal commas, math, rounding with price
  endings, price adjustment, dates, split/join, value maps) and a safe expression language with columns, arithmetic,
  comparisons and 14 functions, parsed once per run without eval. Own transforms can be added through di.xml. See
  docs/transforms.md.
- Mapping engine: source columns, fixed values, transform chains, required fields, identifier checks, store-view
  values as extra rows (with SKU, attribute set and type carried), auto-match with German and English synonyms and
  store-view column names; row filters (include/exclude, all/any, 13 operators or expressions). See docs/mapping.md.
