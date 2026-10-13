<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Entity;

use Iranimij\OpenImport\Model\Entity\EntityInfo;
use Iranimij\OpenImport\Model\Entity\EntityRegistry;
use PHPUnit\Framework\TestCase;

class EntityRegistryTest extends TestCase
{
    public function testBuiltInEntities(): void
    {
        $registry = new EntityRegistry();
        $product = $registry->get('catalog_product');

        self::assertNotNull($product);
        self::assertSame(['sku'], $product->identifier);
        self::assertTrue($product->storeViews);
        self::assertSame(['sku', 'attribute_set_code', 'product_type'], $product->storeViewCarry);
        self::assertSame(['email', '_website'], $registry->get('customer')?->identifier);
        self::assertContains('openimport_stock_price', $registry->codes());
        self::assertNull($registry->get('unknown'));
    }

    public function testEntitiesCanBeAddedOrReplaced(): void
    {
        $registry = new EntityRegistry([
            new EntityInfo('company', ['company_email']),
            new EntityInfo('customer', ['email']),
        ]);

        self::assertSame(['company_email'], $registry->get('company')?->identifier);
        self::assertSame(['email'], $registry->get('customer')?->identifier);
    }
}
