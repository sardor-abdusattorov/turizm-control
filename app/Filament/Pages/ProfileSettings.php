<?php

namespace App\Filament\Pages;

use App\Filament\Support\ImageUpload;
use App\Models\User;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Section;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Schemas\Schema;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;
use BackedEnum;

class ProfileSettings extends Page implements HasForms, HasActions
{
    use InteractsWithForms;
    use InteractsWithActions;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;
    protected string $view = 'filament.pages.profile-settings';
    protected static bool $shouldRegisterNavigation = false;

    public ?array $profileData = [];
    public ?array $passwordData = [];

    public static function getNavigationLabel(): string
    {
        return __('app.label.profile_settings');
    }

    public function getTitle(): string
    {
        return __('app.label.my_profile');
    }

    public function getSubheading(): ?string
    {
        return __('app.label.manage_profile');
    }

    public function mount(): void
    {
        $user = Auth::user();

        $this->profileForm->fill([
            'name' => $user->name,
            'email' => $user->email,
            'avatar_url' => $user->avatar_url,
            'telegram_chat_id' => $user->telegram_chat_id,
            'department_id' => $user->department_id,
            'position_id' => $user->position_id,
            'default_recipients' => $user->getDefaultRecipientIds(),
        ]);

        $this->passwordForm->fill();
    }

    public function profileForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.label.personal_information'))
                    ->description(__('app.label.personal_information_description'))
                    ->aside()
                    ->schema([
                        ImageUpload::make('users', 'avatar_url')
                            ->label(__('app.label.profile_image')),

                        TextInput::make('name')
                            ->label(__('app.label.name'))
                            ->required()
                            ->maxLength(255),

                        TextInput::make('email')
                            ->label(__('app.label.email'))
                            ->email()
                            ->required()
                            ->unique('users', 'email', ignorable: Auth::user())
                            ->maxLength(255),

                        TextInput::make('telegram_chat_id')
                            ->label(__('app.label.telegram_chat_id'))
                            ->helperText(__('app.label.telegram_chat_id_help'))
                            ->maxLength(255),

                        Grid::make(2)
                            ->schema([
                                Select::make('department_id')
                                    ->label(__('app.label.department'))
                                    ->relationship('department', 'name', fn ($query) => $query->where('status', 1))
                                    ->searchable()
                                    ->preload(),

                                Select::make('position_id')
                                    ->label(__('app.label.position'))
                                    ->relationship('position', 'name', fn ($query) => $query->where('status', 1))
                                    ->searchable()
                                    ->preload(),
                            ]),

                        Select::make('default_recipients')
                            ->label(__('app.label.default_recipients'))
                            ->helperText(__('app.label.default_recipients_help'))
                            ->multiple()
                            ->options($this->getGroupedRecipientOptions())
                            ->searchable()
                            ->preload(),
                    ]),
            ])
            ->statePath('profileData');
    }

    public function passwordForm(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make(__('app.label.password'))
                    ->description(__('app.label.password_description'))
                    ->aside()
                    ->schema([
                        TextInput::make('current_password')
                            ->label(__('app.label.current_password'))
                            ->password()
                            ->required()
                            ->currentPassword()
                            ->revealable(),

                        TextInput::make('password')
                            ->label(__('app.label.new_password'))
                            ->password()
                            ->required()
                            ->rule(Password::min(8))
                            ->revealable()
                            ->autocomplete('new-password'),

                        TextInput::make('password_confirmation')
                            ->label(__('app.label.password_confirmation'))
                            ->password()
                            ->required()
                            ->same('password')
                            ->revealable()
                            ->autocomplete('new-password'),
                    ]),
            ])
            ->statePath('passwordData');
    }

    protected function getForms(): array
    {
        return [
            'profileForm',
            'passwordForm',
        ];
    }

    public function updateProfile(): void
    {
        try {
            $data = $this->profileForm->getState();

            $user = Auth::user();
            $user->update([
                'name' => $data['name'],
                'email' => $data['email'],
                'avatar_url' => $data['avatar_url'] ?? null,
                'telegram_chat_id' => $data['telegram_chat_id'] ?? null,
                'department_id' => $data['department_id'],
                'position_id' => $data['position_id'],
            ]);

            $user->defaultRecipients()->sync($data['default_recipients'] ?? []);

            Notification::make()
                ->title(__('app.message.profile_updated'))
                ->success()
                ->send();

        } catch (Halt $exception) {
            return;
        }
    }

    public function updatePassword(): void
    {
        try {
            $data = $this->passwordForm->getState();

            Auth::user()->update([
                'password' => Hash::make($data['password']),
            ]);

            $this->passwordForm->fill();

            Notification::make()
                ->title(__('app.message.password_updated'))
                ->success()
                ->send();

        } catch (Halt $exception) {
            return;
        }
    }

    public function logoutOtherSessionsAction(): Action
    {
        return Action::make('logoutOtherSessions')
            ->label(__('app.action.logout_other_sessions'))
            ->color('danger')
            ->requiresConfirmation()
            ->modalHeading(__('app.action.logout_other_sessions'))
            ->modalDescription(__('app.message.confirm_logout_other_sessions'))
            ->modalSubmitActionLabel(__('app.action.logout_other_sessions'))
            ->action(function () {
                if (config('session.driver') !== 'database') {
                    Notification::make()
                        ->title(__('app.message.session_driver_not_supported'))
                        ->danger()
                        ->send();
                    return;
                }

                DB::table('sessions')
                    ->where('user_id', Auth::id())
                    ->where('id', '!=', Session::getId())
                    ->delete();

                Notification::make()
                    ->title(__('app.message.other_sessions_logged_out'))
                    ->success()
                    ->send();
            });
    }

    public function getSessions(): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        return DB::table('sessions')
            ->where('user_id', Auth::id())
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) {
                $agent = $this->parseUserAgent($session->user_agent);
                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'user_agent' => $session->user_agent,
                    'browser' => $agent['browser'],
                    'platform' => $agent['platform'],
                    'is_current_device' => $session->id === Session::getId(),
                    'last_active' => \Carbon\Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
                ];
            })
            ->toArray();
    }

    protected function parseUserAgent(?string $userAgent): array
    {
        $browser = 'Unknown';
        $platform = 'Unknown';

        if ($userAgent) {
            // Platform detection
            if (preg_match('/Windows/i', $userAgent)) {
                $platform = 'Windows';
            } elseif (preg_match('/Macintosh|Mac OS/i', $userAgent)) {
                $platform = 'Mac';
            } elseif (preg_match('/Linux/i', $userAgent)) {
                $platform = 'Linux';
            } elseif (preg_match('/iPhone/i', $userAgent)) {
                $platform = 'iPhone';
            } elseif (preg_match('/Android/i', $userAgent)) {
                $platform = 'Android';
            }

            // Browser detection
            if (preg_match('/Chrome/i', $userAgent) && !preg_match('/Edge/i', $userAgent)) {
                $browser = 'Chrome';
            } elseif (preg_match('/Firefox/i', $userAgent)) {
                $browser = 'Firefox';
            } elseif (preg_match('/Safari/i', $userAgent) && !preg_match('/Chrome/i', $userAgent)) {
                $browser = 'Safari';
            } elseif (preg_match('/Edge/i', $userAgent)) {
                $browser = 'Edge';
            } elseif (preg_match('/Opera|OPR/i', $userAgent)) {
                $browser = 'Opera';
            }
        }

        return [
            'browser' => $browser,
            'platform' => $platform,
        ];
    }

    protected function getGroupedRecipientOptions(): array
    {
        $users = User::query()
            ->where('status', 1)
            ->where('id', '!=', Auth::id())
            ->with('department')
            ->get();

        $grouped = [];

        foreach ($users as $user) {
            $departmentName = $user->department?->name ?? __('app.label.no_department');
            if (!isset($grouped[$departmentName])) {
                $grouped[$departmentName] = [];
            }
            $grouped[$departmentName][$user->id] = $user->name;
        }

        return $grouped;
    }
}
