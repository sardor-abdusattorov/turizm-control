<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('contracts:send-approval-reminders')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('currency:update')
    ->dailyAt('07:00')
    ->timezone('Asia/Tashkent')
    ->withoutOverlapping();
