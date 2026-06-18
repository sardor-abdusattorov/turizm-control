<?php

namespace App\Models;

use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
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

    //    use HasUiSettings;
    use Notifiable;

    public const STATUS_ACTIVE = 1;

    public const STATUS_DISABLED = 0;

    protected $fillable = [
        'name',
        'avatar_url',
        'email',
        'password',
        'telegram_chat_id',
        'department_id',
        'position_id',
        'status',
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
        return true;
    }

    public function getFilamentAvatarUrl(): ?string
    {
        return $this->avatar_url ? Storage::url($this->avatar_url) : null;
    }

    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    public function position(): BelongsTo
    {
        return $this->belongsTo(Position::class);
    }

    /**
     * Default recipients for approval workflow
     */
    public function defaultRecipients(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'recipients', 'user_id', 'recipient_id')
            ->withTimestamps();
    }

    /**
     * Get default recipient IDs as array
     */
    public function getDefaultRecipientIds(): array
    {
        return $this->defaultRecipients()->pluck('users.id')->toArray();
    }

    /**
     * Active users from approver departments, grouped by department name, with
     * avatar markup so an `allowHtml()` Select can show photo + name. Shared by
     * the profile's default-recipients picker and the contract approval chain.
     *
     * @return array<string, array<int, string>>
     */
    public static function approverOptionsGroupedByDepartment(?int $excludeId = null): array
    {
        return static::query()
            ->where('status', self::STATUS_ACTIVE)
            ->when($excludeId, fn ($query) => $query->where('id', '!=', $excludeId))
            ->whereHas('department', fn ($query) => $query->approvers())
            ->with(['department', 'position'])
            ->get()
            ->reduce(function (array $grouped, self $user): array {
                $department = $user->department?->name ?? __('app.label.no_department');

                $avatar = $user->getFilamentAvatarUrl()
                    ?? 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&color=7F9CF5&background=EBF4FF';

                $position = $user->position?->name ? ' · '.e($user->position->name) : '';

                $grouped[$department][$user->id] = sprintf(
                    '<div class="flex items-center gap-2"><img src="%s" class="w-6 h-6 rounded-full object-cover" alt=""><span>%s%s</span></div>',
                    e($avatar),
                    e($user->name),
                    $position,
                );

                return $grouped;
            }, []);
    }
}
