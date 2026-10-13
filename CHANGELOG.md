# Changelog

All notable changes to this project are documented here. The format follows
[Keep a Changelog](https://keepachangelog.com/en/1.1.0/), and versions follow [Semantic Versioning](https://semver.org/).

## [Unreleased]

### Added
- Module skeleton on `iranimij/module-base`: settings under Stores › Configuration › iranimij › OpenImport, ACL
  under System › Data Transfer, English and German translations, CI.
- Core bridge: prepared rows go into Magento's own import engine without a temporary file (`MappedSource`,
  `CoreImporter`): validate-only dry runs that leave nothing behind, live runs recorded in core's import history.
