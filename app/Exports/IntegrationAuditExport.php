<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class IntegrationAuditExport implements FromCollection, WithHeadings
{
    private Collection $rows;
    private array $headings;

    public function __construct(Collection $rows, array $headings)
    {
        $this->rows = $rows;
        $this->headings = $headings;
    }

    public function collection()
    {
        return $this->rows;
    }

    public function headings(): array
    {
        return $this->headings;
    }
}
