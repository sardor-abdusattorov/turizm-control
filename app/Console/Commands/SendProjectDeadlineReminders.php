<?php

namespace App\Console\Commands;

use App\Models\Project;
use App\Services\Projects\ProjectDeadlineNotifier;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('projects:send-deadline-reminders')]
#[Description('Remind directors and managers about projects starting soon')]
class SendProjectDeadlineReminders extends Command
{
    /**
     * Days-before-start marks at which the countdown fires. The command runs
     * once a day, and each project matches a mark exactly once — so nobody
     * gets the same reminder twice.
     *
     * @var array<int, int>
     */
    private const THRESHOLD_DAYS = [14, 7, 3, 1];

    public function handle(ProjectDeadlineNotifier $notifier): int
    {
        $sent = 0;

        foreach (self::THRESHOLD_DAYS as $days) {
            Project::query()
                ->active()
                ->whereDate('starts_on', today()->addDays($days))
                ->get()
                ->each(function (Project $project) use ($notifier, $days, &$sent): void {
                    $notifier->notifyDeadline($project, $days);
                    $sent++;
                });
        }

        $this->info("Project deadline reminders sent: {$sent}");

        return self::SUCCESS;
    }
}
