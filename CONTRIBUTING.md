# Contributing

Thank you for helping. A few rules keep this module small and trustworthy.

## Scope

OpenImport's scope per release is defined in the public roadmap. Features outside the current release are welcome as issues first, so the design can be discussed before code is written.

## Clean-room rule

All code must be written from scratch. Do not install, open, copy or adapt code, templates, CSS or assets from paid Magento extensions (import/export or any other), and that includes the free "core" or "lite" packages those vendors publish, even "just to see how they do it". Only Magento Open Source, Mage-OS and other OSL/MIT/GPL code and public documentation may be referenced. No vendor names in code, package names or the admin UI.

## Design rules

- Magento's own import engine writes the data. OpenImport prepares rows (sources, readers, mapping, transforms, filters) and hands them to core. No raw SQL writes into catalog, customer or sales tables.
- Nothing runs inside an admin web request: runs are queued and executed by cron or the CLI.
- Anything that can destroy data is a dry run first, has a limit, and asks for confirmation.

## Workflow

- Branch from `main`, use [Conventional Commits](https://www.conventionalcommits.org/) (`feat:`, `fix:`, `test:`, `docs:`, `chore:`).
- Every change ships with tests (PHPUnit unit and/or Magento integration tests). Import behaviour is tested against core: the same data imported through OpenImport and through core's CSV import must give the same result.
- CI must be green: PHPCS (Magento2 standard), PHPStan level 6, unit, coverage gate and integration tests on Magento 2.4.7, 2.4.8 and 2.4.9.
- Shared helpers come from `iranimij/module-base` (`TypedReader` for configuration, `SafeJson` for JSON).
- Public classes and methods carry PHPDoc; `Api/` interfaces follow strict semver.

## Running the tests locally

From a Magento root where the module is installed:

```bash
vendor/bin/phpcs --standard=Magento2 vendor/iranimij/openimport
vendor/bin/phpstan analyse -c vendor/iranimij/openimport/phpstan.neon
vendor/bin/phpunit -c vendor/iranimij/openimport/phpunit.xml.dist
cd dev/tests/integration && ../../../vendor/bin/phpunit -c phpunit.xml ../../../vendor/iranimij/openimport/Test/Integration
```

The development store is [openimport-dev-env](https://github.com/iranimij/openimport-dev-env).
