        {{-- History --}}
        <div x-show="tab === 'history'" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             class="cw-panel">
            @php
                $flat = $activities->map(function ($a, $i) {
                    return (object) [
                        'idx' => $i,
                        'event' => $a->event ?? '',
                        'description' => $this->activityLabel($a->event ?? '', $a->description),
                        'causer' => $a->causer?->name ?? __('app.label.system'),
                        'time' => $a->created_at?->format('H:i'),
                        'day' => $a->created_at?->format('Y-m-d'),
                        'group' => $this->activityGroup($a->event ?? ''),
                        'comment' => data_get($a->properties, 'comment'),
                    ];
                })->values();
                $workflowCount = $flat->where('group', 'workflow')->count();
                $editCount = $flat->where('group', 'edit')->count();
                $totalCount = $flat->count();
            @endphp
            <section class="cw-card">
                <div class="cw-hd"><span class="cw-hd__ic">{!! $ic('heroicon-o-clock') !!}</span><h2 class="cw-hd__t">{{ __('app.label.execution_history') }}</h2></div>

                @if ($flat->isEmpty())
                    <div class="cw-bd"><p style="font-size:0.854rem;color:var(--m)">{{ __('app.label.no_history') }}</p></div>
                @else
                    <div class="cw-filters">
                        <button type="button" class="cw-chip" :class="historyFilter === 'all' ? 'cw-chip--active' : ''" @click="historyFilter = 'all'; historyShown = 8">
                            {{ __('app.label.all') }} <span class="cw-chip__c">{{ $totalCount }}</span>
                        </button>
                        <button type="button" class="cw-chip" :class="historyFilter === 'workflow' ? 'cw-chip--active' : ''" @click="historyFilter = 'workflow'; historyShown = 8">
                            {!! $ic('heroicon-o-arrows-right-left', 13) !!} {{ __('app.label.workflow_events') }} <span class="cw-chip__c">{{ $workflowCount }}</span>
                        </button>
                        <button type="button" class="cw-chip" :class="historyFilter === 'edit' ? 'cw-chip--active' : ''" @click="historyFilter = 'edit'; historyShown = 8">
                            {!! $ic('heroicon-o-pencil-square', 13) !!} {{ __('app.label.edit_events') }} <span class="cw-chip__c">{{ $editCount }}</span>
                        </button>
                    </div>

                    <div class="cw-bd">
                        @php $lastDay = null; @endphp
                        @foreach ($flat as $row)
                            @php $v = $this->activityVisual($row->event); $dayHd = $row->day !== $lastDay ? $dayLabel($row->day) : null; $lastDay = $row->day; @endphp
                            <div x-show="(historyFilter === 'all' || historyFilter === '{{ $row->group }}') && {{ $row->idx }} < historyShown">
                                @if ($dayHd)
                                    <div class="cw-day__hd" style="padding-top: {{ $loop->first ? '0' : '.6rem' }};">{{ $dayHd }}</div>
                                @endif
                                <div class="cw-tl">
                                    <span class="cw-tl__time">{{ $row->time }}</span>
                                    <span class="cw-tl__ic cw-tl__ic--{{ $v['color'] }}">{!! $ic($v['icon'], 15) !!}</span>
                                    <div class="cw-tl__bd">
                                        <div class="cw-tl__ds">{{ $row->description }}</div>
                                        <div class="cw-tl__mt"><span>{{ $row->causer }}</span></div>
                                        @if ($row->comment)<div class="cw-cmt" style="margin-top:.45rem">{{ $row->comment }}</div>@endif
                                    </div>
                                </div>
                            </div>
                        @endforeach

                        <button type="button" class="cw-loadmore"
                                x-show="historyShown < {{ $totalCount }}"
                                @click="historyShown = historyShown + 8">
                            {{ __('app.label.load_more') }}
                        </button>
                    </div>
                @endif
            </section>
        </div>
