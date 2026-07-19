<?php

namespace Modules\Exam\Services;

use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;

class QuantityTakeoffXlsxParser
{
    private const FOOTER_MARKERS = [
        'quantities captured',
        'quantitied captured',
        'notes',
        'area a',
        'area b',
        'system.xml',
        'necessary specifications',
        'self check',
    ];

    /**
     * @return array<int, array{key: string, item: string, unit: string, expected_qty: float}>
     */
    public function parse(string $filePath): array
    {
        $this->validateFile($filePath);

        $rows = $this->readSheetRows($filePath, (string) config('quantity_takeoff.sheet_name', 'Estimator Notes'));

        if (empty($rows)) {
            throw new InvalidArgumentException('The answer key worksheet is empty. Use the Estimator Notes template and fill in the Quantity Summary section.');
        }

        return $this->extractLineItems($rows);
    }

    public function validateFile(string $filePath): void
    {
        if (!is_file($filePath)) {
            throw new InvalidArgumentException('Answer key file was not found.');
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($extension !== 'xlsx') {
            throw new InvalidArgumentException('The answer key must be an Excel .xlsx file.');
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readSheetRows(string $filePath, string $preferredSheet): array
    {
        if (class_exists(\ZipArchive::class)) {
            return $this->readSheetRowsWithZipArchive($filePath, $preferredSheet);
        }

        if (PHP_OS_FAMILY === 'Windows') {
            return $this->readSheetRowsWithPowerShell($filePath, $preferredSheet);
        }

        throw new RuntimeException('PHP Zip extension is required to parse Excel answer keys. Enable extension=zip in php.ini.');
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readSheetRowsWithZipArchive(string $filePath, string $preferredSheet): array
    {
        $zip = new \ZipArchive();
        if ($zip->open($filePath) !== true) {
            throw new InvalidArgumentException('Unable to open the Excel answer key file.');
        }

        try {
            $sharedStrings = $this->readSharedStrings($zip);
            $sheetPath = $this->resolveSheetPath($zip, $preferredSheet);
            $sheetXml = $zip->getFromName($sheetPath);

            if ($sheetXml === false) {
                throw new InvalidArgumentException('The answer key must include an "Estimator Notes" worksheet.');
            }

            return $this->parseSheetXml($sheetXml, $sharedStrings);
        } finally {
            $zip->close();
        }
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function readSheetRowsWithPowerShell(string $filePath, string $preferredSheet): array
    {
        $scriptPath = storage_path('app/parse_takeoff_xlsx.ps1');

        if (!is_file($scriptPath)) {
            $this->writePowerShellParserScript($scriptPath);
        }

        $command = sprintf(
            'powershell -NoProfile -ExecutionPolicy Bypass -File %s -Path %s -SheetName %s',
            escapeshellarg($scriptPath),
            escapeshellarg($filePath),
            escapeshellarg($preferredSheet),
        );

        $output = shell_exec($command);

        if (!is_string($output) || trim($output) === '') {
            throw new RuntimeException('Failed to parse the Excel answer key on this server.');
        }

        $rows = json_decode(trim($output), true);

        if (!is_array($rows)) {
            throw new InvalidArgumentException('The uploaded file is not a valid Excel workbook.');
        }

        return array_values(array_map(function (array $row) {
            return collect($row)
                ->filter(fn ($value) => $value !== null && $value !== '')
                ->map(fn ($value) => trim((string) $value))
                ->all();
        }, $rows));
    }

    private function writePowerShellParserScript(string $scriptPath): void
    {
        $script = <<<'PS1'
param(
    [Parameter(Mandatory = $true)][string]$Path,
    [Parameter(Mandatory = $true)][string]$SheetName
)

Add-Type -AssemblyName System.IO.Compression.FileSystem
$zip = [System.IO.Compression.ZipFile]::OpenRead($Path)

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

    $sheetEntry = $null
    if ($target) {
        $sheetPath = 'xl/' + ($target -replace '^\.\./', '')
        $sheetEntry = $zip.Entries | Where-Object { $_.FullName -eq $sheetPath }
    }

    if (-not $sheetEntry) {
        $sheetEntry = $zip.Entries | Where-Object { $_.FullName -eq 'xl/worksheets/sheet1.xml' }
    }

    if (-not $sheetEntry) {
        Write-Output '[]'
        exit 0
    }
    $sr = New-Object System.IO.StreamReader($sheetEntry.Open())
    $sheetXml = [xml]$sr.ReadToEnd()
    $sr.Close()

    $rows = @()
    foreach ($row in $sheetXml.worksheet.sheetData.row) {
        $cells = @{}
        foreach ($c in $row.c) {
            $ref = [string]$c.r
            $col = ($ref -replace '\d+', '')
            $type = [string]$c.t
            $value = if ($c.v) { [string]$c.v } else { '' }
            if ($type -eq 's' -and $value -ne '') {
                $value = $shared[[int]$value]
            }
            if ($value -ne '') {
                $cells[$col] = $value
            }
        }
        if ($cells.Count -gt 0) {
            $rowObject = [ordered]@{}
            foreach ($key in ($cells.Keys | Sort-Object)) {
                $rowObject[$key] = $cells[$key]
            }
            $rows += [pscustomobject]$rowObject
        }
    }

    $rows | ConvertTo-Json -Depth 5 -Compress
}
finally {
    $zip.Dispose()
}
PS1;

        file_put_contents($scriptPath, $script);
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');

        return $xml ? $this->readSharedStringsFromXml($xml) : [];
    }

    /**
     * @return array<int, string>
     */
    private function readSharedStringsFromXml(string $xml): array
    {
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
            throw new InvalidArgumentException('The uploaded file is not a valid Excel workbook.');
        }

        $workbook = simplexml_load_string($workbookXml);
        $rels = simplexml_load_string($relsXml);

        return $this->resolveSheetPathFromXml($workbook, $rels, $preferredSheet);
    }

    private function resolveSheetPathFromXml(\SimpleXMLElement $workbook, \SimpleXMLElement $rels, string $preferredSheet): string
    {
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

            $normalizedTarget = ltrim(str_replace('../', '', $target), '/');

            if ($fallbackPath === null) {
                $fallbackPath = $normalizedTarget;
            }

            if (strcasecmp($name, $preferredSheet) === 0) {
                $sheetPath = $normalizedTarget;
                break;
            }
        }

        if (!$sheetPath) {
            $sheetPath = $fallbackPath;
        }

        if (!$sheetPath) {
            throw new InvalidArgumentException('No worksheets were found in the answer key file.');
        }

        return $sheetPath;
    }

    /**
     * @return array<int, array<string, string>>
     */
    private function parseSheetXml(string $sheetXml, array $sharedStrings): array
    {
        $document = simplexml_load_string($sheetXml);

        if (!$document || !isset($document->sheetData->row)) {
            return [];
        }

        $rows = [];

        foreach ($document->sheetData->row as $row) {
            $rowNumber = (int) $row['r'];
            $cells = [];

            foreach ($row->c as $cell) {
                $reference = (string) $cell['r'];
                $column = preg_replace('/\d+/', '', $reference) ?: '';
                $type = (string) ($cell['t'] ?? '');
                $value = isset($cell->v) ? (string) $cell->v : '';

                if ($type === 's' && $value !== '') {
                    $value = $sharedStrings[(int) $value] ?? '';
                }

                if ($column !== '') {
                    $cells[$column] = trim($value);
                }
            }

            if (!empty($cells)) {
                $rows[$rowNumber] = $cells;
            }
        }

        ksort($rows);

        return array_values($rows);
    }

    /**
     * @param array<int, array<string, string>> $rows
     * @return array<int, array{key: string, item: string, unit: string, expected_qty: float}>
     */
    private function extractLineItems(array $rows): array
    {
        $headerFound = false;
        $lineItems = [];
        $emptyStreak = 0;

        foreach ($rows as $row) {
            $item = $row['A'] ?? '';
            $quantityRaw = $row['B'] ?? '';
            $unit = strtoupper(trim($row['C'] ?? ''));

            if (!$headerFound) {
                if ($this->containsSectionTitle($item)) {
                    continue;
                }

                if ($this->isHeaderRow($item, $quantityRaw)) {
                    $headerFound = true;
                }

                continue;
            }

            if ($this->isFooterRow($item)) {
                break;
            }

            if ($item === '' && $quantityRaw === '') {
                $emptyStreak++;
                if ($emptyStreak >= 2) {
                    break;
                }
                continue;
            }

            $emptyStreak = 0;

            if ($item === '' || !$this->isNumericQuantity($quantityRaw)) {
                continue;
            }

            $lineItems[] = [
                'key' => $this->itemKey($item),
                'item' => $item,
                'unit' => $unit,
                'expected_qty' => $this->toFloat($quantityRaw),
            ];
        }

        if (!$headerFound) {
            throw new InvalidArgumentException(
                'Could not find the "Quantity Summary" section with Item / Quantity headers. ' .
                'Use the Estimator Notes sheet and keep the standard template layout.'
            );
        }

        if (empty($lineItems)) {
            throw new InvalidArgumentException(
                'No graded quantity rows were found. Fill in column B (Quantity) for each line item you want scored.'
            );
        }

        $keys = array_column($lineItems, 'key');
        if (count($keys) !== count(array_unique($keys))) {
            throw new InvalidArgumentException(
                'Duplicate item names were found in the Quantity Summary. Each graded row must have a unique item name.'
            );
        }

        return $lineItems;
    }

    private function containsSectionTitle(string $value): bool
    {
        return str_contains(strtolower($value), strtolower((string) config('quantity_takeoff.section_title', 'Quantity Summary')));
    }

    private function isHeaderRow(string $item, string $quantity): bool
    {
        return strcasecmp($item, 'Item') === 0 && strcasecmp($quantity, 'Quantity') === 0;
    }

    private function isFooterRow(string $item): bool
    {
        $normalized = strtolower(trim($item));

        foreach (self::FOOTER_MARKERS as $marker) {
            if ($normalized !== '' && str_starts_with($normalized, $marker)) {
                return true;
            }
        }

        return false;
    }

    private function isNumericQuantity(string $value): bool
    {
        if ($value === '') {
            return false;
        }

        $normalized = str_replace([',', ' '], '', $value);

        return is_numeric($normalized);
    }

    private function toFloat(string $value): float
    {
        return (float) str_replace([',', ' '], '', $value);
    }

    private function itemKey(string $item): string
    {
        $normalized = mb_strtolower(trim(preg_replace('/\s+/', ' ', $item) ?? $item));

        return substr(hash('sha256', $normalized), 0, 16);
    }

    private function deleteDirectory(string $directory): void
    {
        if (!is_dir($directory)) {
            return;
        }

        $items = scandir($directory);

        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $directory . DIRECTORY_SEPARATOR . $item;

            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                @unlink($path);
            }
        }

        @rmdir($directory);
    }
}
