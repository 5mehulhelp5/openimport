<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model;

use Iranimij\Base\Model\Config\TypedReader;
use Iranimij\OpenImport\Model\Config;
use Magento\Framework\App\Config\ScopeConfigInterface;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    /**
     * @param array<string, mixed> $values
     * @return Config
     */
    private function config(array $values): Config
    {
        $scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $scopeConfig->method('getValue')->willReturnCallback(
            static fn (string $path): mixed => $values[$path] ?? null
        );
        $scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn (string $path): bool => (bool) ($values[$path] ?? false)
        );

        return new Config(new TypedReader($scopeConfig));
    }

    public function testDefaultsWhenNothingIsSet(): void
    {
        $config = $this->config([]);
        self::assertSame(14, $config->getKeepFilesDays());
        self::assertSame(10000, $config->getMaxErrorsPerRun());
        self::assertSame(240, $config->getCronTimeBudget());
        self::assertSame([], $config->getAllowedHosts());
        self::assertSame([], $config->getAllowedFolders());
        self::assertFalse($config->isFormulaGuardEnabled());
        self::assertSame(10, $config->getSafetyThresholdPercent());
    }

    public function testNumbersAreReadAndClamped(): void
    {
        $config = $this->config([
            Config::XML_KEEP_FILES_DAYS => '0',
            Config::XML_MAX_ERRORS_PER_RUN => ' 250 ',
            Config::XML_CRON_TIME_BUDGET => '3',
            Config::XML_SAFETY_THRESHOLD => '500',
        ]);
        self::assertSame(1, $config->getKeepFilesDays());
        self::assertSame(250, $config->getMaxErrorsPerRun());
        self::assertSame(10, $config->getCronTimeBudget());
        self::assertSame(100, $config->getSafetyThresholdPercent());
        self::assertSame(1, $this->config([Config::XML_SAFETY_THRESHOLD => '-4'])->getSafetyThresholdPercent());
    }

    public function testNonNumericValuesFallBack(): void
    {
        $config = $this->config([Config::XML_KEEP_FILES_DAYS => 'many', Config::XML_SAFETY_THRESHOLD => '']);
        self::assertSame(14, $config->getKeepFilesDays());
        self::assertSame(10, $config->getSafetyThresholdPercent());
    }

    public function testHostsAreLinesInLowerCaseWithoutDuplicates(): void
    {
        $config = $this->config([Config::XML_ALLOWED_HOSTS => "Files.Supplier.LAN\r\n\n  erp.local \nfiles.supplier.lan"]);
        self::assertSame(['files.supplier.lan', 'erp.local'], $config->getAllowedHosts());
    }

    public function testFoldersMustBeAbsoluteAndLoseTrailingSlashes(): void
    {
        $config = $this->config([Config::XML_ALLOWED_FOLDERS => "/srv/feeds/\nrelative/path\n/mnt/erp//"]);
        self::assertSame(['/srv/feeds', '/mnt/erp'], $config->getAllowedFolders());
    }

    public function testFormulaGuardFlag(): void
    {
        self::assertTrue($this->config([Config::XML_FORMULA_GUARD => '1'])->isFormulaGuardEnabled());
    }
}
