<?php

namespace App\Models;

use Database\Factories\TenantFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use App\Support\TenantSettings;
use App\Support\SafeUrl;

class Tenant extends Model
{
    /** @use HasFactory<TenantFactory> */
    use HasFactory;

    protected static function booted(): void
    {
        static::created(function (Tenant $tenant): void {
            TenantSettings::seedDefaults($tenant->id);
        });
    }

    protected $fillable = [
        'slug', 'name', 'tagline', 'address', 'phone', 'email',
        'logo_path', 'invoice_footer_text', 'theme_mode', 'theme_palette', 'is_suspended',
    ];

    protected function casts(): array
    {
        return [
            'is_suspended' => 'boolean',
        ];
    }

    protected $appends = ['logo_url'];

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function getLogoUrlAttribute(): ?string
    {
        if (! $this->logo_path) {
            return null;
        }

        return SafeUrl::sanitize(Storage::disk('public')->url($this->logo_path));
    }

    public function toBrandingArray(): array
    {
        return [
            'id' => $this->id,
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'address' => $this->address,
            'phone' => $this->phone,
            'email' => $this->email,
            'logo_url' => $this->logo_url,
            'invoice_footer_text' => $this->invoice_footer_text,
            'theme_mode' => $this->theme_mode ?? 'system',
            'theme_palette' => $this->theme_palette ?? 'neutral',
        ];
    }

    public function toLoginBrandingArray(): array
    {
        return [
            'slug' => $this->slug,
            'name' => $this->name,
            'tagline' => $this->tagline,
            'logo_url' => $this->logo_url,
        ];
    }
}
