<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Order;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Maatwebsite\Excel\Facades\Excel;

class OrdersImportService
{
    /** @var array<string, Product> */
    private array $productCache = [];

    /** @var array<string, ProductUnit> */
    private array $unitCache = [];

    private ?int $importTenantId = null;

    public function __construct(
        private OrderService $orderService,
        private PaymentService $paymentService,
    ) {}

    public function detectMapping(string $path): string
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));
        if (! in_array($extension, ['xlsx', 'xls'], true)) {
            return 'config';
        }

        $sheets = Excel::toArray([], $path);
        if (count($sheets) >= 2) {
            $productHeader = array_map('strtolower', array_map('strval', $sheets[0][0] ?? []));
            $orderHeader = array_map('strtolower', array_map('strval', $sheets[1][0] ?? []));
            if (
                in_array('product name', $productHeader, true)
                && in_array('customer name', $orderHeader, true)
            ) {
                return 'sheets_legacy';
            }
        }

        return 'config';
    }

    /**
     * @return array{products: array, orders: array, total: int, success: int, failed: int, errors: array}
     */
    public function importWorkbook(string $path, User $user): array
    {
        $this->importTenantId ??= $user->tenant_id;
        if ($this->importTenantId) {
            app()->instance('currentTenantId', $this->importTenantId);
        }

        $products = $this->importProductsFromWorkbook($path);
        $orders = $this->importOrdersFromWorkbook($path, $user);

        return [
            'products' => $products,
            'orders' => $orders,
            'mapping' => 'sheets_legacy',
            'total' => $products['total'] + $orders['total'],
            'success' => $products['success'] + $orders['success'],
            'failed' => $products['failed'] + $orders['failed'],
            'errors' => array_merge($products['errors'], $orders['errors']),
        ];
    }

    /**
     * @return array{total: int, success: int, failed: int, errors: array<int, array{row: int, field: string, reason: string}>}
     */
    public function importProductsRows(array $rows, ?int $tenantId = null): array
    {
        $this->importTenantId = $tenantId ?? (app()->bound('currentTenantId') ? app('currentTenantId') : null);

        return $this->importProductData($rows, 2);
    }

    /**
     * @return array{total: int, success: int, failed: int, errors: array<int, array{row: int, field: string, reason: string}>}
     */
    public function importOrdersRows(array $rows, User $user, int $startRow = 2, ?int $tenantId = null): array
    {
        $this->importTenantId = $tenantId ?? $user->tenant_id;

        return $this->importOrderData($rows, $user, $startRow);
    }

    /**
     * @return array{total: int, success: int, failed: int, errors: array<int, array{row: int, field: string, reason: string}>}
     */
    public function importFile(string $path, User $user, ?string $mapping = null): array
    {
        $this->importTenantId = $user->tenant_id;
        if ($this->importTenantId) {
            app()->instance('currentTenantId', $this->importTenantId);
        }

        $mapping ??= $this->detectMapping($path);

        if ($mapping === 'sheets_legacy') {
            return $this->importWorkbook($path, $user);
        }

        $rows = $this->readRows($path);
        $map = config('import.column_map');
        $required = ['customer_name', 'product_name', 'quantity', 'order_date'];

        $total = count($rows);
        $success = 0;
        $failed = 0;
        $errors = [];

        $this->resetImportCache();

        foreach ($rows as $index => $data) {
            $rowNumber = $index + 2;

            foreach ($required as $field) {
                $header = $map[$field] ?? $field;
                if (empty($data[$header] ?? null) && $field !== 'customer_name') {
                    $failed++;
                    $errors[] = ['row' => $rowNumber, 'field' => $field, 'reason' => 'Required field missing'];

                    continue 2;
                }
            }

            try {
                $productName = trim((string) ($data[$map['product_name']] ?? ''));
                $product = $this->findProductByName($productName);
                if (! $product) {
                    throw new \RuntimeException("Product not found: {$productName}");
                }

                $unit = $product->defaultUnit();
                if (! $unit) {
                    throw new \RuntimeException("Product has no unit: {$productName}");
                }

                $orderDate = $data[$map['order_date']] ?? now()->toDateString();
                if (! strtotime((string) $orderDate)) {
                    throw new \RuntimeException('Invalid order_date format');
                }

                $order = $this->orderService->create($user, [
                    'customer_name' => $data[$map['customer_name']] ?? null,
                    'customer_phone' => $data[$map['customer_phone']] ?? null,
                    'order_date' => date('Y-m-d', strtotime((string) $orderDate)),
                    'items' => [[
                        'product_unit_id' => $unit->id,
                        'quantity' => (int) ($data[$map['quantity']] ?? 1),
                    ]],
                ]);

                $status = strtolower((string) ($data[$map['status']] ?? 'draft'));
                if ($status !== 'draft') {
                    $this->orderService->update($order, $user, [
                        'version' => $order->version,
                        'status' => $status,
                    ]);
                }

                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'field' => 'row', 'reason' => $e->getMessage()];
            }
        }

        return compact('total', 'success', 'failed', 'errors');
    }

    public function writeErrorReport(array $errors): string
    {
        $dir = config('import.path').'/reports';
        File::ensureDirectoryExists($dir);
        $filename = 'import-'.now()->format('Y-m-d-His').'.json';
        $path = "{$dir}/{$filename}";
        file_put_contents($path, json_encode($errors, JSON_PRETTY_PRINT));

        return $path;
    }

    public function countRows(string $path, ?string $mapping = null): int
    {
        $mapping ??= $this->detectMapping($path);

        if ($mapping === 'sheets_legacy') {
            $legacy = config('import.sheets_legacy');
            $rows = $this->readSheetRows($path, $legacy['orders_sheet']);

            return count($rows);
        }

        return count($this->readRows($path));
    }

    private function importProductsFromWorkbook(string $path): array
    {
        $legacy = config('import.sheets_legacy');
        $rows = $this->readSheetRows($path, $legacy['products_sheet']);

        return $this->importProductData($rows, 2);
    }

    private function importOrdersFromWorkbook(string $path, User $user): array
    {
        $legacy = config('import.sheets_legacy');
        $rows = $this->readSheetRows($path, $legacy['orders_sheet']);

        return $this->importOrderData($rows, $user, 2);
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total: int, success: int, failed: int, errors: array}
     */
    private function importProductData(array $rows, int $startRow): array
    {
        $map = config('import.sheets_legacy.products');
        $category = Category::firstOrCreate(
            ['tenant_id' => $this->importTenantId, 'nama' => config('import.sheets_legacy.default_category')],
            ['slug' => 'import', 'is_active' => true],
        );

        $total = count($rows);
        $success = 0;
        $failed = 0;
        $errors = [];

        $this->resetImportCache();

        foreach ($rows as $index => $data) {
            $rowNumber = $index + $startRow;
            $nama = trim((string) ($data[$map['nama']] ?? ''));
            $satuan = strtolower(trim((string) ($data[$map['satuan']] ?? '')));

            if ($nama === '' || $satuan === '') {
                continue;
            }

            $hargaJual = $this->numericValue($data[$map['harga_jual']] ?? null);
            if ($hargaJual === null || $hargaJual <= 0) {
                continue;
            }

            try {
                $hargaBeli = $this->numericValue($data[$map['harga_beli']] ?? null) ?? 0;

                $this->upsertProductUnit($nama, $satuan, $category->id, $hargaJual, $hargaBeli);

                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'field' => 'product', 'reason' => $e->getMessage()];
            }
        }

        return compact('total', 'success', 'failed', 'errors');
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total: int, success: int, failed: int, errors: array}
     */
    private function importOrderData(array $rows, User $user, int $startRow): array
    {
        $map = config('import.sheets_legacy.orders');
        $legacy = config('import.sheets_legacy');

        $total = count($rows);
        $success = 0;
        $failed = 0;
        $skipped = 0;
        $errors = [];

        $this->resetImportCache();

        foreach ($rows as $index => $data) {
            $rowNumber = $index + $startRow;

            $customerName = trim((string) ($data[$map['customer_name']] ?? ''));
            $productName = trim((string) ($data[$map['product_name']] ?? ''));
            $satuan = strtolower(trim((string) ($data[$map['satuan']] ?? '')));

            if ($customerName === '' && $productName === '') {
                continue;
            }

            $dedupeKey = $this->orderRowDedupeKey($data, $map);
            if (\Illuminate\Support\Facades\Cache::has($dedupeKey)) {
                $skipped++;

                continue;
            }

            try {
                if ($productName === '' || $satuan === '') {
                    throw new \RuntimeException('Product name and unit required');
                }

                $unit = $this->findUnit($productName, $satuan);
                if (! $unit) {
                    throw new \RuntimeException("Product not found: {$productName} ({$satuan})");
                }

                $orderDate = $this->parseDate($data[$map['order_date']] ?? null);
                $quantity = (int) ($data[$map['quantity']] ?? 1);
                $delivery = trim((string) ($data[$map['delivery_status']] ?? ''));
                $paymentStatus = trim((string) ($data[$map['payment_status']] ?? ''));

                $status = $legacy['delivery_map'][$delivery] ?? strtolower($delivery);
                if ($status === '') {
                    $status = 'selesai';
                }

                $order = $this->importCompletedOrder(
                    $user,
                    $unit,
                    $quantity,
                    $customerName,
                    $orderDate,
                    $status,
                );

                if (in_array($paymentStatus, $legacy['payment_status_paid'], true)) {
                    $order->refresh();
                    $this->paymentService->addPayment($order, $user, [
                        'amount' => (float) $order->grand_total,
                        'metode' => PaymentMethod::Cash->value,
                        'paid_at' => $orderDate,
                        'notes' => 'Imported from Sheets (lunas)',
                    ]);
                }

                \Illuminate\Support\Facades\Cache::forever($dedupeKey, true);
                $success++;
            } catch (\Throwable $e) {
                $failed++;
                $errors[] = ['row' => $rowNumber, 'field' => 'order', 'reason' => $e->getMessage()];
            }
        }

        return compact('total', 'success', 'failed', 'skipped', 'errors');
    }

    private function orderRowDedupeKey(array $data, array $map): string
    {
        $payload = [
            $data[$map['order_date']] ?? '',
            $data[$map['customer_name']] ?? '',
            $data[$map['product_name']] ?? '',
            $data[$map['quantity']] ?? '',
            $data[$map['satuan']] ?? '',
            $data[$map['payment_status']] ?? '',
            $data[$map['delivery_status']] ?? '',
        ];

        return 'sheets_import.row.'.$this->importTenantId.'.'.hash('sha256', json_encode($payload));
    }

    private function importCompletedOrder(
        User $user,
        ProductUnit $unit,
        int $quantity,
        string $customerName,
        string $orderDate,
        string $statusValue,
    ): Order {
        return DB::transaction(function () use ($user, $unit, $quantity, $customerName, $orderDate, $statusValue) {
            $status = OrderStatus::tryFrom($statusValue) ?? OrderStatus::Selesai;
            $price = (float) $unit->harga_jual;
            $lineSubtotal = $price * $quantity;
            $product = $unit->product;

            $order = Order::create([
                'tenant_id' => $user->tenant_id,
                'user_id' => $user->id,
                'invoice_no' => $this->orderService->generateInvoiceNo($user->tenant_id),
                'status' => $status,
                'customer_name' => $customerName,
                'order_date' => $orderDate,
                'ppn_percentage' => \App\Support\PpnSettings::percentage(),
                'version' => 1,
            ]);

            $order->items()->create([
                'product_id' => $product->id,
                'product_unit_id' => $unit->id,
                'product_name' => $product->nama,
                'satuan' => $unit->satuan,
                'price_snapshot' => $price,
                'quantity' => $quantity,
                'subtotal' => $lineSubtotal,
            ]);

            $this->orderService->recalculateTotals($order->fresh());

            return $order->fresh(['items', 'payments']);
        });
    }

    private function upsertProductUnit(
        string $nama,
        string $satuan,
        int $categoryId,
        float $hargaJual,
        float $hargaBeli,
    ): ProductUnit {
        if (isset($this->productCache[$nama])) {
            $product = $this->productCache[$nama];
        } else {
            $product = Product::firstOrCreate(
                ['tenant_id' => $this->importTenantId, 'nama' => $nama],
                [
                    'category_id' => $categoryId,
                    'slug' => Str::slug($nama),
                    'is_active' => true,
                ],
            );
            $product->load('units');
            $this->productCache[$nama] = $product;
        }

        $isFirst = $product->units->isEmpty();

        $unit = $product->units()->updateOrCreate(
            ['satuan' => $satuan],
            [
                'harga_jual' => $hargaJual,
                'harga_beli' => $hargaBeli,
                'stok' => 0,
                'is_default' => $isFirst,
            ],
        );

        $product->load('units');
        $this->productCache[$nama] = $product;
        $this->unitCache[$this->unitCacheKey($nama, $satuan)] = $unit->loadMissing('product');

        return $unit;
    }

    private function findUnit(string $nama, string $satuan): ?ProductUnit
    {
        $key = $this->unitCacheKey($nama, $satuan);
        if (isset($this->unitCache[$key])) {
            return $this->unitCache[$key];
        }

        $unit = ProductUnit::query()
            ->where('satuan', strtolower(trim($satuan)))
            ->whereHas('product', fn ($q) => $q
                ->where('tenant_id', $this->importTenantId)
                ->where('nama', $nama))
            ->with('product')
            ->first();

        if ($unit) {
            $this->unitCache[$key] = $unit;
        }

        return $unit;
    }

    private function findProductByName(string $name): ?Product
    {
        if (isset($this->productCache[$name])) {
            return $this->productCache[$name];
        }

        $product = Product::with('units')->where('nama', $name)->first();
        if ($product) {
            $this->productCache[$name] = $product;
        }

        return $product;
    }

    private function unitCacheKey(string $nama, string $satuan): string
    {
        return strtolower(trim($nama)).'|'.strtolower(trim($satuan));
    }

    private function resetImportCache(): void
    {
        $this->productCache = [];
        $this->unitCache = [];
    }

    private function parseDate(mixed $value): string
    {
        if ($value === null || $value === '') {
            return now()->toDateString();
        }

        if (is_numeric($value)) {
            return Carbon::instance(\PhpOffice\PhpSpreadsheet\Shared\Date::excelToDateTimeObject((float) $value))->toDateString();
        }

        return Carbon::parse((string) $value)->toDateString();
    }

    private function numericValue(mixed $value): ?float
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            return (float) $value;
        }

        $cleaned = preg_replace('/[^\d.]/', '', (string) $value);

        return $cleaned !== '' ? (float) $cleaned : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readSheetRows(string $path, string $sheetName): array
    {
        $sheets = Excel::toArray([], $path);
        $legacy = config('import.sheets_legacy');

        $index = match (true) {
            strcasecmp($sheetName, $legacy['products_sheet']) === 0 => $legacy['products_sheet_index'] ?? 0,
            strcasecmp($sheetName, $legacy['orders_sheet']) === 0 => $legacy['orders_sheet_index'] ?? 1,
            default => null,
        };

        if ($index !== null && isset($sheets[$index])) {
            $sheet = $sheets[$index];
        } elseif (isset($sheets[$sheetName])) {
            $sheet = $sheets[$sheetName];
        } else {
            return [];
        }

        if (empty($sheet)) {
            return [];
        }

        $header = array_map('strval', $sheet[0]);
        $rows = [];

        foreach (array_slice($sheet, 1) as $row) {
            $row = array_pad($row, count($header), null);
            $combined = array_combine($header, $row);
            if ($combined === false) {
                continue;
            }
            if (count(array_filter($combined, fn ($v) => $v !== null && $v !== '')) === 0) {
                continue;
            }
            $rows[] = $combined;
        }

        return $rows;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function readRows(string $path): array
    {
        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION));

        if ($extension === 'csv') {
            $handle = fopen($path, 'r');
            $header = fgetcsv($handle);
            $rows = [];
            while (($row = fgetcsv($handle)) !== false) {
                if (count($row) === count($header)) {
                    $rows[] = array_combine($header, $row);
                }
            }
            fclose($handle);

            return $rows;
        }

        if (in_array($extension, ['xlsx', 'xls'], true)) {
            $sheets = Excel::toArray([], $path);
            $firstSheet = array_key_first($sheets);

            return $this->readSheetRows($path, (string) ($firstSheet ?? 'Sheet1'));
        }

        throw new \InvalidArgumentException("Unsupported file type: {$extension}");
    }
}
