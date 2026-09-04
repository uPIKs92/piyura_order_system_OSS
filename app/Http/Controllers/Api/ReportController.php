<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\Product;
use App\Models\Tenant;
use App\Models\User;
use App\Services\ReportService;
use App\Support\AppTime;
use App\Support\EfacturCsv;
use App\Support\TenantSettings;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function __construct(private ReportService $reportService) {}

    public function daily(Request $request): JsonResponse
    {
        $request->validate([
            'date' => 'nullable|date',
        ]);

        return response()->json($this->reportService->daily($request->query('date')));
    }

    public function dailyTrend(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);
        $this->validateRangeCap($request);

        return response()->json($this->reportService->dailyTrend($request->from, $request->to));
    }

    public function staff(Request $request): JsonResponse
    {
        $request->validate([
            'staff_id' => 'required',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);
        $this->validateRangeCap($request);

        if ($request->staff_id === 'all') {
            return response()->json($this->reportService->allStaff($request->from, $request->to));
        }

        $request->validate([
            'staff_id' => [
                'integer',
                Rule::exists('users', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
        ]);

        return response()->json($this->reportService->byStaff(
            (int) $request->staff_id,
            $request->from,
            $request->to
        ));
    }

    public function product(Request $request): JsonResponse
    {
        $request->validate([
            'product_id' => 'required',
            'from' => 'required|date',
            'to' => 'required|date|after_or_equal:from',
        ]);
        $this->validateRangeCap($request);

        if ($request->product_id === 'all') {
            return response()->json($this->reportService->allProducts($request->from, $request->to));
        }

        $request->validate([
            'product_id' => [
                'integer',
                Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $request->user()->tenant_id)),
            ],
        ]);

        return response()->json($this->reportService->byProduct(
            (int) $request->product_id,
            $request->from,
            $request->to
        ));
    }

    public function status(Request $request): JsonResponse
    {
        $request->validate([
            'from' => 'nullable|required_with:to|date',
            'to' => 'nullable|required_with:from|date|after_or_equal:from',
        ]);
        $this->validateRangeCap($request);

        return response()->json($this->reportService->byStatus($request->from, $request->to));
    }

    public function tax(Request $request): JsonResponse
    {
        $request->validate([
            'year' => 'nullable|integer|min:2000|max:2100',
        ]);

        $year = (int) $request->query('year', now()->year);

        return response()->json($this->reportService->taxReport($year));
    }

    public function efaktur(Request $request): StreamedResponse
    {
        $request->validate([
            'year' => 'required|integer|min:2000|max:2100',
            'month' => 'nullable|integer|min:1|max:12',
        ]);

        $year = (int) $request->query('year');
        $month = $request->query('month') !== null ? (int) $request->query('month') : null;
        $tenant = Tenant::find((int) app('currentTenantId'));

        $orders = Order::query()
            ->where('ppn_amount', '>', 0)
            ->whereIn('status', ['pending', 'diproses', 'dikirim', 'selesai'])
            ->whereYear('order_date', $year)
            ->when($month !== null, fn ($query) => $query->whereMonth('order_date', $month))
            ->with('items.product')
            ->orderBy('order_date')
            ->orderBy('id')
            ->lazy();

        $filename = sprintf(
            'efaktur-%s-%04d%s.csv',
            $tenant?->slug ?? 'tenant',
            $year,
            $month !== null ? sprintf('-%02d', $month) : ''
        );

        return response()->streamDownload(
            function () use ($orders): void {
                $out = fopen('php://output', 'wb');

                foreach ($orders as $order) {
                    fwrite($out, EfacturCsv::forOrder($order));
                }

                fclose($out);
            },
            $filename,
            ['Content-Type' => 'text/csv; charset=UTF-8']
        );
    }

    public function print(Request $request): Response
    {
        $request->validate([
            'type' => 'required|in:daily,staff,product,status,tax',
            'from' => 'required_unless:type,tax,status|required_with:to|date',
            'to' => 'required_unless:type,tax,status|required_with:from|date|after_or_equal:from',
            'download' => 'boolean',
        ]);
        $this->validateRangeCap($request);

        $type = $request->type;
        $from = $request->from;
        $to = $request->to;
        $tenant = Tenant::find((int) app('currentTenantId'));

        $data = [
            'type' => $type,
            'title' => match ($type) {
                'daily' => 'Laporan Harian',
                'staff' => 'Laporan Staff',
                'product' => 'Laporan Produk',
                'status' => 'Order per Status',
                'tax' => 'Laporan Pajak',
            },
            'from' => $from,
            'to' => $to,
            'printedAt' => AppTime::now(),
            'tenant' => $tenant,
            'logoDataUri' => $this->logoDataUri($tenant?->logo_path),
            'showPlatformCredit' => config('branding.show_platform_credit_on_invoice'),
            'platformName' => config('branding.platform_name'),
            'appName' => config('branding.app_name'),
        ];

        switch ($type) {
            case 'daily':
                $data['daily'] = $this->reportService->daily();
                $data['trend'] = $this->reportService->dailyTrend($from, $to);
                $data['tax'] = $this->reportService->taxReport(AppTime::now()->year);
                break;

            case 'staff':
                $request->validate(['staff_id' => 'required']);

                if ($request->staff_id === 'all') {
                    $data['staff'] = $this->reportService->allStaff($from, $to);
                } else {
                    $request->validate([
                        'staff_id' => [
                            'integer',
                            Rule::exists('users', 'id')->where('tenant_id', $request->user()->tenant_id),
                        ],
                    ]);
                    $staffId = (int) $request->staff_id;
                    $data['staff'] = $this->reportService->byStaff($staffId, $from, $to);
                    $data['staffName'] = User::query()
                        ->where('tenant_id', $request->user()->tenant_id)
                        ->find($staffId)?->name ?? 'Unknown';
                }
                break;

            case 'product':
                $request->validate(['product_id' => 'required']);

                if ($request->product_id === 'all') {
                    $data['productData'] = $this->reportService->allProducts($from, $to);
                } else {
                    $request->validate([
                        'product_id' => [
                            'integer',
                            Rule::exists('products', 'id')->where(fn ($q) => $q->where('tenant_id', $request->user()->tenant_id)),
                        ],
                    ]);
                    $productId = (int) $request->product_id;
                    $data['productData'] = $this->reportService->byProduct($productId, $from, $to);
                    $data['productName'] = Product::find($productId)?->nama ?? 'Unknown';
                }
                break;

            case 'status':
                $data['statusData'] = $this->reportService->byStatus($from, $to);
                break;

            case 'tax':
                $request->validate(['year' => 'required|integer|min:2000|max:2100']);

                $year = (int) $request->year;
                $data['from'] = $from = sprintf('%04d-01-01', $year);
                $data['to'] = $to = sprintf('%04d-12-31', $year);
                $data['year'] = $year;
                $data['taxReport'] = $this->reportService->taxReport($year);
                $data['npwp'] = TenantSettings::for()->pajakNpwp();
                break;
        }

        $filename = $type === 'tax'
            ? sprintf('laporan-pajak-%04d.pdf', (int) $request->year)
            : "laporan-{$type}-{$from}_{$to}.pdf";
        $pdf = Pdf::loadView('pdf.report', $data);

        if ($request->boolean('download')) {
            return $pdf->download($filename);
        }

        return response($pdf->output(), 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }

    private function validateRangeCap(Request $request): void
    {
        if (! $request->from || ! $request->to) {
            return;
        }

        $days = (int) Carbon::parse($request->from)->diffInDays(Carbon::parse($request->to));

        if ($days > 730) {
            throw ValidationException::withMessages([
                'to' => ['Rentang maksimal 730 hari.'],
            ]);
        }
    }

    private function logoDataUri(?string $logoPath): ?string
    {
        if (! $logoPath || ! Storage::disk('public')->exists($logoPath)) {
            return null;
        }

        $contents = Storage::disk('public')->get($logoPath);
        $mime = Storage::disk('public')->mimeType($logoPath) ?: 'image/png';

        return 'data:'.$mime.';base64,'.base64_encode($contents);
    }
}
