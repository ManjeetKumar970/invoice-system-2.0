<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class Store extends Model
{
    use HasFactory, SoftDeletes;

    protected $guarded = ['id'];

    protected static function booted(): void
    {
        static::creating(function (Store $store) {
            if (empty($store->uuid)) {
                $store->uuid = (string) Str::uuid();
            }
        });
    }

    public function terminals(): HasMany
    {
        return $this->hasMany(Terminal::class);
    }

    public function stockLocations(): HasMany
    {
        return $this->hasMany(StockLocation::class);
    }

    public function sales(): HasMany
    {
        return $this->hasMany(Sale::class);
    }
}
