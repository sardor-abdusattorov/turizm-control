<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\TelegramMessageLog;
use Illuminate\Auth\Access\HandlesAuthorization;
use Illuminate\Foundation\Auth\User as AuthUser;

class TelegramMessageLogPolicy
{
    use HandlesAuthorization;

    public function viewAny(AuthUser $authUser): bool
    {
        return $authUser->can('view_any_telegram_message_log');
    }

    public function view(AuthUser $authUser, TelegramMessageLog $telegramMessageLog): bool
    {
        return $authUser->can('view_telegram_message_log');
    }

    public function create(AuthUser $authUser): bool
    {
        return $authUser->can('create_telegram_message_log');
    }

    public function update(AuthUser $authUser, TelegramMessageLog $telegramMessageLog): bool
    {
        return $authUser->can('update_telegram_message_log');
    }

    public function delete(AuthUser $authUser, TelegramMessageLog $telegramMessageLog): bool
    {
        return $authUser->can('delete_telegram_message_log');
    }

    public function restore(AuthUser $authUser, TelegramMessageLog $telegramMessageLog): bool
    {
        return $authUser->can('restore_telegram_message_log');
    }

    public function forceDelete(AuthUser $authUser, TelegramMessageLog $telegramMessageLog): bool
    {
        return $authUser->can('force_delete_telegram_message_log');
    }

    public function forceDeleteAny(AuthUser $authUser): bool
    {
        return $authUser->can('force_delete_any_telegram_message_log');
    }

    public function restoreAny(AuthUser $authUser): bool
    {
        return $authUser->can('restore_any_telegram_message_log');
    }

    public function replicate(AuthUser $authUser, TelegramMessageLog $telegramMessageLog): bool
    {
        return $authUser->can('replicate_telegram_message_log');
    }

    public function reorder(AuthUser $authUser): bool
    {
        return $authUser->can('reorder_telegram_message_log');
    }
}
