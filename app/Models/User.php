<?php

namespace App\Models;

use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;

class User extends Authenticatable implements FilamentUser, HasAvatar
{
    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use HasPanelShield;
    use HasRoles;
    use Notifiable;

    public const STATUS_ACTIVE = 1;

    public const STATUS_DISABLED = 0;

    protected $fillable = [
        'name',
        'avatar_url',
        'email',
        'password',
        'department_id',
        'position_id',
        'status',
        'locale',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'status' => 'boolean',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        // A user whose role grants no permissions at all has nothing to do in
        // the panel — keep them out instead of dropping them on an empty
        // dashboard. super_admin passes via its wildcard role even before any
        // permissions are synced onto it.
        return $this->hasRole('super_admin') || $this->getAllPermissions()->isNotEmpty();
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url
            ? Storage::disk('local')->temporaryUrl($this->avatar_url, now()->addMinutes(30))
            : null;
    }

    /**
     * An avatar URL that always resolves — the uploaded photo when present,
     * otherwise a generated initials avatar. Used wherever a name is shown
     * alongside a face (approval chain, contract list).
     */
    public function avatarUrl(): string
    {
        return $this->getFilamentAvatarUrl()
            ?? 'https://ui-avatars.com/api/?name='.urlencode($this->name ?? '?').'&background=E0E7FF&color=4338CA&size=80';
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    public function defaultRecipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'recipients', 'user_id', 'recipient_id')
            ->withTimestamps();
    }

    public function telegram(): HasOne
    {
        return $this->hasOne(TelegramUser::class);
    }

    public function isTelegramLinked(): bool
    {
        return $this->telegram()->exists();
    }

    public function getDefaultRecipientIds(): array
    {
        return $this->defaultRecipients()->pluck('users.id')->toArray();
    }

    /**
     * @return array<string, array<int, string>>
     */
    public static function approverOptionsGroupedByDepartment(?int $excludeId = null): array
    {
        return static::optionsGroupedByDepartment(
            $excludeId,
            fn (Builder $query) => $query->whereHas('department', fn ($department) => $department->approvers()),
        );
    }

    /**
     * Every active user, grouped by department — the picker for roles the
     * approval flow knows nothing about, such as the supply officer who checks
     * requisitions.
     *
     * @return array<string, array<int, string>>
     */
    public static function activeOptionsGroupedByDepartment(?int $excludeId = null): array
    {
        return static::optionsGroupedByDepartment($excludeId);
    }

    /**
     * @param  (callable(Builder): Builder)|null  $filter
     * @return array<string, array<int, string>>
     */
    private static function optionsGroupedByDepartment(?int $excludeId = null, ?callable $filter = null): array
    {
        return static::query()
            ->where('status', self::STATUS_ACTIVE)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->when($filter, fn ($query) => $filter($query))
            ->with(['department', 'position'])
            ->get()
            ->reduce(function (array $grouped, self $user): array {
                $department = $user->department?->name ?? __('app.label.no_department');
                $avatar = $user->getFilamentAvatarUrl()
                    ?? 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&color=60A5FA&background=DBEAFE';
                $position = $user->position?->name ? ' · '.e($user->position->name) : '';
                $grouped[$department][$user->id] = sprintf(
                    '<span style="display:inline-flex;align-items:center;gap:.4rem;font-size:14px;">'
                    .'<img src="%s" alt="" style="width:30px;height:30px;border-radius:9999px;object-fit:cover;flex-shrink:0;">'
                    .'<span>%s%s</span></span>',
                    e($avatar),
                    e($user->name),
                    $position,
                );

                return $grouped;
            }, []);
    }
}
