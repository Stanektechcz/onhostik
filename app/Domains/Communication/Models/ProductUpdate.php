<?php

declare(strict_types=1);

namespace App\Domains\Communication\Models;

use App\Models\User;
use Database\Factories\ProductUpdateFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One entry in the in-app changelog (audit I130).
 *
 * @property int                             $id
 * @property string                          $title
 * @property string                          $body
 * @property string                          $category
 * @property string|null                     $version
 * @property bool                            $is_published
 * @property \Illuminate\Support\Carbon|null $published_at
 */
class ProductUpdate extends Model
{
    /** @use HasFactory<ProductUpdateFactory> */
    use HasFactory;

    public const CATEGORIES = [
        'feature'     => 'Novinka',
        'improvement' => 'Vylepšení',
        'fix'         => 'Oprava',
        'security'    => 'Zabezpečení',
    ];

    /** Cuba badge variant per category. */
    public const CATEGORY_COLORS = [
        'feature'     => 'primary',
        'improvement' => 'info',
        'fix'         => 'warning',
        'security'    => 'danger',
    ];

    protected $fillable = [
        'title',
        'body',
        'category',
        'version',
        'is_published',
        'published_at',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * What a customer may see.
     *
     * Both conditions are required: a future `published_at` is a scheduled
     * entry, and publishing it early would leak an unannounced change.
     *
     * @param  Builder<ProductUpdate>  $query
     * @return Builder<ProductUpdate>
     */
    public function scopeVisible(Builder $query): Builder
    {
        return $query->where('is_published', true)
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now());
    }

    /** @return BelongsTo<User, $this> */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function categoryLabel(): string
    {
        return self::CATEGORIES[$this->category] ?? $this->category;
    }

    public function categoryColor(): string
    {
        return self::CATEGORY_COLORS[$this->category] ?? 'secondary';
    }

    /**
     * How many entries this user has not seen yet.
     *
     * A user who has never opened the page has seen nothing — but counting
     * every entry ever published would greet a brand-new customer with a
     * badge of 40, so first sight is treated as caught up.
     */
    public static function unseenCountFor(?User $user): int
    {
        if ($user === null) {
            return 0;
        }

        if ($user->changelog_seen_at === null) {
            return 0;
        }

        return self::query()
            ->visible()
            ->where('published_at', '>', $user->changelog_seen_at)
            ->count();
    }
}
