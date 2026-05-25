<?php

namespace App\Models;

use BezhanSalleh\FilamentShield\Traits\HasPanelShield;
use Database\Factories\UserFactory;
use Filament\Models\Contracts\FilamentUser;
use Filament\Models\Contracts\HasAvatar;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Traits\HasRoles;
use Filament\Panel;

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
        'email',
        'password',
        'department_id',
        'position_id',
        'status',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $appends = ['profile_image'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'ui_settings' => 'array',
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

    public static function approverOptionsGrouped(): array
    {
        $users = static::query()
            ->where('status', self::STATUS_ACTIVE)
            ->where('id', '!=', auth()->id())
            ->whereDoesntHave('roles', function ($q) {
                $q->where('name', 'manager');
            })
            ->with(['department', 'position'])
            ->get();

        $grouped = [];

        foreach ($users as $user) {
            $departmentName = $user->department?->name ?? __('app.label.no_department');

            if (! isset($grouped[$departmentName])) {
                $grouped[$departmentName] = [];
            }

            $positionName = $user->position?->name ?? '';
            $label = $positionName
                ? "{$user->name} — {$positionName}"
                : $user->name;

            $grouped[$departmentName][$user->id] = $label;
        }

        return $grouped;
    }
}
