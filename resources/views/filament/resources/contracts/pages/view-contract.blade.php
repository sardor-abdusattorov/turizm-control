@php
    use App\Models\Contract;
    use App\Models\ContractApprover;

    $statusColor = Contract::getStatusColors()[$record->status] ?? 'gray';
    $statusLabel = Contract::getStatuses()[$record->status] ?? $record->status;
    $current = $record->currentApprover();

    $iconColorClass = [
        'success' => 'text-success-500',
        'danger' => 'text-danger-500',
        'warning' => 'text-warning-500',
        'info' => 'text-info-500',
        'gray' => 'text-gray-400',
    ];
@endphp

<x-filament-panels::page>
    {{-- Status banner --}}
    <section @class([
        'rounded-xl px-5 py-4 ring-1',
        'bg-success-50 ring-success-200 dark:bg-success-500/10 dark:ring-success-500/30' => $statusColor === 'success',
        'bg-warning-50 ring-warning-200 dark:bg-warning-500/10 dark:ring-warning-500/30' => $statusColor === 'warning',
        'bg-danger-50 ring-danger-200 dark:bg-danger-500/10 dark:ring-danger-500/30' => $statusColor === 'danger',
        'bg-gray-50 ring-gray-200 dark:bg-white/5 dark:ring-white/10' => in_array($statusColor, ['gray', 'info']),
    ])>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <div class="flex items-center gap-3">
                <span class="text-lg font-bold text-gray-950 dark:text-white">{{ $record->number }}</span>
                <span class="text-sm text-gray-500 dark:text-gray-400">{{ $record->title }}</span>
            </div>
            <div class="flex items-center gap-3">
                @if ($current && $record->status === Contract::STATUS_IN_REVIEW)
                    <span class="text-sm text-gray-500 dark:text-gray-400">
                        {{ __('app.label.current_step') }}:
                        <span class="font-medium text-gray-800 dark:text-gray-200">{{ $current->user?->name }}</span>
                    </span>
                @endif
                <x-filament::badge :color="$statusColor" size="lg">{{ $statusLabel }}</x-filament::badge>
            </div>
        </div>
    </section>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        {{-- Main column --}}
        <div class="space-y-6 lg:col-span-2">
            {{-- Meta --}}
            <x-filament::section :heading="__('app.label.basic_information')" icon="heroicon-o-clipboard-document-list">
                <dl class="grid grid-cols-1 gap-x-6 gap-y-4 sm:grid-cols-2">
                    @foreach ([
                        'app.label.contact_single' => $record->contact?->name,
                        'app.label.contract_template_single' => $record->template?->name,
                        'app.label.order_type_single' => $record->orderType?->title ?: '—',
                        'app.label.responsible' => $record->responsible?->name,
                        'app.label.amount' => number_format((float) $record->amount, 2, '.', ' ').' '.($record->currency?->short_name ?? ''),
                        'app.label.signing_date' => $record->signed_at?->format('d.m.Y') ?: '—',
                    ] as $label => $value)
                        <div>
                            <dt class="text-xs font-medium uppercase tracking-wide text-gray-400">{{ __($label) }}</dt>
                            <dd class="mt-0.5 text-sm text-gray-800 dark:text-gray-200">{{ $value }}</dd>
                        </div>
                    @endforeach
                </dl>
            </x-filament::section>

            {{-- Document --}}
            <x-filament::section :heading="__('app.label.attached_document')" icon="heroicon-o-document-text">
                @if ($record->documentExists())
                    <div class="flex flex-wrap items-center gap-4">
                        <div class="flex size-14 shrink-0 items-center justify-center rounded-lg bg-primary-50 text-primary-600 dark:bg-primary-500/10">
                            @svg('heroicon-o-document-text', 'h-8 w-8')
                        </div>
                        <div class="min-w-0 flex-1">
                            <div class="truncate font-semibold text-gray-900 dark:text-white">{{ $record->number }}.docx</div>
                            <div class="mt-0.5 text-sm text-gray-500">
                                {{ $this->documentSizeLabel() }} · {{ $record->created_at->format('d.m.Y H:i') }}
                            </div>
                        </div>
                        <div class="flex gap-2">
                            <x-filament::button
                                tag="a"
                                :href="$this->editorUrl($record->canBeEditedBy() ? 'edit' : 'view')"
                                icon="heroicon-o-pencil-square"
                                color="primary"
                            >
                                {{ __('app.action.open_editor') }}
                            </x-filament::button>

                            @if ($record->status === Contract::STATUS_APPROVED)
                                <x-filament::button
                                    tag="a"
                                    :href="route('contracts.pdf.download', ['contract' => $record])"
                                    icon="heroicon-o-document-arrow-down"
                                    color="gray"
                                >
                                    PDF
                                </x-filament::button>
                            @endif
                        </div>
                    </div>

                    @if ($url = $this->pdfPreviewUrl())
                        <div class="mt-4 overflow-hidden rounded-lg border border-gray-200 dark:border-white/10">
                            <iframe src="{{ $url }}" class="w-full bg-white" style="height: 70vh;" loading="lazy"></iframe>
                        </div>
                    @endif
                @else
                    <p class="text-sm text-gray-500">{{ __('app.label.document_not_ready') }}</p>
                @endif
            </x-filament::section>
        </div>

        {{-- Side column --}}
        <div class="space-y-6">
            {{-- Approval timeline --}}
            <x-filament::section :heading="__('app.label.approval_chain')" icon="heroicon-o-users">
                @if ($record->approvers->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('app.label.no_approvers') }}</p>
                @else
                    <ol class="space-y-1">
                        @foreach ($record->approvers as $approver)
                            @php $visual = $this->approverVisual($approver); @endphp
                            <li class="flex gap-3">
                                <div class="flex flex-col items-center">
                                    <span class="{{ $iconColorClass[$visual['color']] ?? 'text-gray-400' }}">
                                        @svg($visual['icon'], 'h-6 w-6')
                                    </span>
                                    @unless ($loop->last)
                                        <span class="my-1 w-px flex-1 bg-gray-200 dark:bg-white/10"></span>
                                    @endunless
                                </div>

                                <div @class(['flex-1 pb-4', 'rounded-lg bg-warning-50 px-2 py-1.5 dark:bg-warning-500/10' => $this->isCurrentApprover($approver)])>
                                    <div class="flex items-center gap-2">
                                        <img src="{{ $this->approverAvatar($approver) }}" alt="" class="size-8 shrink-0 rounded-full object-cover">
                                        <div class="min-w-0 flex-1">
                                            <div class="truncate text-sm font-semibold text-gray-900 dark:text-white">{{ $approver->user?->name }}</div>
                                            <div class="truncate text-xs text-gray-500">{{ $approver->user?->department?->name }}</div>
                                        </div>
                                    </div>

                                    @if ($approver->acted_at)
                                        <div class="mt-1 pl-10 text-xs text-gray-500">{{ $approver->acted_at->format('d.m.Y H:i') }}</div>
                                    @elseif ($approver->isPending() && $approver->due_at)
                                        <div class="mt-1 pl-10 text-xs {{ $approver->isOverdue() ? 'font-medium text-danger-600' : 'text-gray-500' }}">
                                            ⏳ {{ __('app.label.due_at') }} {{ $approver->due_at->format('d.m.Y H:i') }}
                                        </div>
                                    @endif

                                    @if ($approver->comment)
                                        <div class="mt-1.5 ml-10 rounded bg-gray-50 px-2 py-1 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                            {{ $approver->comment }}
                                        </div>
                                    @endif
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </x-filament::section>

            {{-- Execution history --}}
            <x-filament::section :heading="__('app.label.execution_history')" icon="heroicon-o-clock" collapsible>
                @php $activities = $this->getActivities(); @endphp
                @if ($activities->isEmpty())
                    <p class="text-sm text-gray-500">{{ __('app.label.no_history') }}</p>
                @else
                    <ol class="space-y-4">
                        @foreach ($activities as $activity)
                            @php $av = $this->activityVisual($activity->event ?? ''); @endphp
                            <li class="flex gap-3">
                                <span class="{{ $iconColorClass[$av['color']] ?? 'text-gray-400' }} mt-0.5">
                                    @svg($av['icon'], 'h-5 w-5')
                                </span>
                                <div class="min-w-0 flex-1">
                                    <div class="text-sm text-gray-800 dark:text-gray-200">{{ $activity->description ?: $activity->event }}</div>
                                    <div class="mt-0.5 text-xs text-gray-500">
                                        {{ $activity->causer?->name ?? __('app.label.system') }}
                                        · {{ $activity->created_at?->format('d.m.Y H:i') }}
                                    </div>
                                    @if ($comment = data_get($activity->properties, 'comment'))
                                        <div class="mt-1 rounded bg-gray-50 px-2 py-1 text-xs text-gray-600 dark:bg-white/5 dark:text-gray-300">
                                            {{ $comment }}
                                        </div>
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
