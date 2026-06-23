<?php

namespace App\Services\Auth;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Session;

/**
 * Reads and maintains the database-backed sessions for the current user — the
 * data behind the profile page's "active devices" list and its "log out other
 * sessions" action. Keeps raw sessions-table access and user-agent sniffing
 * out of the Filament page.
 */
class UserSessions
{
    public function isSupported(): bool
    {
        return config('session.driver') === 'database';
    }

    /**
     * Active sessions for the current user, newest first.
     *
     * @return array<int, array{id: string, ip_address: ?string, browser: string, platform: string, is_current_device: bool, last_active: string}>
     */
    public function forCurrentUser(): array
    {
        if (! $this->isSupported()) {
            return [];
        }

        $activityThreshold = now()
            ->subMinutes((int) config('session.lifetime', 120))
            ->getTimestamp();

        return DB::table($this->table())
            ->where('user_id', Auth::id())
            ->where('last_activity', '>=', $activityThreshold)
            ->orderByDesc('last_activity')
            ->get()
            ->map(function ($session): array {
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

    /**
     * Log the current user out of every other device. Returns false when the
     * database session driver isn't active (so the caller can report that the
     * feature is unsupported), true once the other sessions are cleared.
     */
    public function logoutOthers(string $password): bool
    {
        if (! $this->isSupported()) {
            return false;
        }

        Auth::guard('web')->logoutOtherDevices($password);

        DB::table($this->table())
            ->where('user_id', Auth::id())
            ->where('id', '!=', Session::getId())
            ->delete();

        request()->session()->put([
            'password_hash_'.Auth::getDefaultDriver() => Auth::user()->getAuthPassword(),
        ]);

        return true;
    }

    /**
     * @return array{browser: string, platform: string}
     */
    public function parseUserAgent(?string $userAgent): array
    {
        $browser = __('app.label.unknown');
        $platform = __('app.label.unknown');

        if ($userAgent) {
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

    private function table(): string
    {
        return config('session.table', 'sessions');
    }
}
