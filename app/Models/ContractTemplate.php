<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\HtmlString;
use Spatie\Translatable\HasTranslations;

class ContractTemplate extends Model
{
    use HasFactory;
    use HasTranslations;

    public $translatable = ['name', 'content'];

    protected $fillable = [
        'order_type_id',
        'name',
        'content',
        'fields',
        'sort',
        'status',
    ];

    protected $casts = [
        'fields' => 'array',
        'status' => 'boolean',
    ];

    public const STATUS_INACTIVE = 0;

    public const STATUS_ACTIVE = 1;

    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => __('app.status.active'),
            self::STATUS_INACTIVE => __('app.status.inactive'),
        ];
    }

    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Pick the first active template for a given order type, in sort order.
     * Used by the contract editor to figure out which template to render.
     */
    public static function defaultForOrderType(int $orderTypeId): ?self
    {
        return static::query()
            ->where('order_type_id', $orderTypeId)
            ->active()
            ->orderBy('sort')
            ->orderBy('id')
            ->first();
    }

    public function orderType(): BelongsTo
    {
        return $this->belongsTo(OrderType::class);
    }

    /**
     * Render the template's content for a given locale with the
     * provided values substituted in for the {{placeholder}} tokens.
     *
     * Plain scalars are escaped (manager-entered text shouldn't be
     * able to inject HTML); values wrapped in HtmlString are inserted
     * raw — that's the escape hatch for system-generated blocks like
     * {{logo}}, {{approvers_block}}, {{purchase_items}}.
     *
     * @param  array<string, scalar|HtmlString|null>  $values
     */
    public function render(array $values, ?string $locale = null): string
    {
        $locale ??= app()->getLocale();
        $html = (string) $this->getTranslation('content', $locale);

        foreach ($values as $key => $value) {
            $replacement = $value instanceof HtmlString
                ? (string) $value
                : e((string) ($value ?? ''));

            $html = str_replace('{{'.$key.'}}', $replacement, $html);
        }

        return $html;
    }
}
