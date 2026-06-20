<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('contracts:send-approval-reminders')
    ->hourly()
    ->withoutOverlapping();

// Pull the daily CBU rates every morning at 07:00 Tashkent so that
// contract totals shown later in the day already match the published
// rate. withoutOverlapping() protects us from a hung previous fetch.
Schedule::command('currency:update')
    ->dailyAt('07:00')
    ->timezone('Asia/Tashkent')
    ->withoutOverlapping();
