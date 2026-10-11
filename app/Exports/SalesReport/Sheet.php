<?php

namespace App\Exports\SalesReport;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStrictNullComparison;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithTitle;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

/** One plain sheet: title, a bold header row and data rows. */
class Sheet implements FromArray, WithTitle, WithHeadings, ShouldAutoSize, WithStyles, WithStrictNullComparison
{
    public function __construct(protected string $title, protected array $headings, protected array $rows)
    {
    }

    public function array(): array
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }

    public function title(): string
    {
        return mb_substr($this->title, 0, 31);
    }

    public function styles(Worksheet $sheet)
    {
        $sheet->freezePane('A2');
        return [1 => ['font' => ['bold' => true]]];
    }
}
