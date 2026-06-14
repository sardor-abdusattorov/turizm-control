@php
    use App\Models\Contract;
    use App\Models\ContractApprover;

    $statusColor = Contract::getStatusColors()[$record->status] ?? 'gray';
    $statusLabel = Contract::getStatuses()[$record->status] ?? $record->status;
    $current = $record->currentApprover();

    $approvedCount = $record->approvers->where('status', ContractApprover::STATUS_APPROVED)->count();
    $totalCount = $record->approvers->count();
    $pendingCount = $record->approvers->where('status', ContractApprover::STATUS_PENDING)->count();

    $tone = [
        'success' => 'text-success-600 dark:text-success-400',
        'danger' => 'text-danger-600 dark:text-danger-400',
        'warning' => 'text-warning-600 dark:text-warning-400',
        'info' => 'text-info-600 dark:text-info-400',
        'gray' => 'text-gray-400',
    ];

    $meta = [
        ['icon' => 'heroicon-o-building-office-2', 'label' => __('app.label.contact_single'), 'value' => $record->contact?->name],
        ['icon' => 'heroicon-o-document-duplicate', 'label' => __('app.label.contract_template_single'), 'value' => $record->template?->name],
        ['icon' => 'heroicon-o-tag', 'label' => __('app.label.order_type_single'), 'value' => $record->orderType?->title ?: '—'],
        ['icon' => 'heroicon-o-user', 'label' => __('app.label.responsible'), 'value' => $record->responsible?->name],
        ['icon' => 'heroicon-o-banknotes', 'label' => __('app.label.amount'), 'value' => number_format((float) $record->amount, 2, '.', ' ').' '.($record->currency?->short_name ?? '')],
        ['icon' => 'heroicon-o-calendar-days', 'label' => __('app.label.signing_date'), 'value' => $record->signed_at?->format('d.m.Y') ?: '—'],
    ];
@endphp

<x-filament-panels::page>
    {{-- Hero --}}
    <section @class([
        'relative overflow-hidden rounded-2xl p-6 ring-1',
        'bg-success-50/60 ring-success-200 dark:bg-success-500/5 dark:ring-success-500/20' => $statusColor === 'success',
        'bg-warning-50/60 ring-warning-200 dark:bg-warning-500/5 dark:ring-warning-500/20' => $statusColor === 'warning',
        'bg-danger-50/60 ring-danger-200 dark:bg-danger-500/5 dark:ring-danger-500/20' => $statusColor === 'danger',
        'bg-gray-50 ring-gray-200 dark:bg-white/5 dark:ring-white/10' => in_array($statusColor, ['gray', 'info']),
    ])>
        <div class="flex flex-wrap items-start justify-between gap-4">
            <div class="min-w-0">
                <h1 class="text-2xl font-bold tracking-tight text-gray-950 dark:text-white">{{ $record->number }}</h1>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $record->title }}</p>
            </div>
            <x-filament::badge :color="$statusColor" size="lg">{{ $statusLabel }}</x-filament::badge>
        </div>

        @if ($totalCount > 0)
            <div class="mt-5 flex flex-wrap items-center gap-x-6 gap-y-2 border-t border-gray-200/70 pt-4 dark:border-white/10">
                @if ($current && $record->status === Contract::STATUS_IN_REVIEW)
                    <div class="flex items-center gap-2 text-sm">
                        @svg('heroicon-o-clock', 'h-5 w-5 text-warning-500')
                        <span class="text-gray-500 dark:text-gray-400">{{ __('app.label.current_step') }}:</span>
                        <span class="font-semibold text-gray-900 dark:text-white">{{ $current->user?->name }}</span>
                    </div>
                @endif
                <div class="flex items-center gap-3">
                    <span class="text-sm text-gray-500 dark:text-gray-400">{{ $approvedCount }} / {{ $totalCount }}</span>
                    <div class="h-2 w-32 overflow-hidden rounded-full bg-gray-200 dark:bg-white/10">
                        <div class="h-full rounded-full bg-primary-500 transition-all" style="width: {{ $totalCount ? round($approvedCount / $totalCount * 100) : 0 }}%"></div>
                    </div>
                </div>
            </div>
        @endif
    </section>

    {{-- Tabbed body --}}
    <div x-data="{ tab: 'document' }" class="mt-2">
        <x-filament::tabs>
            <x-filament::tabs.item alpine-active="tab === 'document'" x-on:click="tab = 'document'" icon="heroicon-o-document-text">
                {{ __('app.label.document') }}
            </x-filament::tabs.item>
            <x-filament::tabs.item alpine-active="tab === 'approval'" x-on:click="tab = 'approval'" icon="heroicon-o-users" :badge="$pendingCount ?: null">
                {{ __('app.label.approval_chain') }}
            </x-filament::tabs.item>
            <x-filament::tabs.item alpine-active="tab === 'history'" x-on:click="tab = 'history'" icon="heroicon-o-clock">
                {{ __('app.label.execution_history') }}
            </x-filament::tabs.item>
        </x-filament::tabs>

        {{-- DOCUMENT --}}
        <div x-show="tab === 'document'" class="mt-6 space-y-6">
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 xl:grid-cols-3">
                @foreach ($meta as $item)
                    <div class="rounded-xl bg-white p-4 ring-1 ring-gray-200 dark:bg-white/5 dark:ring-white/10">
                        <div class="flex items-center gap-2 text-gray-400">
                            @svg($item['icon'], 'h-4 w-4')
                            <span class="text-xs font-medium uppercase tracking-wide">{{ $item['label'] }}</span>
                        </div>
                        <div class="mt-1.5 truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $item['value'] ?: '—' }}</div>
                    </div>
                @endforeach
            </div>

            <x-filament::section>
                <x-slot name="heading">{{ __('app.label.attached_document') }}</x-slot>

                @if ($record->documentExists())
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex size-14 shrink-0 items-center justify-center rounded-xl bg-primary-50 text-primary-600 dark:bg-primary-500/10">
                            @svg('heroicon-o-document-text', 'h-8 w-8')
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold text-gray-900 dark:text-white">{{ $record->number }}.docx</div>
                            <div class="mt-0.5 text-sm text-gray-500">{{ $this->documentSizeLabel() }} · {{ $record->created_at->format('d.m.Y H:i') }}</div>
                        </div>
                        <div class="flex gap-2">
                            <x-filament::button tag="a" :href="$this->editorUrl($record->canBeEditedBy() ? 'edit' : 'view')" icon="heroicon-o-pencil-square" color="primary">
                                {{ __('app.action.open_editor') }}
                            </x-filament::button>
                            @if ($record->status === Contract::STATUS_APPROVED)
                                <x-filament::button tag="a" :href="route('contracts.pdf.download', ['contract' => $record])" icon="heroicon-o-document-arrow-down" color="gray">PDF</x-filament::button>
                            @endif
                        </div>
                    </div>

                    @if ($url = $this->pdfPreviewUrl())
                        <div class="mt-4 overflow-hidden rounded-xl border border-gray-200 dark:border-white/10">
                            <iframe src="{{ $url }}" class="w-full bg-white" style="height: 72vh;" loading="lazy"></iframe>
                        </div>
                    @endif
                @else
                    <div class="flex flex-col items-center justify-center gap-2 py-10 text-center">
                        @svg('heroicon-o-document', 'h-10 w-10 text-gray-300')
                        <p class="text-sm text-gray-500">{{ __('app.label.document_not_ready') }}</p>
                    </div>
                @endif
            </x-filament::section>
        </div>

        {{-- APPROVAL --}}
        <div x-show="tab === 'approval'" x-cloak class="mt-6">
            <x-filament::section>
                <x-slot name="heading">{{ __('app.label.approval_chain') }}</x-slot>

                @if ($record->approvers->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('app.label.no_approvers') }}</p>
                @else
                    <ol class="space-y-0">
                        @foreach ($record->approvers as $approver)
                            @php $visual = $this->approverVisual($approver); $isCurrent = $this->isCurrentApprover($approver); @endphp
                            <li class="flex gap-4">
                                <div class="flex flex-col items-center">
                                    <span @class(['flex size-10 items-center justify-center rounded-full ring-4 ring-white dark:ring-gray-900', 'bg-success-100 dark:bg-success-500/20' => $visual['color'] === 'success', 'bg-danger-100 dark:bg-danger-500/20' => $visual['color'] === 'danger', 'bg-warning-100 dark:bg-warning-500/20' => $visual['color'] === 'warning', 'bg-info-100 dark:bg-info-500/20' => $visual['color'] === 'info', 'bg-gray-100 dark:bg-white/10' => $visual['color'] === 'gray'])>
                                        <span class="{{ $tone[$visual['color']] ?? 'text-gray-400' }}">@svg($visual['icon'], 'h-5 w-5')</span>
                                    </span>
                                    @unless ($loop->last)
                                        <span class="my-1 w-0.5 flex-1 bg-gray-200 dark:bg-white/10"></span>
                                    @endunless
                                </div>

                                <div @class(['mb-4 flex-1 rounded-xl p-3 ring-1', 'bg-warning-50 ring-warning-200 dark:bg-warning-500/5 dark:ring-warning-500/20' => $isCurrent, 'bg-transparent ring-transparent' => ! $isCurrent])>
                                    <div class="flex items-center gap-3">
                                        <img src="{{ $this->approverAvatar($approver) }}" alt="" class="size-9 shrink-0 rounded-full object-cover">
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $approver->user?->name }}</div>
                                            <div class="truncate text-xs text-gray-500">{{ $approver->user?->department?->name }}</div>
                                        </div>
                                        <x-filament::badge :color="$visual['color']">{{ ContractApprover::getStatuses()[$approver->status] ?? $approver->status }}</x-filament::badge>
                                    </div>

                                    @if ($approver->acted_at)
                                        <div class="mt-2 pl-12 text-xs text-gray-500">{{ $approver->acted_at->format('d.m.Y H:i') }}</div>
                                    @elseif ($approver->isPending() && $approver->due_at)
                                        <div class="mt-2 flex items-center gap-1.5 pl-12 text-xs {{ $approver->isOverdue() ? 'font-medium text-danger-600' : 'text-gray-500' }}">
                                            @svg('heroicon-m-clock', 'h-4 w-4')
                                            {{ __('app.label.due_at') }} {{ $approver->due_at->format('d.m.Y H:i') }}
                                        </div>
                                    @endif

                                    @if ($approver->comment)
                                        <div class="mt-2 ml-12 rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $approver->comment }}</div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-filament::section>
        </div>

        {{-- HISTORY --}}
        <div x-show="tab === 'history'" x-cloak class="mt-6">
            <x-filament::section>
                <x-slot name="heading">{{ __('app.label.execution_history') }}</x-slot>

                @php $activities = $this->getActivities(); $prevKey = null; @endphp
                @if ($activities->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('app.label.no_history') }}</p>
                @else
                    <ol class="space-y-5">
                        @foreach ($activities as $activity)
                            @php $key = ($activity->description ?? '').'|'.$activity->created_at?->format('YmdHi'); @endphp
                            @continue($key === $prevKey)
                            @php $prevKey = $key; $av = $this->activityVisual($activity->event ?? ''); @endphp
                            <li class="flex gap-3">
                                <span @class(['mt-0.5 flex size-8 shrink-0 items-center justify-center rounded-full', 'bg-success-100 dark:bg-success-500/20' => $av['color'] === 'success', 'bg-danger-100 dark:bg-danger-500/20' => $av['color'] === 'danger', 'bg-warning-100 dark:bg-warning-500/20' => $av['color'] === 'warning', 'bg-info-100 dark:bg-info-500/20' => $av['color'] === 'info', 'bg-gray-100 dark:bg-white/10' => $av['color'] === 'gray'])>
                                    <span class="{{ $tone[$av['color']] ?? 'text-gray-400' }}">@svg($av['icon'], 'h-4 w-4')</span>
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm font-medium text-gray-800 dark:text-gray-200">{{ $activity->description ?: $activity->event }}</div>
                                    <div class="mt-0.5 text-xs text-gray-500">{{ $activity->causer?->name ?? __('app.label.system') }} · {{ $activity->created_at?->format('d.m.Y H:i') }}</div>
                                    @if ($comment = data_get($activity->properties, 'comment'))
                                        <div class="mt-1.5 rounded-lg bg-gray-100 px-3 py-2 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">{{ $comment }}</div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-filament::section>
        </div>
    </div>
</x-filament-panels::page>
