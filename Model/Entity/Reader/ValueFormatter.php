<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Entity\Reader;

use Iranimij\OpenImport\Model\Value\NumberText;
use Magento\Eav\Model\Entity\Attribute\AbstractAttribute;

/**
 * Turns stored EAV values into the text an import file would carry (option labels, plain numbers, dates).
 */
class ValueFormatter
{
    private NumberText $numbers;

    public function __construct()
    {
        $this->numbers = new NumberText();
    }

    /**
     * @param AbstractAttribute $attribute
     * @param mixed $value
     * @return string
     */
    public function format(AbstractAttribute $attribute, mixed $value): string
    {
        if ($value === null || $value === false || $value === '') {
            return '';
        }
        $input = (string) $attribute->getFrontendInput();
        if ($attribute->usesSource() && in_array($input, ['select', 'multiselect', 'boolean'], true)) {
            $labels = [];
            foreach (explode(',', (string) $value) as $optionId) {
                $label = $attribute->getSource()->getOptionText(trim($optionId));
                $labels[] = is_array($label) ? implode(',', array_map('strval', $label)) : (string) $label;
            }
            return implode(',', array_filter($labels, static fn (string $label): bool => $label !== ''));
        }
        if ($attribute->getBackendType() === 'decimal' && is_numeric($value)) {
            return $this->numbers->fromFloat((float) $value);
        }
        if ($attribute->getBackendType() === 'datetime') {
            $text = (string) $value;
            return str_ends_with($text, ' 00:00:00') ? substr($text, 0, 10) : $text;
        }

        return (string) $value;
    }
}
