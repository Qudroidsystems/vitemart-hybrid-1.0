<?php

namespace App\Support\Xlsx;

/**
 * Minimal streaming .xlsx writer. Rows are written straight to disk, so a
 * 100,000-product catalogue exports in seconds with very little memory
 * (PhpSpreadsheet would need gigabytes for that).
 *
 * Supports: several sheets, a bold/coloured header row, frozen header, column
 * widths, text / number / date cells, dropdown lists (data validation) and
 * read-only-looking (grey) columns.
 *
 *   $w = new XlsxWriter($path);
 *   $w->sheet('Products', $columns);            // columns: [['title' => 'Name', 'width' => 30, 'type' => 'text'|'number'|'date', 'list' => 'Lists!$A$2:$A$50', 'muted' => true], ...]
 *   $w->row(['Indomie', 320]);
 *   $w->sheet('Instructions', [...]); ...
 *   $w->close();
 */
class XlsxWriter
{
    protected string $dir;
    protected array $sheets = [];        // [name, file, columns, rows, validations]
    protected $fh = null;
    protected int $rowNum = 0;

    // style ids (see styles())
    public const S_TEXT = 1;
    public const S_HEADER = 2;
    public const S_NUMBER = 3;
    public const S_DATE = 4;
    public const S_MUTED = 5;
    public const S_MUTED_NUMBER = 6;
    public const S_WRAP = 7;
    public const S_TITLE = 8;
    public const S_REQUIRED_HEADER = 9;
    public const S_MUTED_HEADER = 10;

    public function __construct(protected string $path)
    {
        $this->dir = sys_get_temp_dir() . '/xlsxw-' . bin2hex(random_bytes(6));
        mkdir($this->dir, 0700, true);
    }

    /** Start a new sheet. $columns: title, width, type, list (formula range), muted, required, wrap */
    public function sheet(string $name, array $columns, bool $header = true): void
    {
        $this->endSheet();
        $i = count($this->sheets) + 1;
        $file = "{$this->dir}/sheet{$i}.xml";
        $this->fh = fopen($file, 'w');
        $this->rowNum = 0;
        $this->sheets[] = ['name' => mb_substr($name, 0, 31), 'file' => $file, 'columns' => $columns, 'header' => $header];

        $cols = '';
        foreach ($columns as $c => $col) {
            $w = $col['width'] ?? 14;
            $cols .= '<col min="' . ($c + 1) . '" max="' . ($c + 1) . '" width="' . $w . '" customWidth="1"/>';
        }
        fwrite($this->fh, '<?xml version="1.0" encoding="UTF-8" standalone="yes"?>'
            . '<worksheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . ($header ? '<sheetViews><sheetView workbookViewId="0"><pane ySplit="1" topLeftCell="A2" activePane="bottomLeft" state="frozen"/></sheetView></sheetViews>' : '<sheetViews><sheetView workbookViewId="0"/></sheetViews>')
            . '<sheetFormatPr defaultRowHeight="15"/>'
            . ($cols ? '<cols>' . $cols . '</cols>' : '')
            . '<sheetData>');

        if ($header) {
            $cells = [];
            foreach ($columns as $c => $col) {
                $style = !empty($col['muted']) ? self::S_MUTED_HEADER : (!empty($col['required']) ? self::S_REQUIRED_HEADER : self::S_HEADER);
                $cells[] = [$col['title'] ?? '', $style, 'text'];
            }
            $this->writeCells($cells, 30);
        }
    }

    /** Write one data row (values in column order). */
    public function row(array $values, ?int $style = null): void
    {
        $cols = $this->sheets[count($this->sheets) - 1]['columns'] ?? [];
        $cells = [];
        foreach (array_values($values) as $c => $v) {
            $col = $cols[$c] ?? [];
            $type = $col['type'] ?? 'text';
            $muted = !empty($col['muted']);
            $s = $style ?? match (true) {
                $type === 'number' => $muted ? self::S_MUTED_NUMBER : self::S_NUMBER,
                $type === 'date' => self::S_DATE,
                !empty($col['wrap']) => self::S_WRAP,
                default => $muted ? self::S_MUTED : self::S_TEXT,
            };
            $cells[] = [$v, $s, $type];
        }
        $this->writeCells($cells);
    }

    protected function writeCells(array $cells, ?int $height = null): void
    {
        $r = ++$this->rowNum;
        $xml = '<row r="' . $r . '"' . ($height ? ' ht="' . $height . '" customHeight="1"' : '') . '>';
        foreach ($cells as $c => [$v, $s, $type]) {
            if ($v === null || $v === '') {
                // keep header/required styling on empty cells only when styled
                if ($s === self::S_TEXT || $s === self::S_NUMBER) {
                    continue;
                }
                $xml .= '<c r="' . self::col($c) . $r . '" s="' . $s . '"/>';
                continue;
            }
            $ref = self::col($c) . $r;
            if ($type === 'number' && is_numeric($v)) {
                $xml .= '<c r="' . $ref . '" s="' . $s . '"><v>' . (0 + $v) . '</v></c>';
            } elseif ($type === 'date' && ($v instanceof \DateTimeInterface)) {
                $serial = 25569 + ($v->getTimestamp() + $v->getOffset()) / 86400;
                $xml .= '<c r="' . $ref . '" s="' . $s . '"><v>' . round($serial, 6) . '</v></c>';
            } else {
                // Text – always as a string so barcodes keep leading zeros and aren't turned into 6.15E+12
                $xml .= '<c r="' . $ref . '" s="' . $s . '" t="inlineStr"><is><t xml:space="preserve">' . self::esc((string) $v) . '</t></is></c>';
            }
        }
        fwrite($this->fh, $xml . '</row>');
    }

    /** Dropdown list on a column of the current sheet, e.g. ('Lists!$A$2:$A$80', 4, 2, 100001) */
    public function validation(int $colIndex, string $source, int $fromRow = 2, int $toRow = 100000, string $prompt = ''): void
    {
        $k = count($this->sheets) - 1;
        $this->sheets[$k]['validations'][] = [$colIndex, $source, $fromRow, $toRow, $prompt];
    }

    protected function endSheet(): void
    {
        if (!$this->fh) {
            return;
        }
        $k = count($this->sheets) - 1;
        $xml = '</sheetData>';
        $last = count($this->sheets[$k]['columns']);
        if ($this->sheets[$k]['header'] && $last) {
            $xml .= '<autoFilter ref="A1:' . self::col($last - 1) . max(1, $this->rowNum) . '"/>';
        }
        $vals = $this->sheets[$k]['validations'] ?? [];
        if ($vals) {
            $xml .= '<dataValidations count="' . count($vals) . '">';
            foreach ($vals as [$c, $src, $from, $to, $prompt]) {
                $range = self::col($c) . $from . ':' . self::col($c) . $to;
                $xml .= '<dataValidation type="list" allowBlank="1" showErrorMessage="1" errorStyle="warning"'
                    . ' errorTitle="Not in the list" error="Pick from the list, or type a new name to create it."'
                    . ($prompt ? ' showInputMessage="1" prompt="' . self::esc($prompt) . '"' : '')
                    . ' sqref="' . $range . '"><formula1>' . self::esc($src) . '</formula1></dataValidation>';
            }
            $xml .= '</dataValidations>';
        }
        $xml .= '<pageMargins left="0.5" right="0.5" top="0.6" bottom="0.6" header="0.3" footer="0.3"/></worksheet>';
        fwrite($this->fh, $xml);
        fclose($this->fh);
        $this->fh = null;
    }

    public function close(): string
    {
        $this->endSheet();
        $zip = new \ZipArchive();
        if ($zip->open($this->path, \ZipArchive::CREATE | \ZipArchive::OVERWRITE) !== true) {
            throw new \RuntimeException('Cannot create ' . $this->path);
        }
        $n = count($this->sheets);
        $ct = '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Types xmlns="http://schemas.openxmlformats.org/package/2006/content-types">'
            . '<Default Extension="rels" ContentType="application/vnd.openxmlformats-package.relationships+xml"/><Default Extension="xml" ContentType="application/xml"/>'
            . '<Override PartName="/xl/workbook.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.sheet.main+xml"/>'
            . '<Override PartName="/xl/styles.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.styles+xml"/>'
            . '<Override PartName="/docProps/app.xml" ContentType="application/vnd.openxmlformats-officedocument.extended-properties+xml"/>'
            . '<Override PartName="/docProps/core.xml" ContentType="application/vnd.openxmlformats-package.core-properties+xml"/>';
        $wbSheets = '';
        $wbRels = '';
        for ($i = 1; $i <= $n; $i++) {
            $ct .= '<Override PartName="/xl/worksheets/sheet' . $i . '.xml" ContentType="application/vnd.openxmlformats-officedocument.spreadsheetml.worksheet+xml"/>';
            $wbSheets .= '<sheet name="' . self::esc($this->sheets[$i - 1]['name']) . '" sheetId="' . $i . '" r:id="rId' . $i . '"/>';
            $wbRels .= '<Relationship Id="rId' . $i . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/worksheet" Target="worksheets/sheet' . $i . '.xml"/>';
            $zip->addFile($this->sheets[$i - 1]['file'], 'xl/worksheets/sheet' . $i . '.xml');
        }
        $ct .= '</Types>';
        $wbRels .= '<Relationship Id="rId' . ($n + 1) . '" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/styles" Target="styles.xml"/>';

        // filter ranges so the autofilter buttons work in Excel
        $defined = '';
        foreach ($this->sheets as $i => $s) {
            if ($s['header'] && $s['columns']) {
                $defined .= '<definedName name="_xlnm._FilterDatabase" localSheetId="' . $i . '" hidden="1">\'' . self::esc(str_replace("'", "''", $s['name'])) . '\'!$A$1:$' . self::col(count($s['columns']) - 1) . '$1</definedName>';
            }
        }

        $zip->addFromString('[Content_Types].xml', $ct);
        $zip->addFromString('_rels/.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">'
            . '<Relationship Id="rId1" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/officeDocument" Target="xl/workbook.xml"/>'
            . '<Relationship Id="rId2" Type="http://schemas.openxmlformats.org/package/2006/relationships/metadata/core-properties" Target="docProps/core.xml"/>'
            . '<Relationship Id="rId3" Type="http://schemas.openxmlformats.org/officeDocument/2006/relationships/extended-properties" Target="docProps/app.xml"/></Relationships>');
        $zip->addFromString('docProps/app.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Properties xmlns="http://schemas.openxmlformats.org/officeDocument/2006/extended-properties"><Application>ViteMart</Application></Properties>');
        $zip->addFromString('docProps/core.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><cp:coreProperties xmlns:cp="http://schemas.openxmlformats.org/package/2006/metadata/core-properties" xmlns:dc="http://purl.org/dc/elements/1.1/" xmlns:dcterms="http://purl.org/dc/terms/" xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance">'
            . '<dc:creator>ViteMart</dc:creator><dcterms:created xsi:type="dcterms:W3CDTF">' . gmdate('Y-m-d\TH:i:s\Z') . '</dcterms:created></cp:coreProperties>');
        $zip->addFromString('xl/workbook.xml', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><workbook xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main" xmlns:r="http://schemas.openxmlformats.org/officeDocument/2006/relationships">'
            . '<bookViews><workbookView activeTab="0"/></bookViews><sheets>' . $wbSheets . '</sheets>' . ($defined ? '<definedNames>' . $defined . '</definedNames>' : '') . '</workbook>');
        $zip->addFromString('xl/_rels/workbook.xml.rels', '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><Relationships xmlns="http://schemas.openxmlformats.org/package/2006/relationships">' . $wbRels . '</Relationships>');
        $zip->addFromString('xl/styles.xml', $this->styles());
        $zip->close();

        foreach ($this->sheets as $s) {
            @unlink($s['file']);
        }
        @rmdir($this->dir);
        return $this->path;
    }

    protected function styles(): string
    {
        // fonts: 0 normal, 1 bold white, 2 grey, 3 bold dark (title) 4 bold white
        // fills: 0 none, 1 gray125 (required by Excel), 2 teal header, 3 light grey (muted), 4 dark teal (required header), 5 grey header
        return '<?xml version="1.0" encoding="UTF-8" standalone="yes"?><styleSheet xmlns="http://schemas.openxmlformats.org/spreadsheetml/2006/main">'
            . '<numFmts count="2"><numFmt numFmtId="164" formatCode="#,##0.00"/><numFmt numFmtId="165" formatCode="yyyy-mm-dd"/></numFmts>'
            . '<fonts count="4"><font><sz val="11"/><name val="Calibri"/></font><font><b/><sz val="11"/><color rgb="FFFFFFFF"/><name val="Calibri"/></font>'
            . '<font><sz val="11"/><color rgb="FF6B7280"/><name val="Calibri"/></font><font><b/><sz val="14"/><color rgb="FF0F172A"/><name val="Calibri"/></font></fonts>'
            . '<fills count="6"><fill><patternFill patternType="none"/></fill><fill><patternFill patternType="gray125"/></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF0F766E"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FFF1F5F9"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF115E59"/><bgColor indexed="64"/></patternFill></fill>'
            . '<fill><patternFill patternType="solid"><fgColor rgb="FF94A3B8"/><bgColor indexed="64"/></patternFill></fill></fills>'
            . '<borders count="2"><border><left/><right/><top/><bottom/><diagonal/></border><border><left/><right/><top/><bottom style="thin"><color rgb="FFCBD5E1"/></bottom><diagonal/></border></borders>'
            . '<cellStyleXfs count="1"><xf numFmtId="0" fontId="0" fillId="0" borderId="0"/></cellStyleXfs>'
            . '<cellXfs count="11">'
            . '<xf numFmtId="0" fontId="0" fillId="0" borderId="0" xfId="0"/>'                                                     // 0 default
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                               // 1 text (@)
            . '<xf numFmtId="49" fontId="1" fillId="2" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>' // 2 header
            . '<xf numFmtId="164" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                              // 3 number
            . '<xf numFmtId="165" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1"/>'                              // 4 date
            . '<xf numFmtId="49" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyNumberFormat="1"/>'   // 5 muted text
            . '<xf numFmtId="164" fontId="2" fillId="3" borderId="0" xfId="0" applyFont="1" applyFill="1" applyNumberFormat="1"/>'  // 6 muted number
            . '<xf numFmtId="49" fontId="0" fillId="0" borderId="0" xfId="0" applyNumberFormat="1" applyAlignment="1"><alignment vertical="top" wrapText="1"/></xf>' // 7 wrap
            . '<xf numFmtId="0" fontId="3" fillId="0" borderId="0" xfId="0" applyFont="1"/>'                                        // 8 title
            . '<xf numFmtId="49" fontId="1" fillId="4" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>' // 9 required header
            . '<xf numFmtId="49" fontId="1" fillId="5" borderId="1" xfId="0" applyFont="1" applyFill="1" applyBorder="1" applyAlignment="1"><alignment vertical="center" wrapText="1"/></xf>' // 10 muted header
            . '</cellXfs><cellStyles count="1"><cellStyle name="Normal" xfId="0" builtinId="0"/></cellStyles></styleSheet>';
    }

    public static function col(int $i): string
    {
        $s = '';
        $i++;
        while ($i > 0) {
            $m = ($i - 1) % 26;
            $s = chr(65 + $m) . $s;
            $i = intdiv($i - $m - 1, 26);
        }
        return $s;
    }

    protected static function esc(string $s): string
    {
        // strip characters XML 1.0 forbids
        $s = preg_replace('/[^\x{9}\x{A}\x{D}\x{20}-\x{D7FF}\x{E000}-\x{FFFD}]/u', '', $s) ?? '';
        return htmlspecialchars($s, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    public function __destruct()
    {
        if ($this->fh) {
            fclose($this->fh);
        }
    }
}
