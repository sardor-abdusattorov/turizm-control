<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('contracts:send-approval-reminders')
    ->hourly()
    ->withoutOverlapping();

Schedule::command('currency:update')
    ->dailyAt('07:00')
    ->timezone('Asia/Tashkent')
    ->withoutOverlapping();

Schedule::command('projects:send-deadline-reminders')
    ->dailyAt('08:30')
    ->timezone('Asia/Tashkent')
    ->withoutOverlapping();

Schedule::command('contracts:send-payment-reminders')
    ->dailyAt('09:00')
    ->timezone('Asia/Tashkent')
    ->withoutOverlapping();
