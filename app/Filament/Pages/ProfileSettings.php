<?php

namespace App\Filament\Pages;

use App\Filament\Support\ImageUpload;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Services\Auth\UserSessions;
use BackedEnum;
use BezhanSalleh\FilamentShield\Traits\HasPageShield;
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
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\View;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Filament\Support\Exceptions\Halt;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class ProfileSettings extends Page implements HasActions, HasForms
{
    use HasPageShield;
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
                            ->hiddenLabel()
                            ->avatar()
                            ->imageEditorAspectRatios(['1:1'])
                            ->placeholder(__('app.label.upload_photo'))
                            ->extraAttributes(['class' => 'profile-avatar-uploader']),

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

                        View::make('filament.pages.partials.telegram-connect')
                            ->viewData([
                                'connected' => (bool) Auth::user()?->isTelegramLinked(),
                                'username' => Auth::user()?->telegram?->username,
                            ]),

                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                Select::make('department_id')
                                    ->label(__('app.label.department'))
                                    ->options(Department::getActive())
                                    ->disabled(fn (): bool => ! Auth::user()?->can('update_user'))
                                    ->dehydrated(fn (): bool => (bool) Auth::user()?->can('update_user'))
                                    ->helperText(fn (): ?string => Auth::user()?->can('update_user')
                                        ? null
                                        : __('app.label.managed_by_admin'))
                                    ->searchable()
                                    ->preload(),

                                Select::make('position_id')
                                    ->label(__('app.label.position'))
                                    ->options(Position::getActive())
                                    ->disabled(fn (): bool => ! Auth::user()?->can('update_user'))
                                    ->dehydrated(fn (): bool => (bool) Auth::user()?->can('update_user'))
                                    ->helperText(fn (): ?string => Auth::user()?->can('update_user')
                                        ? null
                                        : __('app.label.managed_by_admin'))
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

    public function updateProfile(): void
    {
        try {
            $data = $this->profileForm->getState();

            $user = Auth::user();
            $payload = [
                'name' => $data['name'],
                'email' => $data['email'],
                'avatar_url' => $data['avatar_url'] ?? null,
            ];

            if (array_key_exists('department_id', $data)) {
                $payload['department_id'] = $data['department_id'];
            }

            if (array_key_exists('position_id', $data)) {
                $payload['position_id'] = $data['position_id'];
            }

            $user->update($payload);

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

    public function connectTelegramAction(): Action
    {
        return Action::make('connectTelegram')
            ->label(__('app.action.connect_telegram'))
            ->color('primary')
            ->icon('heroicon-o-paper-airplane')
            ->url(fn (): string => route('telegram.connect'))
            ->openUrlInNewTab();
    }

    public function disconnectTelegramAction(): Action
    {
        return Action::make('disconnectTelegram')
            ->label(__('app.action.disconnect_telegram'))
            ->color('danger')
            ->icon('heroicon-o-x-circle')
            ->requiresConfirmation()
            ->modalHeading(__('app.action.disconnect_telegram'))
            ->modalDescription(__('app.message.confirm_disconnect_telegram'))
            ->action(function (): void {
                Auth::user()?->telegram()?->delete();

                Notification::make()
                    ->title(__('app.message.telegram_disconnected'))
                    ->success()
                    ->send();
            });
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
                if (! app(UserSessions::class)->logoutOthers($data['password'])) {
                    Notification::make()
                        ->title(__('app.message.session_driver_not_supported'))
                        ->danger()
                        ->send();

                    return;
                }

                Notification::make()
                    ->title(__('app.message.other_sessions_logged_out'))
                    ->success()
                    ->send();
            });
    }

    /** @return array<int, array{id: string, ip_address: ?string, browser: string, platform: string, is_current_device: bool, last_active: string}> */
    public function getSessions(): array
    {
        return app(UserSessions::class)->forCurrentUser();
    }

    /** @return array<string, array<int, string>> */
    protected function getGroupedRecipientOptions(): array
    {
        return User::approverOptionsGroupedByDepartment(Auth::id());
    }
}
