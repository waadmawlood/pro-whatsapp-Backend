<?php

namespace App\Models;

use App\Models\Concerns\BelongsToCompany;
use Database\Factories\TagFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Fillable([
    'company_id',
    'name',
    'slug',
    'color',
])]
class Tag extends Model
{
    /** @use HasFactory<TagFactory> */
    use BelongsToCompany, HasFactory;

    protected static function booted(): void
    {
        static::creating(function (self $tag): void {
            if (! $tag->slug) {
                $tag->slug = Str::slug($tag->name) ?: Str::random(8);
            }
        });
    }

    public function customers(): BelongsToMany
    {
        return $this->belongsToMany(Customer::class, 'customer_tag');
    }
}
