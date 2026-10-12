<?php

namespace App\Support\Xlsx;

/**
 * Streams rows out of an .xlsx or .csv file without loading the whole file into
 * memory (100,000-row spreadsheets are fine).
 *
 *   foreach (SheetReader::rows($path, 'xlsx', 'Products') as $rowNumber => $cells) { ... }
 *
 * Each row is a list of strings indexed from 0 (empty cells are ''). Dates come
 * back as Excel serial numbers – see SheetReader::excelDate().
 */
class SheetReader
{
    public static function rows(string $path, string $ext, ?string $preferSheet = null): \Generator
    {
        $ext = strtolower($ext);
        if (in_array($ext, ['csv', 'txt'], true)) {
            yield from self::csv($path);
            return;
        }
        if ($ext !== 'xlsx') {
            throw new \InvalidArgumentException('Please upload an .xlsx (Excel) or .csv file. Old .xls files: open in Excel and "Save as" .xlsx first.');
        }
        yield from self::xlsx($path, $preferSheet);
    }

    protected static function csv(string $path): \Generator
    {
        $h = fopen($path, 'r');
        if (!$h) {
            throw new \RuntimeException('Could not open the file.');
        }
        $first = fgets($h);
        $bom = str_starts_with((string) $first, "\xEF\xBB\xBF");
        $delimiter = substr_count((string) $first, ';') > substr_count((string) $first, ',') ? ';' : (substr_count((string) $first, "\t") > substr_count((string) $first, ',') ? "\t" : ',');
        rewind($h);
        if ($bom) {
            fread($h, 3);
        }
        $n = 0;
        while (($row = fgetcsv($h, 0, $delimiter, '"', '')) !== false) {
            $n++;
            if ($row === [null]) {
                yield $n => [];
                continue;
            }
            $row = array_map(fn ($v) => self::toUtf8(trim((string) $v)), $row);
            yield $n => $row;
        }
        fclose($h);
    }

    protected static function xlsx(string $path, ?string $preferSheet): \Generator
    {
        $zip = new \ZipArchive();
        if ($zip->open($path) !== true) {
            throw new \RuntimeException('This doesn\'t look like a valid Excel (.xlsx) file.');
        }
        $sheetPath = self::sheetPath($zip, $preferSheet);
        $strings = self::sharedStrings($zip);
        $zip->close();

        $reader = new \XMLReader();
        if (!$reader->open('zip://' . $path . '#' . $sheetPath, null, LIBXML_NONET | LIBXML_COMPACT)) {
            throw new \RuntimeException('Could not read the worksheet.');
        }
        $rowNum = 0;
        while ($reader->read()) {
            if ($reader->nodeType !== \XMLReader::ELEMENT || $reader->localName !== 'row') {
                continue;
            }
            $r = (int) $reader->getAttribute('r') ?: $rowNum + 1;
            // blank rows between
            while ($rowNum + 1 < $r) {
                $rowNum++;
                yield $rowNum => [];
            }
            $rowNum = $r;
            $node = $reader->expand();
            $cells = [];
            if ($node) {
                $col = 0;
                foreach ($node->childNodes as $c) {
                    if ($c->nodeName !== 'c') {
                        continue;
                    }
                    $ref = $c->getAttribute('r');
                    $idx = $ref ? self::colIndex(preg_replace('/\d+/', '', $ref)) : $col;
                    $col = $idx + 1;
                    $type = $c->getAttribute('t');
                    $val = '';
                    if ($type === 'inlineStr') {
                        foreach ($c->getElementsByTagName('t') as $t) {
                            $val .= $t->textContent;
                        }
                    } else {
                        $v = $c->getElementsByTagName('v')->item(0);
                        $raw = $v ? $v->textContent : '';
                        $val = match ($type) {
                            's' => $strings[(int) $raw] ?? '',
                            'b' => $raw === '1' ? 'TRUE' : 'FALSE',
                            'e' => '',
                            'str' => $raw,
                            default => self::number($raw),
                        };
                    }
                    $cells[$idx] = trim($val);
                }
            }
            if ($cells) {
                $max = max(array_keys($cells));
                $row = array_fill(0, $max + 1, '');
                foreach ($cells as $i => $v) {
                    $row[$i] = $v;
                }
                $cells = $row;
            }
            yield $rowNum => $cells;
        }
        $reader->close();
    }

    protected static function sheetPath(\ZipArchive $zip, ?string $prefer): string
    {
        $wb = $zip->getFromName('xl/workbook.xml');
        $rels = $zip->getFromName('xl/_rels/workbook.xml.rels');
        if (!$wb || !$rels) {
            return 'xl/worksheets/sheet1.xml';
        }
        $targets = [];
        if (preg_match_all('/<Relationship\b[^>]*>/', $rels, $m)) {
            foreach ($m[0] as $tag) {
                if (preg_match('/Id="([^"]+)"/', $tag, $id) && preg_match('/Target="([^"]+)"/', $tag, $t)) {
                    $target = ltrim($t[1], '/');
                    $targets[$id[1]] = str_starts_with($target, 'xl/') ? $target : 'xl/' . $target;
                }
            }
        }
        $sheets = [];
        if (preg_match_all('/<sheet\b[^>]*>/', $wb, $m)) {
            foreach ($m[0] as $tag) {
                preg_match('/name="([^"]*)"/', $tag, $n);
                preg_match('/r:id="([^"]+)"/', $tag, $id) || preg_match('/\bid="([^"]+)"/', $tag, $id);
                if (!empty($id[1]) && isset($targets[$id[1]])) {
                    $sheets[html_entity_decode($n[1] ?? '', ENT_QUOTES | ENT_XML1)] = $targets[$id[1]];
                }
            }
        }
        if ($prefer) {
            foreach ($sheets as $name => $p) {
                if (strcasecmp(trim($name), $prefer) === 0) {
                    return $p;
                }
            }
        }
        return $sheets ? reset($sheets) : 'xl/worksheets/sheet1.xml';
    }

    protected static function sharedStrings(\ZipArchive $zip): array
    {
        $xml = $zip->getFromName('xl/sharedStrings.xml');
        if ($xml === false) {
            return [];
        }
        $out = [];
        $r = new \XMLReader();
        $r->XML($xml, null, LIBXML_NONET | LIBXML_COMPACT);
        while ($r->read()) {
            if ($r->nodeType === \XMLReader::ELEMENT && $r->localName === 'si') {
                $node = $r->expand();
                $s = '';
                foreach ($node->getElementsByTagName('t') as $t) {
                    // skip phonetic runs
                    if ($t->parentNode && $t->parentNode->nodeName === 'rPh') {
                        continue;
                    }
                    $s .= $t->textContent;
                }
                $out[] = $s;
                $r->next();
            }
        }
        $r->close();
        return $out;
    }

    /** "6.1512345678900003E+12" → "6151234567890"; "320" stays "320"; "1.5" stays "1.5". */
    protected static function number(string $raw): string
    {
        if ($raw === '' || !is_numeric($raw)) {
            return $raw;
        }
        if (stripos($raw, 'e') !== false) {
            $f = (float) $raw;
            if (abs($f) >= 1 && floor($f) == $f && abs($f) < 1e17) {
                return number_format($f, 0, '', '');
            }
            return rtrim(rtrim(sprintf('%.10F', $f), '0'), '.');
        }
        if (str_contains($raw, '.')) {
            // floating noise like 319.99999999999994 → 320
            $f = (float) $raw;
            $r = round($f, 6);
            return rtrim(rtrim(sprintf('%.6F', $r), '0'), '.');
        }
        return $raw;
    }

    public static function colIndex(string $letters): int
    {
        $n = 0;
        foreach (str_split(strtoupper($letters)) as $ch) {
            $n = $n * 26 + (ord($ch) - 64);
        }
        return $n - 1;
    }

    /** Excel serial (45567) or text date → 'Y-m-d', or null. */
    public static function excelDate(string $v): ?string
    {
        $v = trim($v);
        if ($v === '') {
            return null;
        }
        if (is_numeric($v) && (float) $v > 20000 && (float) $v < 80000) {
            return gmdate('Y-m-d', (int) round(((float) $v - 25569) * 86400));
        }
        foreach (['Y-m-d', 'd/m/Y', 'd-m-Y', 'd.m.Y', 'Y/m/d', 'd M Y', 'j M Y', 'd/m/y'] as $f) {
            $d = \DateTime::createFromFormat('!' . $f, $v);
            if ($d && $d->format($f) === $v) {
                return $d->format('Y-m-d');
            }
        }
        $t = strtotime($v);
        return $t ? date('Y-m-d', $t) : null;
    }

    protected static function toUtf8(string $s): string
    {
        if ($s === '' || mb_check_encoding($s, 'UTF-8')) {
            return $s;
        }
        return mb_convert_encoding($s, 'UTF-8', 'Windows-1252');
    }
}
