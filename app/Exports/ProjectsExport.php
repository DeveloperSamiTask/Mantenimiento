<?php

namespace App\Exports;

use Illuminate\View\View;
use Maatwebsite\Excel\Concerns\FromView;
use Maatwebsite\Excel\Concerns\WithChunkReading;

class ProjectsExport implements FromView, WithChunkReading
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    /*

    */
    public function view(): View
    {
        // $projects = json_decode($this->data['projects']); // ← ESTO ESTÁ MAL
        $projects = $this->data['projects']; // ← Así es correcto

        // views/exports/excel
        return view('exports.excel.projects', [
            'projects' => $projects,
        ]);
    }

    public function chunkSize(): int
    {
        return 1000;
    }
}
