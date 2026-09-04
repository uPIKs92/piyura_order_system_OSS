<?php

namespace App\Models;

use App\Models\Concerns\BelongsToTenant;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model
{
    /** @use HasFactory<\Database\Factories\ProductFactory> */
    use BelongsToTenant, HasFactory, LogsActivity, SoftDeletes;

    protected $fillable = [
        'tenant_id', 'category_id', 'nama', 'slug', 'sku', 'barcode',
        'deskripsi', 'is_active', 'photo_path',
    ];

    protected $appends = ['photo_url'];

    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
        ];
    }

    public function getPhotoUrlAttribute(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return \App\Support\SafeUrl::sanitize(\Illuminate\Support\Facades\Storage::disk('public')->url($this->photo_path));
    }


    public function category()
    {
        return $this->belongsTo(Category::class);
    }

    public function units()
    {
        return $this->hasMany(ProductUnit::class);
    }

    public function defaultUnit(): ?ProductUnit
    {
        if ($this->relationLoaded('units')) {
            return $this->units->firstWhere('is_default', true)
                ?? $this->units->first();
        }

        return $this->units()->where('is_default', true)->first()
            ?? $this->units()->first();
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logFillable()->logOnlyDirty();
    }
}
