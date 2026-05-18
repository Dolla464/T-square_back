<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Category extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'slug',
        'description',
        'icon',
        'image',
        'parent_id',
        'sort_order',
        'status',
    ];

    protected $casts = [
        'sort_order' => 'integer',
    ];

    // ──────────────────────────────────────────────────────────────────────────
    // Slug Automation Hooks
    // ──────────────────────────────────────────────────────────────────────────

    protected static function booted(): void
    {
        // On create: always generate a fresh unique slug from the name.
        static::creating(function (self $category): void {
            $category->slug = self::generateUniqueSlug($category->name);
        });

        // On update: only regenerate when the name has actually changed.
        static::updating(function (self $category): void {
            if ($category->isDirty('name')) {
                $category->slug = self::generateUniqueSlug($category->name, $category->id);
            }
        });
    }

    /**
     * Generate a URL-friendly, collision-free slug.
     *
     * When a duplicate base-slug exists, a numeric suffix is appended
     * (e.g. "web-development-1", "web-development-2") until a free slot
     * is found. The current record's own id is excluded so that an update
     * that does not change the name never falsely detects a collision.
     */
    protected static function generateUniqueSlug(string $name, ?int $ignoreId = null): string
    {
        $base  = Str::slug($name);
        $slug  = $base;
        $count = 1;

        while (
            static::query()
                ->where('slug', '=', $slug)
                ->when($ignoreId, fn ($q) => $q->where('id', '!=', $ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$count}";
            $count++;
        }

        return $slug;
    }

    // ──────────────────────────────────────────────────────────────────────────
    // Relationships
    // ──────────────────────────────────────────────────────────────────────────

    /** The parent category (null for top-level categories). */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(self::class, 'parent_id');
    }

    /** Direct child categories, ordered by sort_order. */
    public function children(): HasMany
    {
        return $this->hasMany(self::class, 'parent_id')->orderBy('sort_order');
    }

    /** Courses that belong to this category. */
    public function courses(): HasMany
    {
        return $this->hasMany(Course::class);
    }
}
