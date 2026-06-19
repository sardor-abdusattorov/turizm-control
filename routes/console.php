<?php

use Illuminate\Support\Facades\Schedule;

Schedule::command('contracts:send-approval-reminders')
    ->hourly()
    ->withoutOverlapping();
