<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;

class ImportTemplateService
{
    public function path(): string
    {
        return config('import.template_path');
    }

    public function ensureExists(): string
    {
        $path = $this->path();
        File::ensureDirectoryExists(dirname($path));

        if (! File::exists($path)) {
            $this->write($path);
        }

        return $path;
    }

    private function write(string $path): void
    {
        $spreadsheet = new Spreadsheet;

        $products = $spreadsheet->getActiveSheet();
        $products->setTitle('Products');
        $products->fromArray([
            ['Product Name', 'Unit', 'COGS', 'Selling Price'],
            ['Omega Egg Negeri', 'pack', 28000, 35000],
            ['Omega Egg Negeri', 'krat', 80000, 90000],
            ['Dried Mango', 'ori', 80000, 90000],
        ]);

        $orders = $spreadsheet->createSheet();
        $orders->setTitle('Orders');
        $orders->fromArray([
            ['Date', 'Customer Name', 'Product Name', 'Qty', 'Unit', 'Status', 'Delivery'],
            ['6/7/2026 0:00:00', 'Dewi', 'Omega Egg Negeri', 1, 'krat', 'lunas', 'Selesai'],
        ]);

        (new Xlsx($spreadsheet))->save($path);
    }
}
