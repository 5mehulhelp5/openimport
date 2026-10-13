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
