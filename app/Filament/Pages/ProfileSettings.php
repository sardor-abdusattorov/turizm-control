<?php

namespace App\Filament\Pages;

use App\Filament\Support\ImageUpload;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use BackedEnum;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\Concerns\InteractsWithActions;
use Filament\Actions\Contracts\HasActions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Concerns\InteractsWithForms;
use Filament\Forms\Contracts\HasForms;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Session;
use Illuminate\Validation\Rules\Password;

class ProfileSettings extends Page implements HasActions, HasForms
{
    use InteractsWithActions;
    use InteractsWithForms;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserCircle;

    protected string $view = 'filament.pages.profile-settings';

    protected static bool $shouldRegisterNavigation = false;

    protected static ?string $slug = 'profile';

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
                        Grid::make(['default' => 1, 'md' => 3])
                            ->schema([
                                Group::make([
                                    Grid::make(['default' => 1, 'sm' => 2])
                                        ->schema([
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
                                        ]),

                                    TextInput::make('telegram_chat_id')
                                        ->label(__('app.label.telegram_chat_id'))
                                        ->helperText(__('app.label.telegram_chat_id_help'))
                                        ->maxLength(255),

                                    Grid::make(['default' => 1, 'sm' => 2])
                                        ->schema([
                                            Select::make('department_id')
                                                ->label(__('app.label.department'))
                                                ->options(Department::getActive())
                                                ->searchable()
                                                ->preload(),

                                            Select::make('position_id')
                                                ->label(__('app.label.position'))
                                                ->options(Position::getActive())
                                                ->searchable()
                                                ->preload(),
                                        ]),

                                    Select::make('default_recipients')
                                        ->label(__('app.label.default_recipients'))
                                        ->helperText(__('app.label.default_recipients_help'))
                                        ->multiple()
                                        ->options($this->getGroupedRecipientOptions())
                                        ->allowHtml()
                                        ->searchable()
                                        ->preload(),
                                ])->columnSpan(['md' => 2]),

                                ImageUpload::make('users', 'avatar_url')
                                    ->label(__('app.label.profile_image'))
                                    ->avatar()
                                    ->imageEditorAspectRatios(['1:1'])
                                    ->columnSpan(['md' => 1]),
                            ]),
                    ])
                    ->footerActions([
                        Action::make('save_profile')
                            ->label(__('app.action.update'))
                            ->color('primary')
                            ->submit('updateProfile'),
                    ])
                    ->footerActionsAlignment(Alignment::End),
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
                    ])
                    ->footerActions([
                        Action::make('save_password')
                            ->label(__('app.action.update'))
                            ->color('primary')
                            ->submit('updatePassword'),
                    ])
                    ->footerActionsAlignment(Alignment::End),
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
            ->icon('heroicon-o-arrow-right-on-rectangle')
            ->requiresConfirmation()
            ->modalHeading(__('app.action.logout_other_sessions'))
            ->modalDescription(__('app.message.confirm_logout_other_sessions'))
            ->modalSubmitActionLabel(__('app.action.confirm'))
            ->schema([
                TextInput::make('password')
                    ->label(__('app.label.password'))
                    ->password()
                    ->required()
                    ->currentPassword()
                    ->revealable(),
            ])
            ->action(function (array $data) {
                if (config('session.driver') !== 'database') {
                    Notification::make()
                        ->title(__('app.message.session_driver_not_supported'))
                        ->danger()
                        ->send();

                    return;
                }

                Auth::guard('web')->logoutOtherDevices($data['password']);

                DB::table(config('session.table', 'sessions'))
                    ->where('user_id', Auth::id())
                    ->where('id', '!=', Session::getId())
                    ->delete();

                // logoutOtherDevices() re-hashes the user's password but does NOT
                // sync the new hash into the current session. AuthenticateSession
                // middleware would then see a mismatch and kick us out on the next
                // request, same as the other sessions. Mirror Jetstream and store
                // the new hash so the current device stays logged in.
                request()->session()->put([
                    'password_hash_'.Auth::getDefaultDriver() => Auth::user()->getAuthPassword(),
                ]);

                Notification::make()
                    ->title(__('app.message.other_sessions_logged_out'))
                    ->success()
                    ->send();
            });
    }

    /**
     * @return array<int, array{id: string, ip_address: ?string, browser: string, platform: string, is_current_device: bool, last_active: string}>
     */
    public function getSessions(): array
    {
        if (config('session.driver') !== 'database') {
            return [];
        }

        $activityThreshold = now()
            ->subMinutes((int) config('session.lifetime', 120))
            ->getTimestamp();

        return DB::table(config('session.table', 'sessions'))
            ->where('user_id', Auth::id())
            ->where('last_activity', '>=', $activityThreshold)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session) {
                $agent = $this->parseUserAgent($session->user_agent);

                return [
                    'id' => $session->id,
                    'ip_address' => $session->ip_address,
                    'browser' => $agent['browser'],
                    'platform' => $agent['platform'],
                    'is_current_device' => $session->id === Session::getId(),
                    'last_active' => Carbon::createFromTimestamp($session->last_activity)->diffForHumans(),
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
            if (preg_match('/Chrome/i', $userAgent) && ! preg_match('/Edge/i', $userAgent)) {
                $browser = 'Chrome';
            } elseif (preg_match('/Firefox/i', $userAgent)) {
                $browser = 'Firefox';
            } elseif (preg_match('/Safari/i', $userAgent) && ! preg_match('/Chrome/i', $userAgent)) {
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

    /**
     * Active users from approver departments only, grouped by department,
     * with avatar markup so the Select can show photo + name.
     *
     * @return array<string, array<int, string>>
     */
    protected function getGroupedRecipientOptions(): array
    {
        return User::approverOptionsGroupedByDepartment(Auth::id());
    }
}
