@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\ProjectParticipant> $participations */
    /** @var \Illuminate\Support\Collection<int, array{currency: string, count: int, total: float, paid: float}> $totals */
    $fmt = fn ($n) => \App\Support\Money::format($n);
@endphp

<div>
    @if ($participations->isEmpty())
        <p class="text-sm text-gray-500 dark:text-gray-400">
            {{ __('app.message.no_projects_for_contact') }}
        </p>
    @else
        {{-- One row per participation: project + role on the left, pledged and
             paid on the right. Tailwind utilities + inline flex only, because
             this renders inside a modal outside the .pj token scope. --}}
        <div class="overflow-hidden rounded-xl text-sm ring-1 ring-gray-950/5 dark:ring-white/10">
            @foreach ($participations as $p)
                <div class="border-t border-gray-100 text-gray-700 first:border-t-0 dark:border-white/5 dark:text-gray-200"
                     style="display:flex;align-items:center;gap:.75rem;padding:.6rem .9rem;">
                    <div style="flex:1 1 auto;min-width:0;">
                        <div style="font-weight:600;overflow:hidden;text-overflow:ellipsis;white-space:nowrap;">
                            {{ $p->project?->name ?? '—' }}
                        </div>
                        <div class="text-gray-400" style="font-size:.72rem;margin-top:.1rem;">
                            {{ $p->role->label() }}
                        </div>
                    </div>
                    <div style="flex:0 0 auto;text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;">
                        <div style="font-weight:600;">{{ $fmt($p->amount) }} {{ $p->currency?->short_name }}</div>
                        <div class="text-gray-400" style="font-size:.72rem;margin-top:.1rem;">
                            {{ __('app.label.paid') }}: {{ $fmt($p->paid_amount) }}
                        </div>
                    </div>
                </div>
            @endforeach
        </div>

        @if ($totals->isNotEmpty())
            <div style="margin-top:.85rem;display:flex;flex-direction:column;gap:.35rem;">
                @foreach ($totals as $t)
                    <div class="text-gray-600 dark:text-gray-300"
                         style="display:flex;align-items:center;gap:.75rem;font-size:.8rem;">
                        <span style="font-weight:600;flex:0 0 3rem;">{{ $t['currency'] }}</span>
                        <span style="flex:1 1 auto;min-width:0;">{{ $t['count'] }}</span>
                        <span style="margin-left:auto;text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;">
                            {{ $fmt($t['paid']) }} / {{ $fmt($t['total']) }} {{ $t['currency'] }}
                        </span>
                    </div>
                @endforeach
            </div>
        @endif
    @endif
</div>
