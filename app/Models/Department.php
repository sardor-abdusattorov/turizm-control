<?php

namespace App\Models;

use App\Models\Concerns\HasActiveOptions;
use App\Models\Concerns\HasActiveStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Spatie\Translatable\HasTranslations;

class Department extends Model
{
    use HasActiveOptions;
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

    /** @var array<int, string> */
    public const APPROVER_CODES = ['legal', 'accounting', 'direction'];

    /** @var array<int, string> */
    public const REQUIRED_APPROVER_CODES = ['legal', 'accounting'];

    /** @var array<int, string> */
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

    /** @return array<int, string> */
    public static function getActive(): array
    {
        return static::activeOptions('name');
    }

    /** @return array<int, string> */
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
