<?php
/**
 * Copyright © Iman Aboheydary. All rights reserved.
 * See LICENSE for license details (MIT).
 */

declare(strict_types=1);

namespace Iranimij\OpenImport\Test\Unit\Model\Reader;

/**
 * Temporary files for reader tests, removed after each test.
 */
trait TempFiles
{
    /**
     * @var string[]
     */
    private array $tempFiles = [];

    private function tempFile(string $content, string $extension = 'csv'): string
    {
        $file = sys_get_temp_dir() . '/openimport-test-' . bin2hex(random_bytes(6)) . '.' . $extension;
        file_put_contents($file, $content);
        $this->tempFiles[] = $file;

        return $file;
    }

    /**
     * Builds a minimal .xlsx workbook.
     *
     * @param array<string, string> $sheets sheet name => sheetData inner XML (<row> elements)
     * @param string[] $sharedStrings
     * @param string $stylesXml cellXfs/numFmts inner XML of styles.xml
     * @param bool $date1904
     * @return string
     */
    private function xlsx(array $sheets, array $sharedStrings = [], string $stylesXml = '', bool $date1904 = false): string
    {
        $file = $this->tempFile('', 'xlsx');
        $zip = new \ZipArchive();
        $zip->open($file, \ZipArchive::CREATE | \ZipArchive::OVERWRITE);
        $main = 'http://schemas.openxmlformats.org/spreadsheetml/2006/main';
        $rel = 'http://schemas.openxmlformats.org/officeDocument/2006/relationships';
        $sheetList = '';
        $rels = '';
        $index = 0;
        foreach ($sheets as $name => $rows) {
            $index++;
            $sheetList .= sprintf('<sheet name="%s" sheetId="%d" r:id="rId%d"/>', htmlspecialchars($name), $index, $index);
            $rels .= sprintf(
                '<Relationship Id="rId%d" Type="%s/worksheet" Target="worksheets/sheet%d.xml"/>',
                $index,
                $rel,
                $index
            );
            $zip->addFromString(
                sprintf('xl/worksheets/sheet%d.xml', $index),
                '<?xml version="1.0" encoding="UTF-8"?><worksheet xmlns="' . $main . '"><sheetData>' . $rows
                . '</sheetData></worksheet>'
            );
        }
        $zip->addFromString(
            'xl/workbook.xml',
            '<?xml version="1.0" encoding="UTF-8"?><workbook xmlns="' . $main . '" xmlns:r="' . $rel . '">'
            . ($date1904 ? '<workbookPr date1904="1"/>' : '<workbookPr/>')
            . '<sheets>' . $sheetList . '</sheets></workbook>'
        );
        $zip->addFromString(
            'xl/_rels/workbook.xml.rels',
            '<?xml version="1.0" encoding="UTF-8"?>'
            . '<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $rels
            . '</Relationships>'
        );
        if ($sharedStrings !== []) {
            $items = '';
            foreach ($sharedStrings as $string) {
                $items .= str_starts_with($string, '<') ? '<si>' . $string . '</si>'
                    : '<si><t xml:space="preserve">' . htmlspecialchars($string) . '</t></si>';
            }
            $zip->addFromString(
                'xl/sharedStrings.xml',
                '<?xml version="1.0" encoding="UTF-8"?><sst xmlns="' . $main . '">' . $items . '</sst>'
            );
        }
        if ($stylesXml !== '') {
            $zip->addFromString(
                'xl/styles.xml',
                '<?xml version="1.0" encoding="UTF-8"?><styleSheet xmlns="' . $main . '">' . $stylesXml . '</styleSheet>'
            );
        }
        $zip->close();

        return $file;
    }

    protected function tearDown(): void
    {
        foreach ($this->tempFiles as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        $this->tempFiles = [];
    }
}
