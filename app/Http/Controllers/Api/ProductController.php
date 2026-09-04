<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Product;
use App\Models\ProductUnit;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class ProductController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Product::class);
    }

    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'page' => 'sometimes|integer|min:1',
            'per_page' => 'sometimes|integer|min:1|max:100',
        ]);

        $products = Product::with(['category', 'units'])
            ->orderBy('id')
            ->paginate((int) ($validated['per_page'] ?? 20));

        if ($request->user()->isStaff()) {
            $products->getCollection()->each(fn (Product $product) => $this->hideUnitCosts($product));
        }

        return response()->json($products);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => [
                'nullable',
                Rule::exists('categories', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'nama' => 'required|string|max:255',
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')->where('tenant_id', auth()->user()->tenant_id),
            ],
            'barcode' => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
            'units' => 'required|array|min:1',
            'units.*.satuan' => 'required|string|max:50',
            'units.*.harga_jual' => 'required|numeric|min:0',
            'units.*.harga_beli' => 'required|numeric|min:0',
            'units.*.stok' => 'required|integer|min:0',
            'units.*.min_stok' => 'nullable|integer|min:0',
            'units.*.is_default' => 'boolean',
        ]);

        $units = $validated['units'];
        unset($validated['units']);

        $product = DB::transaction(function () use ($validated, $units) {
            $validated['slug'] = $this->uniqueSlug($validated['nama']);
            $product = Product::create($validated);
            $this->syncUnits($product, $units);

            return $product;
        });

        return response()->json($product->load(['category', 'units']), 201);
    }

    public function show(Request $request, Product $product): JsonResponse
    {
        $product->load(['category', 'units']);

        if ($request->user()->isStaff()) {
            $this->hideUnitCosts($product);
        }

        return response()->json($product);
    }

    public function update(Request $request, Product $product): JsonResponse
    {
        $validated = $request->validate([
            'category_id' => [
                'sometimes',
                'nullable',
                Rule::exists('categories', 'id')->where('tenant_id', $request->user()->tenant_id),
            ],
            'nama' => 'sometimes|string|max:255',
            'sku' => [
                'nullable',
                'string',
                'max:100',
                Rule::unique('products', 'sku')
                    ->where('tenant_id', auth()->user()->tenant_id)
                    ->ignore($product),
            ],
            'barcode' => 'nullable|string|max:100',
            'deskripsi' => 'nullable|string',
            'is_active' => 'boolean',
            'units' => 'sometimes|array|min:1',
            'units.*.id' => 'nullable|integer|exists:product_units,id',
            'units.*.satuan' => 'required_with:units|string|max:50',
            'units.*.harga_jual' => 'required_with:units|numeric|min:0',
            'units.*.harga_beli' => 'required_with:units|numeric|min:0',
            'units.*.stok' => 'nullable|integer|min:0',
            'units.*.min_stok' => 'nullable|integer|min:0',
            'units.*.is_default' => 'boolean',
        ]);

        $units = $validated['units'] ?? null;
        unset($validated['units']);

        DB::transaction(function () use ($product, $validated, $units) {
            if (isset($validated['nama'])) {
                $validated['slug'] = $this->uniqueSlug($validated['nama'], $product->id);
            }

            $product->update($validated);

            if ($units !== null) {
                $this->syncUnits($product, $units);
            }
        });

        return response()->json($product->fresh(['category', 'units']));
    }

    public function destroy(Product $product): JsonResponse
    {
        if ($product->photo_path) {
            Storage::disk('public')->delete($product->photo_path);
        }

        $product->delete();

        return response()->json(['message' => 'Product deleted successfully.']);
    }

    public function uploadPhoto(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        $request->validate([
            'photo' => 'required|file|max:2048',
        ]);

        $file = $request->file('photo');
        $allowed = [
            'image/png' => 'png',
            'image/jpeg' => 'jpg',
            'image/webp' => 'webp',
        ];
        $mime = $file->getMimeType() ?: '';
        if (! isset($allowed[$mime])) {
            return response()->json(['message' => 'Tipe file foto tidak valid.'], 422);
        }

        $ext = $allowed[$mime];
        $dir = "tenants/{$product->tenant_id}/products/{$product->id}";
        $path = "{$dir}/photo.{$ext}";

        if ($product->photo_path) {
            Storage::disk('public')->delete($product->photo_path);
        }

        $file->storeAs($dir, "photo.{$ext}", 'public');
        $product->update(['photo_path' => $path]);

        return response()->json($product->fresh(['category', 'units']));
    }

    public function deletePhoto(Request $request, Product $product): JsonResponse
    {
        $this->authorize('update', $product);

        if ($product->photo_path) {
            Storage::disk('public')->delete($product->photo_path);
            $product->update(['photo_path' => null]);
        }

        return response()->json($product->fresh(['category', 'units']));
    }

    private function hideUnitCosts(Product $product): void
    {
        $product->units->each(fn (ProductUnit $unit) => $unit->makeHidden('harga_beli'));
    }

    private function uniqueSlug(string $nama, ?int $ignoreId = null): string
    {
        $slug = Str::slug($nama);
        $base = $slug;
        $i = 1;

        while (Product::withTrashed()
            ->where('slug', $slug)
            ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
            ->exists()) {
            $slug = $base.'-'.$i++;
        }

        return $slug;
    }

    /**
     * @param  array<int, array<string, mixed>>  $units
     */
    private function syncUnits(Product $product, array $units): void
    {
        $this->ensureDefaultUnit($units);

        $keepIds = [];
        foreach ($units as $unitData) {
            $satuan = strtolower(trim((string) $unitData['satuan']));
            $minStok = (int) ($unitData['min_stok'] ?? 5);
            $newStok = (int) ($unitData['stok'] ?? 0);

            if (! empty($unitData['id'])) {
                $unit = ProductUnit::where('product_id', $product->id)
                    ->where('id', $unitData['id'])
                    ->firstOrFail();

                $unit->update([
                    'satuan' => $satuan,
                    'harga_jual' => $unitData['harga_jual'],
                    'harga_beli' => $unitData['harga_beli'],
                    'min_stok' => $minStok,
                    'is_default' => (bool) ($unitData['is_default'] ?? false),
                ]);
            } else {
                $unit = $product->units()->firstOrNew(['satuan' => $satuan]);

                if ($unit->exists) {
                    $unit->update([
                        'harga_jual' => $unitData['harga_jual'],
                        'harga_beli' => $unitData['harga_beli'],
                    ]);
                } else {
                    $unit->fill([
                        'harga_jual' => $unitData['harga_jual'],
                        'harga_beli' => $unitData['harga_beli'],
                        'stok' => $newStok,
                        'min_stok' => $minStok,
                        'is_default' => (bool) ($unitData['is_default'] ?? false),
                    ]);
                    $unit->save();
                }
            }

            $keepIds[] = $unit->id;
        }

        $product->units()->whereNotIn('id', $keepIds)->delete();
        $this->normalizeDefaultUnit($product);
    }

    /**
     * @param  array<int, array<string, mixed>>  $units
     */
    private function ensureDefaultUnit(array &$units): void
    {
        $defaults = array_filter($units, fn ($u) => ! empty($u['is_default']));
        if ($defaults === []) {
            $units[0]['is_default'] = true;
        }
    }

    private function normalizeDefaultUnit(Product $product): void
    {
        $units = $product->units()->orderBy('id')->get();
        if ($units->isEmpty()) {
            return;
        }

        $default = $units->firstWhere('is_default', true) ?? $units->first();
        $product->units()->update(['is_default' => false]);
        $default->update(['is_default' => true]);
    }
}
