<?php

namespace App\Services;

use App\Enums\OrderStatus;
use App\Enums\PaymentMethod;
use App\Models\Category;
use App\Models\Customer;
use App\Models\Order;
use App\Models\OrderStatusLog;
use App\Models\Product;
use App\Models\ProductUnit;
use App\Models\User;
use App\Support\AppTime;
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
    public function importOrdersRows(array $rows, User $user, int $startRow = 2, ?int $tenantId = null, bool $forceFresh = false): array
    {
        $this->importTenantId = $tenantId ?? $user->tenant_id;

        return $this->importOrderData($rows, $user, $startRow, $forceFresh);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function readOrdersSheetRows(string $path): array
    {
        return $this->readSheetRows($path, config('import.sheets_legacy.orders_sheet'));
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

                $orderDate = $data[$map['order_date']] ?? AppTime::toDateString();
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
        $sanitized = array_map(
            fn (array $error) => array_map($this->sanitizeReportValue(...), $error),
            $errors
        );
        file_put_contents($path, json_encode($sanitized, JSON_PRETTY_PRINT));

        return $path;
    }

    private function sanitizeReportValue(mixed $value): mixed
    {
        if (is_string($value) && $value !== '' && in_array($value[0], ['=', '+', '-', '@'], true)) {
            return "'".$value;
        }

        return $value;
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
            $nama = trim((string) ($this->resolveColumnValue($data, $map['nama']) ?? ''));
            $satuan = strtolower(trim((string) ($this->resolveColumnValue($data, $map['satuan']) ?? '')));

            if ($nama === '' || $satuan === '') {
                continue;
            }

            $hargaJual = $this->numericValue($this->resolveColumnValue($data, $map['harga_jual']));
            if ($hargaJual === null || $hargaJual <= 0) {
                continue;
            }

            try {
                $hargaBeli = $this->numericValue($this->resolveColumnValue($data, $map['harga_beli'])) ?? 0;

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
    private function importOrderData(array $rows, User $user, int $startRow, bool $forceFresh = false): array
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

            $customerName = trim((string) ($this->resolveColumnValue($data, $map['customer_name']) ?? ''));
            $productName = trim((string) ($this->resolveColumnValue($data, $map['product_name']) ?? ''));
            $satuan = strtolower(trim((string) ($this->resolveColumnValue($data, $map['satuan']) ?? '')));

            if ($customerName === '' && $productName === '') {
                continue;
            }

            $dedupeKey = $this->orderRowDedupeKey($data, $map);
            if (! $forceFresh && \Illuminate\Support\Facades\Cache::has($dedupeKey)) {
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

                $orderDate = $this->parseDate($this->resolveColumnValue($data, $map['order_date']));
                $quantity = (int) ($this->resolveColumnValue($data, $map['quantity']) ?? 1);
                $delivery = trim((string) ($this->resolveColumnValue($data, $map['delivery_status']) ?? ''));
                $paymentStatus = trim((string) ($this->resolveColumnValue($data, $map['payment_status']) ?? ''));

                $resolved = $this->resolveImportedOrderStatus($paymentStatus, $delivery);

                $order = $this->importCompletedOrder(
                    $user,
                    $unit,
                    $quantity,
                    $customerName,
                    $orderDate,
                    $resolved['status'],
                );

                if ($resolved['should_pay']) {
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

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array{total: int, updated: int, paid: int, skipped: int, missing: int, errors: array}
     */
    public function repairOrderStatusesFromRows(array $rows, User $user, int $startRow = 2, ?int $tenantId = null): array
    {
        $this->importTenantId = $tenantId ?? $user->tenant_id;
        $map = config('import.sheets_legacy.orders');

        $total = count($rows);
        $updated = 0;
        $paid = 0;
        $skipped = 0;
        $missing = 0;
        $errors = [];

        foreach ($rows as $index => $data) {
            $rowNumber = $index + $startRow;
            $customerName = trim((string) ($this->resolveColumnValue($data, $map['customer_name']) ?? ''));
            $productName = trim((string) ($this->resolveColumnValue($data, $map['product_name']) ?? ''));
            $satuan = strtolower(trim((string) ($this->resolveColumnValue($data, $map['satuan']) ?? '')));

            if ($customerName === '' && $productName === '') {
                continue;
            }

            try {
                if ($productName === '' || $satuan === '') {
                    throw new \RuntimeException('Product name and unit required');
                }

                $orderDate = $this->parseDate($this->resolveColumnValue($data, $map['order_date']));
                $quantity = (int) ($this->resolveColumnValue($data, $map['quantity']) ?? 1);
                $delivery = trim((string) ($this->resolveColumnValue($data, $map['delivery_status']) ?? ''));
                $paymentStatus = trim((string) ($this->resolveColumnValue($data, $map['payment_status']) ?? ''));
                $resolved = $this->resolveImportedOrderStatus($paymentStatus, $delivery);

                $orders = $this->findImportedOrders($customerName, $orderDate, $productName, $satuan, $quantity);
                if ($orders->isEmpty()) {
                    $missing++;

                    continue;
                }

                foreach ($orders as $order) {
                    $changed = false;

                    if ($order->status !== $resolved['status']) {
                        $fromStatus = $order->status;

                        // Import repair is deliberately machine-exempt from
                        // transitionStatus: these are historical Sheets orders,
                        // so stock is intentionally left untouched. We still
                        // record the audit log and bump the version so
                        // concurrent editors are invalidated.
                        $order->update([
                            'status' => $resolved['status'],
                            'version' => $order->version + 1,
                        ]);

                        OrderStatusLog::create([
                            'order_id' => $order->id,
                            'from_status' => $fromStatus->value,
                            'to_status' => $resolved['status']->value,
                            'changed_by' => $user->id,
                            'reason' => 'Repaired from Sheets import',
                            'created_at' => now(),
                        ]);

                        $changed = true;
                    }

                    if ($resolved['should_pay'] && (float) $order->total_paid <= 0) {
                        $this->paymentService->addPayment($order->fresh(), $user, [
                            'amount' => (float) $order->grand_total,
                            'metode' => PaymentMethod::Cash->value,
                            'paid_at' => $orderDate,
                            'notes' => 'Repaired from Sheets import (lunas)',
                        ]);
                        $paid++;
                        $changed = true;
                    }

                    if ($changed) {
                        $updated++;
                    } else {
                        $skipped++;
                    }
                }
            } catch (\Throwable $e) {
                $errors[] = ['row' => $rowNumber, 'field' => 'order', 'reason' => $e->getMessage()];
            }
        }

        return compact('total', 'updated', 'paid', 'skipped', 'missing', 'errors');
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

        return 'sheets_import.row.'.$this->importTenantId.'.'.\App\Support\TenantSettings::for($this->importTenantId)->sheetsImportEpoch().'.'.hash('sha256', json_encode($payload));
    }

    private function importCompletedOrder(
        User $user,
        ProductUnit $unit,
        int $quantity,
        string $customerName,
        string $orderDate,
        OrderStatus $status,
    ): Order {
        $order = DB::transaction(function () use ($user, $unit, $quantity, $customerName, $orderDate, $status) {
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
                'cost_snapshot' => (float) $unit->harga_beli,
                'quantity' => $quantity,
                'subtotal' => $lineSubtotal,
            ]);

            $this->orderService->recalculateTotals($order->fresh());

            Customer::upsertFromOrder($order);

            return $order->fresh(['items', 'payments']);
        });

        OrderService::bumpAggregatesVersion($user->tenant_id);

        return $order;
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

        $unit = $product->units()->where('satuan', $satuan)->first();

        if ($unit === null) {
            $unit = $product->units()->create([
                'satuan' => $satuan,
                'harga_jual' => $hargaJual,
                'harga_beli' => $hargaBeli,
                'stok' => 0,
                'is_default' => $isFirst,
            ]);
        } else {
            $unit->update([
                'harga_jual' => $hargaJual,
                'harga_beli' => $hargaBeli,
            ]);
        }

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
            return AppTime::toDateString();
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

        if (is_int($value) || is_float($value)) {
            return (float) $value;
        }

        $normalized = trim((string) $value);

        if ($normalized === '' || $normalized === '-' || $normalized === '.') {
            return null;
        }

        if (preg_match('/^\d{1,3}(\.\d{3})+(,\d+)?$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
            $normalized = str_replace(',', '.', $normalized);

            return (float) $normalized;
        }

        if (is_numeric($normalized)) {
            return (float) $normalized;
        }

        $normalized = preg_replace('/[^\d,.-]/', '', $normalized) ?? '';


        if ($normalized === '' || $normalized === '-' || $normalized === '.') {
            return null;
        }

        if (str_contains($normalized, ',') && str_contains($normalized, '.')) {
            if (strrpos($normalized, ',') > strrpos($normalized, '.')) {
                $normalized = str_replace('.', '', $normalized);
                $normalized = str_replace(',', '.', $normalized);
            } else {
                $normalized = str_replace(',', '', $normalized);
            }
        } elseif (str_contains($normalized, ',')) {
            $normalized = preg_match('/,\d{3}$/', $normalized)
                ? str_replace(',', '', $normalized)
                : str_replace(',', '.', $normalized);
        } elseif (preg_match('/\.\d{3}$/', $normalized)) {
            $normalized = str_replace('.', '', $normalized);
        }

        return is_numeric($normalized) ? (float) $normalized : null;
    }

    /**
     * @param  string|array<int, string>  $keys
     */
    private function resolveColumnValue(array $data, string|array $keys): mixed
    {
        foreach ((array) $keys as $key) {
            if (array_key_exists($key, $data)) {
                return $data[$key];
            }
        }

        $normalized = [];
        foreach ($data as $header => $value) {
            $normalized[strtolower(trim((string) $header))] = $value;
        }

        foreach ((array) $keys as $key) {
            $lookup = strtolower(trim($key));
            if (array_key_exists($lookup, $normalized)) {
                return $normalized[$lookup];
            }
        }

        return null;
    }

    /**
     * @return array{status: OrderStatus, should_pay: bool}
     */
    private function resolveImportedOrderStatus(string $paymentStatus, string $delivery): array
    {
        $legacy = config('import.sheets_legacy');
        $payment = $this->normalizeImportStatusToken($paymentStatus);
        $deliveryToken = $this->normalizeImportStatusToken($delivery);

        $cancelled = collect($legacy['cancelled_status_values'] ?? [])
            ->map(fn (string $value) => $this->normalizeImportStatusToken($value))
            ->all();

        if (in_array($payment, $cancelled, true) || in_array($deliveryToken, $cancelled, true)) {
            return ['status' => OrderStatus::Cancelled, 'should_pay' => false];
        }

        $statusMap = collect($legacy['delivery_map'] ?? [])
            ->mapWithKeys(fn (string $value, string $key) => [$this->normalizeImportStatusToken($key) => $value])
            ->all();

        $mapped = $statusMap[$deliveryToken] ?? null;
        if ($mapped === null && $deliveryToken !== '') {
            $mapped = OrderStatus::tryFrom($deliveryToken)?->value;
        }
        if ($mapped === null || $mapped === '') {
            $mapped = 'selesai';
        }

        $status = OrderStatus::tryFrom($mapped) ?? OrderStatus::Selesai;

        $paidValues = collect($legacy['payment_status_paid'] ?? [])
            ->map(fn (string $value) => $this->normalizeImportStatusToken($value))
            ->all();

        $shouldPay = in_array($payment, $paidValues, true) && $status !== OrderStatus::Cancelled;

        // Delivered but unpaid orders stay at dikirim; selesai requires payment.
        if ($status === OrderStatus::Selesai && ! $shouldPay) {
            $status = OrderStatus::Dikirim;
        }

        return ['status' => $status, 'should_pay' => $shouldPay];
    }

    private function normalizeImportStatusToken(string $value): string
    {
        return strtolower(trim($value));
    }

    private function findImportedOrders(
        string $customerName,
        string $orderDate,
        string $productName,
        string $satuan,
        int $quantity,
    ): \Illuminate\Support\Collection {
        return Order::query()
            ->where('tenant_id', $this->importTenantId)
            ->where('customer_name', $customerName)
            ->whereDate('order_date', $orderDate)
            ->whereHas('items', fn ($query) => $query
                ->where('product_name', $productName)
                ->where('satuan', $satuan)
                ->where('quantity', $quantity))
            ->orderBy('id')
            ->get();
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
