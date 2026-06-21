<?php

namespace App\Models;

use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Department extends Model
{
    use HasActiveStatus;
    use HasFactory;
    use HasTranslations;

    public $translatable = ['name'];

    protected $fillable = [
        'code',
        'name',
        'head_of_department',
        'sort',
        'status',
    ];

    protected $casts = [
        'status' => 'boolean',
    ];

    /**
     * Department codes that participate in the approval workflow.
     *
     * @var array<int, string>
     */
    public const APPROVER_CODES = ['legal', 'accounting', 'direction'];

    /**
     * Departments that MUST be represented in every contract approval chain —
     * a chain is invalid unless it contains at least one approver from each.
     *
     * @var array<int, string>
     */
    public const REQUIRED_APPROVER_CODES = ['legal', 'accounting'];

    /**
     * Department codes that form the auto-built sequential approval chain.
     * The director is intentionally excluded — they join later as a separate,
     * manually-triggered final sign-off (see Contract::appendDirectorApprover()).
     *
     * @var array<int, string>
     */
    public const FLOW_CODES = ['legal', 'accounting'];

    public function head()
    {
        return $this->belongsTo(User::class, 'head_of_department');
    }

    public function users()
    {
        return $this->hasMany(User::class);
    }

    public function approverUser(): ?User
    {
        if ($this->head && (bool) $this->head->status) {
            return $this->head;
        }

        return $this->users()
            ->where('status', User::STATUS_ACTIVE)
            ->orderBy('id')
            ->first();
    }

    public function isApproverDepartment(): bool
    {
        return in_array($this->code, self::APPROVER_CODES, true);
    }

    public function scopeApprovers($query)
    {
        return $query->whereIn('code', self::APPROVER_CODES);
    }

    public function positions()
    {
        return $this->belongsToMany(Position::class, 'department_position');
    }

    public static function findByCode(string $code): ?self
    {
        return static::where('code', $code)->first();
    }

    /**
     * Active departments as id => localized name pairs (for Select::options).
     *
     * @return array<int, string>
     */
    public static function getActive(): array
    {
        return static::query()
            ->active()
            ->orderBy('sort')
            ->get()
            ->mapWithKeys(fn (self $item) => [
                $item->id => $item->getTranslation('name', app()->getLocale()),
            ])
            ->toArray();
    }

    /**
     * Canonical approval flow order: takes the admin-configured order
     * from settings.approval.flow and falls back to FLOW_CODES when
     * none is set or the saved one is empty. The director is never part of
     * this chain — they are handed the contract manually as a final stage.
     *
     * @return array<int, string>
     */
    public static function approvalFlow(): array
    {
        $flow = settings('approval.flow');

        if (! is_array($flow) || empty($flow)) {
            return self::FLOW_CODES;
        }

        return array_values(array_intersect(
            array_filter($flow, 'is_string'),
            self::FLOW_CODES
        ));
    }
}
