<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Department extends Model
{
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

    const STATUS_INACTIVE = 0;

    const STATUS_ACTIVE = 1;

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
     * Get available statuses
     */
    public static function getStatuses(): array
    {
        return [
            self::STATUS_ACTIVE => __('app.status.active'),
            self::STATUS_INACTIVE => __('app.status.inactive'),
        ];
    }

    /**
     * Relationship with User (head of department)
     */
    public function head()
    {
        return $this->belongsTo(User::class, 'head_of_department');
    }

    /**
     * Members of this department.
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }

    /**
     * The user who approves on behalf of this department in the global
     * flow — the department head when active, otherwise the first active
     * member. Returns null when the department has no usable approver.
     */
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

    /**
     * Scope for active departments
     */
    public function scopeActive($query)
    {
        return $query->where('status', self::STATUS_ACTIVE);
    }

    /**
     * Check if this is an approver department (Legal, Financial, Accounting, Direction)
     */
    public function isApproverDepartment(): bool
    {
        return in_array($this->code, self::APPROVER_CODES, true);
    }

    /**
     * Scope for approver departments
     */
    public function scopeApprovers($query)
    {
        return $query->whereIn('code', self::APPROVER_CODES);
    }

    /**
     * Positions available in this department
     */
    public function positions()
    {
        return $this->belongsToMany(Position::class, 'department_position');
    }

    /**
     * Get department by code
     */
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
     * from settings.approval.flow and falls back to APPROVER_CODES when
     * none is set or the saved one is empty.
     *
     * @return array<int, string>
     */
    public static function approvalFlow(): array
    {
        $flow = settings('approval.flow');

        if (! is_array($flow) || empty($flow)) {
            return self::APPROVER_CODES;
        }

        return array_values(array_intersect(
            array_filter($flow, 'is_string'),
            self::APPROVER_CODES
        ));
    }
}
