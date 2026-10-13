<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Pipeline;

use Iranimij\OpenImport\Model\Pipeline\PreviewBuilder;
use Iranimij\OpenImport\Model\Storage\PreviewRecord;
use PHPUnit\Framework\TestCase;

class PreviewBuilderTest extends TestCase
{
    public function testNewRecordsShowEveryValue(): void
    {
        $record = (new PreviewBuilder())->compare(
            'A',
            ['sku' => 'A', 'name' => 'Shirt', 'price' => '', 'color' => '__EMPTY__VALUE__'],
            null,
            ['sku'],
            7
        );

        self::assertSame(PreviewRecord::NEW, $record->changeType);
        self::assertSame(['name' => [null, 'Shirt']], $record->diff);
        self::assertSame(7, $record->sourceRow);
        self::assertSame('A', $record->identifier);
    }

    public function testChangesFollowCoreRules(): void
    {
        $record = (new PreviewBuilder())->compare(
            'A',
            [
                'sku' => 'A',
                'name' => 'Shirt',
                'price' => '12.50',
                'qty' => '3',
                'description' => '',
                'color' => '__EMPTY__VALUE__',
                'featured' => 'Yes',
                'size' => 'L',
                'unreadable' => 'x',
            ],
            [
                'name' => 'shirt',
                'price' => '12.5',
                'qty' => '2',
                'description' => 'Old text',
                'color' => 'Red',
                'featured' => '1',
                'size' => ' L ',
            ],
            ['sku'],
            2
        );

        self::assertSame(PreviewRecord::CHANGED, $record->changeType);
        self::assertSame(
            ['name' => ['shirt', 'Shirt'], 'qty' => ['2', '3'], 'color' => ['Red', '']],
            $record->diff
        );
    }

    public function testUnchanged(): void
    {
        $record = (new PreviewBuilder())->compare('A', ['sku' => 'A', 'status' => 'No'], ['status' => '0'], ['sku'], 2);

        self::assertSame(PreviewRecord::UNCHANGED, $record->changeType);
        self::assertSame([], $record->diff);
    }

    public function testDeletions(): void
    {
        $builder = new PreviewBuilder();

        self::assertSame(PreviewRecord::DELETED, $builder->deletion('A', true, 3)->changeType);
        self::assertSame(PreviewRecord::UNCHANGED, $builder->deletion('B', false, 4)->changeType);
    }
}
