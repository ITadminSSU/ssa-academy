<?php

use App\Support\XlsxSheetPath;
use Modules\Exam\Services\QuantityTakeoffXlsxParser;

function makeEstimatorNotesWorkbook(string $sheetRelTarget = 'worksheets/sheet1.xml'): string
{
    $path = tempnam(sys_get_temp_dir(), 'qto').'.xlsx';

    $zip = new ZipArchive();
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);

    $zip->addFromString(
        'xl/workbook.xml',
        <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">
  <sheets>
    <sheet name="Estimator Notes" sheetId="1" r:id="rId1"/>
    <sheet name="QAQC Review" sheetId="2" r:id="rId2"/>
  </sheets>
</workbook>
XML
    );

    $zip->addFromString(
        'xl/_rels/workbook.xml.rels',
        <<<XML
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">
  <Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="{$sheetRelTarget}"/>
  <Relationship Id="rId2" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet2.xml"/>
  <Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/sharedStrings" Target="sharedStrings.xml"/>
</Relationships>
XML
    );

    $zip->addFromString(
        'xl/sharedStrings.xml',
        <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<sst xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" count="6" uniqueCount="6">
  <si><t>Quantity Summary</t></si>
  <si><t>Item</t></si>
  <si><t>Quantity</t></si>
  <si><t>Unit</t></si>
  <si><t>Concrete footing</t></si>
  <si><t>CY</t></si>
</sst>
XML
    );

    $zip->addFromString(
        'xl/worksheets/sheet1.xml',
        <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData>
    <row r="1"><c r="A1" t="s"><v>0</v></c></row>
    <row r="2">
      <c r="A2" t="s"><v>1</v></c>
      <c r="B2" t="s"><v>2</v></c>
      <c r="C2" t="s"><v>3</v></c>
    </row>
    <row r="3">
      <c r="A3" t="s"><v>4</v></c>
      <c r="B3"><v>12.5</v></c>
      <c r="C3" t="s"><v>5</v></c>
    </row>
  </sheetData>
</worksheet>
XML
    );

    $zip->addFromString(
        'xl/worksheets/sheet2.xml',
        <<<'XML'
<?xml version="1.0" encoding="UTF-8" standalone="yes"?>
<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">
  <sheetData/>
</worksheet>
XML
    );

    $zip->close();

    return $path;
}

it('prefixes relative worksheet targets with xl/', function () {
    expect(XlsxSheetPath::zipEntry('worksheets/sheet1.xml'))->toBe('xl/worksheets/sheet1.xml');
});

it('does not double-prefix targets that already include xl/', function () {
    expect(XlsxSheetPath::zipEntry('xl/worksheets/sheet1.xml'))->toBe('xl/worksheets/sheet1.xml');
});

it('parses an Excel-style Estimator Notes workbook with a relative sheet target', function () {
    if (! class_exists(ZipArchive::class)) {
        test()->markTestSkipped('ZipArchive is required to parse Excel answer keys.');
    }

    $path = makeEstimatorNotesWorkbook('worksheets/sheet1.xml');

    try {
        $items = app(QuantityTakeoffXlsxParser::class)->parse($path);
    } finally {
        @unlink($path);
    }

    expect($items)->toHaveCount(1)
        ->and($items[0]['item'])->toBe('Concrete footing')
        ->and($items[0]['expected_qty'])->toBe(12.5)
        ->and($items[0]['unit'])->toBe('CY');
});

it('parses a workbook whose relationship target already includes xl/', function () {
    if (! class_exists(ZipArchive::class)) {
        test()->markTestSkipped('ZipArchive is required to parse Excel answer keys.');
    }

    $path = makeEstimatorNotesWorkbook('xl/worksheets/sheet1.xml');

    try {
        $items = app(QuantityTakeoffXlsxParser::class)->parse($path);
    } finally {
        @unlink($path);
    }

    expect($items)->toHaveCount(1)
        ->and($items[0]['item'])->toBe('Concrete footing');
});
