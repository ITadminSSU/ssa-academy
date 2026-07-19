<?php

namespace Modules\Exam\Services;

use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class QuantityTakeoffTemplateGenerator
{
    /**
     * @param array<int, array{key: string, item: string}> $lineItems
     */
    public function generateBlankTemplate(string $sourcePath, string $outputPath, array $lineItems): void
    {
        if (!is_file($sourcePath)) {
            throw new InvalidArgumentException('Answer key file was not found for template generation.');
        }

        File::ensureDirectoryExists(dirname($outputPath));

        if (is_file($outputPath)) {
            @unlink($outputPath);
        }

        copy($sourcePath, $outputPath);

        $sheetName = (string) config('quantity_takeoff.sheet_name', 'Estimator Notes');

        if (class_exists(\ZipArchive::class)) {
            $this->blankWithZipArchive($outputPath, $lineItems, $sheetName);

            return;
        }

        if (PHP_OS_FAMILY === 'Windows') {
            $this->blankWithPowerShell($outputPath, $lineItems, $sheetName);

            return;
        }

        throw new RuntimeException('Unable to generate a student template on this server.');
    }

    /**
     * @param array<int, array{key: string, item: string}> $lineItems
     */
    private function blankWithPowerShell(string $outputPath, array $lineItems, string $sheetName): void
    {
        $itemsFile = storage_path('app/temp_takeoff_items_' . Str::uuid() . '.json');
        file_put_contents($itemsFile, json_encode(array_column($lineItems, 'item')));

        $scriptPath = storage_path('app/blank_takeoff_template.ps1');
        $this->writeBlankTemplateScript($scriptPath);

        $command = sprintf(
            'powershell -NoProfile -ExecutionPolicy Bypass -File %s -Path %s -ItemsFile %s -SheetName %s',
            escapeshellarg($scriptPath),
            escapeshellarg($outputPath),
            escapeshellarg($itemsFile),
            escapeshellarg($sheetName),
        );

        $output = shell_exec($command);
        @unlink($itemsFile);

        if (!is_file($outputPath)) {
            throw new RuntimeException('Failed to generate the student template file.');
        }

        if (is_string($output) && str_starts_with(trim($output), 'ERROR:')) {
            throw new RuntimeException(trim($output));
        }
    }

    /**
     * @param array<int, array{key: string, item: string}> $lineItems
     */
    private function blankWithZipArchive(string $outputPath, array $lineItems, string $sheetName): void
    {
        $items = array_map(
            fn (string $item) => $this->normalizeItem($item),
            array_column($lineItems, 'item'),
        );

        $zip = new \ZipArchive();

        if ($zip->open($outputPath) !== true) {
            throw new RuntimeException('Failed to open workbook for template generation.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetPath = $this->resolveSheetPath($zip, $sheetName);
            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                throw new RuntimeException('Could not read the Estimator Notes worksheet.');
            }

            $document = new \DOMDocument();
            $document->preserveWhiteSpace = false;
            $document->formatOutput = false;
            $document->loadXML($sheetXml);

            $xpath = new \DOMXPath($document);
            $xpath->registerNamespace('m', 'http://schemas.openxmlformats.org/spreadsheetml/2006/main');

            $blankedCount = 0;

            foreach ($xpath->query('//m:sheetData/m:row') as $row) {
                $itemValue = null;
                $quantityCell = null;

                foreach ($xpath->query('m:c', $row) as $cell) {
                    if (!$cell instanceof \DOMElement) {
                        continue;
                    }

                    $reference = $cell->getAttribute('r');
                    $column = preg_replace('/\d+/', '', $reference);

                    if ($column === 'A') {
                        $itemValue = $this->cellTextValue($cell, $xpath, $sharedStrings);
                    }

                    if ($column === 'B') {
                        $quantityCell = $cell;
                    }
                }

                if (!$itemValue || !$quantityCell) {
                    continue;
                }

                if (!in_array($this->normalizeItem($itemValue), $items, true)) {
                    continue;
                }

                $this->clearCellValue($quantityCell, $xpath);
                $blankedCount++;
            }

            if ($blankedCount === 0) {
                throw new RuntimeException('Could not blank any quantity cells in the student template.');
            }

            $zip->addFromString($sheetPath, $document->saveXML());
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        if ($xml === false) {
            return [];
        }

        $shared = [];
        $document = simplexml_load_string($xml);

        if (!$document) {
            return $shared;
        }

        foreach ($document->si as $si) {
            if (isset($si->t)) {
                $shared[] = (string) $si->t;
            } elseif (isset($si->r)) {
                $text = '';
                foreach ($si->r as $run) {
                    $text .= (string) $run->t;
                }
                $shared[] = $text;
            } else {
                $shared[] = '';
            }
        }

        return $shared;
    }

    private function resolveSheetPath(\ZipArchive $zip, string $preferredSheet): string
    {
        $workbookXml = $zip->getFromName('xl/workbook.xml');
        $relsXml = $zip->getFromName('xl/_rels/workbook.xml.rels');

        if ($workbookXml === false || $relsXml === false) {
            return 'xl/worksheets/sheet1.xml';
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);

        if (!$workbook || !$rels) {
            return 'xl/worksheets/sheet1.xml';
        }

        $relationships = [];
        foreach ($rels->Relationship as $relationship) {
            $relationships[(string) $relationship['Id']] = (string) $relationship['Target'];
        }

        $sheetPath = null;
        $fallbackPath = null;

        foreach ($workbook->sheets->sheet as $sheet) {
            $name = (string) $sheet['name'];
            $target = $relationships[(string) $sheet->attributes('r', true)->id] ?? null;

            if (!$target) {
                continue;
            }

            $normalizedTarget = 'xl/' . ltrim(str_replace('../', '', $target), '/');

            if ($fallbackPath === null) {
                $fallbackPath = $normalizedTarget;
            }

            if (strcasecmp($name, $preferredSheet) === 0) {
                $sheetPath = $normalizedTarget;
                break;
            }
        }

        return $sheetPath ?? $fallbackPath ?? 'xl/worksheets/sheet1.xml';
    }

    /**
     * @param array<int, string> $sharedStrings
     */
    private function cellTextValue(\DOMElement $cell, \DOMXPath $xpath, array $sharedStrings): ?string
    {
        $type = $cell->getAttribute('t');
        $valueNode = $xpath->query('m:v', $cell)->item(0);

        if ($valueNode) {
            $value = trim($valueNode->textContent);

            if ($type === 's') {
                return $sharedStrings[(int) $value] ?? null;
            }

            return $value !== '' ? $value : null;
        }

        $inlineText = $xpath->query('m:is/m:t', $cell)->item(0);

        if ($inlineText) {
            $value = trim($inlineText->textContent);

            return $value !== '' ? $value : null;
        }

        return null;
    }

    private function clearCellValue(\DOMElement $cell, \DOMXPath $xpath): void
    {
        foreach ($xpath->query('m:v', $cell) as $valueNode) {
            $cell->removeChild($valueNode);
        }

        foreach ($xpath->query('m:is', $cell) as $inlineNode) {
            $cell->removeChild($inlineNode);
        }

        if ($cell->hasAttribute('t')) {
            $cell->removeAttribute('t');
        }
    }

    private function normalizeItem(string $item): string
    {
        return mb_strtolower(trim(preg_replace('/\s+/', ' ', $item) ?? $item));
    }

    private function writeBlankTemplateScript(string $scriptPath): void
    {
        $script = <<<'PS1'
param(
    [Parameter(Mandatory = $true)][string]$Path,
    [Parameter(Mandatory = $true)][string]$ItemsFile,
    [Parameter(Mandatory = $true)][string]$SheetName
)

$items = Get-Content $ItemsFile -Raw | ConvertFrom-Json
$normalizedItems = @{}
foreach ($item in $items) {
    $normalizedItems[($item.ToString().ToLower().Trim() -replace '\s+', ' ')] = $true
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::Open($Path, [System.IO.Compression.ZipArchiveMode]::Update)

try {
    $shared = @()
    $ss = $zip.Entries | Where-Object { $_.FullName -eq 'xl/sharedStrings.xml' }
    if ($ss) {
        $sr = New-Object System.IO.StreamReader($ss.Open())
        $sxml = [xml]$sr.ReadToEnd()
        $sr.Close()
        foreach ($si in $sxml.sst.si) {
            if ($si.t) { $shared += [string]$si.t }
            elseif ($si.r) {
                $text = ''
                foreach ($run in $si.r) { $text += [string]$run.t }
                $shared += $text
            } else { $shared += '' }
        }
    }

    $workbookEntry = $zip.Entries | Where-Object { $_.FullName -eq 'xl/workbook.xml' }
    $relsEntry = $zip.Entries | Where-Object { $_.FullName -eq 'xl/_rels/workbook.xml.rels' }
    $sr = New-Object System.IO.StreamReader($workbookEntry.Open())
    $workbook = [xml]$sr.ReadToEnd()
    $sr.Close()
    $sr = New-Object System.IO.StreamReader($relsEntry.Open())
    $rels = [xml]$sr.ReadToEnd()
    $sr.Close()

    $relationships = @{}
    foreach ($rel in $rels.Relationships.Relationship) {
        $relationships[[string]$rel.Id] = [string]$rel.Target
    }

    $target = $null
    foreach ($sheet in $workbook.workbook.sheets.sheet) {
        $name = [string]$sheet.name
        $rid = $sheet.GetAttribute('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
        if (-not $rid) { $rid = [string]$sheet.'r:id' }
        if ($name -eq $SheetName -and $rid) {
            $target = $relationships[$rid]
            break
        }
    }

    if (-not $target) {
        foreach ($sheet in $workbook.workbook.sheets.sheet) {
            $rid = $sheet.GetAttribute('id', 'http://schemas.openxmlformats.org/officeDocument/2006/relationships')
            if (-not $rid) { $rid = [string]$sheet.'r:id' }
            if ($rid -and $relationships.ContainsKey($rid)) {
                $target = $relationships[$rid]
                break
            }
        }
    }

    $sheetPath = if ($target) { 'xl/' + ($target -replace '^\.\./', '') } else { 'xl/worksheets/sheet1.xml' }
    $sheetEntry = $zip.GetEntry($sheetPath)
    if (-not $sheetEntry) {
        Write-Output 'ERROR: Worksheet not found for template generation.'
        exit 1
    }

    $sr = New-Object System.IO.StreamReader($sheetEntry.Open())
    $xml = [xml]$sr.ReadToEnd()
    $sr.Close()

    $blankedCount = 0
    foreach ($row in $xml.worksheet.sheetData.row) {
        $itemValue = $null
        $quantityCell = $null
        foreach ($c in $row.c) {
            $ref = [string]$c.r
            $col = ($ref -replace '\d+', '')
            $type = [string]$c.t
            $value = if ($c.v) { [string]$c.v } else { '' }
            if ($type -eq 's' -and $value -ne '') { $value = $shared[[int]$value] }
            if ($col -eq 'A') { $itemValue = $value }
            if ($col -eq 'B') { $quantityCell = $c }
        }
        if (-not $itemValue -or -not $quantityCell) { continue }
        $normalized = ($itemValue.ToLower().Trim() -replace '\s+', ' ')
        if (-not $normalizedItems.ContainsKey($normalized)) { continue }
        if ($quantityCell.v) { $quantityCell.RemoveChild($quantityCell.v) }
        if ($quantityCell.is) { $quantityCell.RemoveChild($quantityCell.is) }
        if ($quantityCell.t) { $quantityCell.RemoveAttribute('t') }
        $blankedCount++
    }

    if ($blankedCount -eq 0) {
        Write-Output 'ERROR: Could not blank any quantity cells in the student template.'
        exit 1
    }

    $sheetEntry.Delete()
    $newEntry = $zip.CreateEntry($sheetPath)
    $stream = $newEntry.Open()
    $writer = New-Object System.IO.StreamWriter($stream)
    $writer.Write($xml.OuterXml)
    $writer.Flush()
    $writer.Close()
    $stream.Close()
}
finally {
    $zip.Dispose()
}
PS1;

        file_put_contents($scriptPath, $script);
    }
}
