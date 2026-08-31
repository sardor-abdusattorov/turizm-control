<?php

namespace App\Services\Projects;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Projects\BaseProjectResource;
use App\Models\Contract;
use App\Models\Project;
use App\Models\User;
use App\Services\Notifications\RendersInRecipientLocale;
use App\Services\Telegram\TelegramService;
use Illuminate\Support\Collection;

class ProjectDeadlineNotifier
{
    use RendersInRecipientLocale;

    public function __construct(public TelegramService $telegram) {}

    public function notifyDeadline(Project $project, int $daysLeft): void
    {
        $this->notifyDirectors($project, $daysLeft);
        $this->notifyManagers($project, $daysLeft);
    }

    private function notifyDirectors(Project $project, int $daysLeft): void
    {
        $total = $project->contracts()->count();
        $pending = $this->pendingContractsQuery($project)->count();

        $feesTotal = (float) $project->feesTotal();
        $paidPercent = $feesTotal > 0
            ? (int) round((float) $project->paidTotal() / $feesTotal * 100)
            : null;

        $recipients = User::query()
            ->whereHas('roles', fn ($query) => $query->where('name', Contract::DIRECTOR_ROLE))
            ->where('status', User::STATUS_ACTIVE)
            ->whereHas('telegram')
            ->with('telegram')
            ->get();

        foreach ($recipients as $recipient) {
            $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $project, $daysLeft, $total, $pending, $paidPercent): void {
                $lines = ['<b>⏰ '.__('app.notification.project_deadline.title', [
                    'name' => $project->name,
                    'days' => $daysLeft,
                    'date' => $project->starts_on?->format('d.m.Y') ?? '—',
                ]).'</b>'];

                $lines[] = $total > 0 && $pending > 0
                    ? __('app.notification.project_deadline.director_contracts', ['pending' => $pending, 'total' => $total])
                    : __('app.notification.project_deadline.director_contracts_ok', ['total' => $total]);

                if ($paidPercent !== null) {
                    $lines[] = __('app.notification.project_deadline.director_fees', ['percent' => $paidPercent]);
                }

                $this->telegram->queue(
                    $recipient->telegram?->chat_id,
                    implode("\n", $lines),
                    [[
                        [
                            'text' => __('app.action.open_project'),
                            'url' => BaseProjectResource::resourceFor($project)::getUrl('view', ['record' => $project]),
                        ],
                    ]],
                );
            });
        }
    }

    private function notifyManagers(Project $project, int $daysLeft): void
    {
        $byResponsible = $this->pendingContractsQuery($project)
            ->with(['responsible.telegram'])
            ->get()
            ->groupBy('responsible_id');

        foreach ($byResponsible as $contracts) {
            /** @var Collection<int, Contract> $contracts */
            $recipient = $contracts->first()?->responsible;

            if (! $recipient || ! $recipient->telegram) {
                continue;
            }

            $this->inRecipientTelegramLocale($recipient, function () use ($recipient, $project, $daysLeft, $contracts): void {
                $lines = ['<b>⏰ '.__('app.notification.project_deadline.title', [
                    'name' => $project->name,
                    'days' => $daysLeft,
                    'date' => $project->starts_on?->format('d.m.Y') ?? '—',
                ]).'</b>'];

                $lines[] = __('app.notification.project_deadline.manager_intro');

                foreach ($contracts as $contract) {
                    $lines[] = '• №'.$contract->number.' — '.$contract->status->label();
                }

                $first = $contracts->first();

                $this->telegram->queue(
                    $recipient->telegram?->chat_id,
                    implode("\n", $lines),
                    [[
                        [
                            'text' => __('app.action.open_contract'),
                            'url' => ContractResource::getUrl('view', ['record' => $first->id]),
                        ],
                    ]],
                );
            });
        }
    }

    private function pendingContractsQuery(Project $project)
    {
        return $project->contracts()->whereNotIn('status', [
            Contract::STATUS_APPROVED->value,
            Contract::STATUS_REJECTED->value,
        ]);
    }
}
