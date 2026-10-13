<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Model\Transform;

/**
 * date(from_format, to_format): reads a date in the file's format and writes it in another (PHP date format letters:
 * d.m.Y, m/d/Y, Y-m-d H:i:s …). from_format "auto" accepts ISO dates (2024-01-31, with or without time) and
 * German dates (31.01.2024, with or without time). Impossible dates such as 31.02.2024 are row errors. No time zone
 * conversion.
 */
class DateTransform implements TransformProviderInterface
{
    private const AUTO_FORMATS = ['Y-m-d', 'Y-m-d H:i:s', 'Y-m-d H:i', 'Y-m-d\TH:i:s', 'Y-m-d\TH:i:sP', 'Y-m-d\TH:i:s.uP',
        'd.m.Y', 'd.m.Y H:i', 'd.m.Y H:i:s', 'Ymd'];

    /**
     * @inheritDoc
     */
    public function names(): array
    {
        return ['date'];
    }

    /**
     * @inheritDoc
     */
    public function compile(string $name, Arguments $arguments, TransformScope $scope): \Closure
    {
        $arguments->expectCount(0, 2);
        $from = trim($arguments->string(0, 'auto'));
        $from = $from === '' ? 'auto' : $from;
        $to = $arguments->string(1, 'Y-m-d');
        if (trim($to) === '') {
            throw $arguments->error('the output format is empty');
        }
        $formats = strtolower($from) === 'auto' ? self::AUTO_FORMATS : [$from];

        return function (string $value) use ($formats, $to, $from): string {
            $value = trim($value);
            if ($value === '') {
                return '';
            }
            foreach ($formats as $format) {
                $date = $this->read($format, $value);
                if ($date !== null) {
                    return $date->format($to);
                }
            }
            throw new TransformException(sprintf('date: "%s" is not a date in the format %s.', $value, $from));
        };
    }

    private function read(string $format, string $value): ?\DateTimeImmutable
    {
        $date = \DateTimeImmutable::createFromFormat('!' . $format, $value, new \DateTimeZone('UTC'));
        $errors = \DateTimeImmutable::getLastErrors();
        if ($date === false || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))) {
            return null;
        }

        return $date;
    }
}
