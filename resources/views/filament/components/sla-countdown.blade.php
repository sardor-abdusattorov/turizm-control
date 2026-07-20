@php
    /**
     * Reusable live SLA countdown badge.
     *
     * @var \Carbon\CarbonInterface|null $due  ISO8601 timestamp when the
     *                                         current approver's slot expires
     *                                         (null hides the badge).
     */

    if (! isset($due) && isset($getState)) {
        $due = $getState();
    }

    if (! $due) {
        return;
    }

    if (is_string($due)) {
        try {
            $due = \Illuminate\Support\Carbon::parse($due);
        } catch (\Throwable $e) {
            return;
        }
    }

    $iso = $due->toIso8601String();
@endphp

<span class="sla-cd"
      x-data="slaCountdown('{{ $iso }}')"
      x-init="start()"
      :class="{
          'sla-cd--over': overdue,
          'sla-cd--soon': !overdue && urgent,
          'sla-cd--ok': !overdue && !urgent,
      }"
      :title="absolute">
    <template x-if="overdue">
        <span class="sla-cd__ic">{!! svg('heroicon-m-exclamation-triangle', '')->toHtml() !!}</span>
    </template>
    <template x-if="!overdue">
        <span class="sla-cd__ic">{!! svg('heroicon-m-clock', '')->toHtml() !!}</span>
    </template>
    <span x-text="label"></span>
</span>

@assets
    @php
        $slaCountdownUnits = [
            'd' => __('app.unit.day_short'),
            'h' => __('app.unit.hour_short'),
            'm' => __('app.unit.minute_short'),
            's' => __('app.unit.second_short'),
        ];
    @endphp
    <script>
        window.slaCountdownUnits = @json($slaCountdownUnits);

        window.slaCountdown = (iso) => ({
            due: new Date(iso),
            overdue: false,
            urgent: false,
            label: '',
            absolute: '',
            _t: null,

            start() {
                this.absolute = this.due.toLocaleString();
                this.tick();
                this._t = setInterval(() => this.tick(), 1000);
            },

            tick() {
                const now = new Date();
                let diff = Math.floor((this.due - now) / 1000);
                this.overdue = diff < 0;
                if (this.overdue) {
                    diff = -diff;
                }
                // "Urgent" zone — under 1 hour to deadline.
                this.urgent = !this.overdue && diff < 3600;

                const d = Math.floor(diff / 86400);
                const h = Math.floor((diff % 86400) / 3600);
                const m = Math.floor((diff % 3600) / 60);
                const s = diff % 60;
                const u = window.slaCountdownUnits;

                // Show seconds only inside the last hour; days+hours otherwise.
                let pretty;
                if (d > 0) {
                    pretty = `${d}${u.d} ${h}${u.h} ${m}${u.m}`;
                } else if (h > 0) {
                    pretty = `${h}${u.h} ${m}${u.m}`;
                } else {
                    pretty = `${m}${u.m} ${s}${u.s}`;
                }

                this.label = this.overdue
                    ? @json(__('app.label.overdue')) + ' · ' + pretty
                    : @json(__('app.label.due_in', ['time' => ''])) + pretty;
            },
        });
    </script>
    <style>
        .sla-cd {
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            padding: .22rem .55rem;
            border-radius: 999px;
            font-size: .76rem;
            font-weight: 600;
            line-height: 1.1;
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .sla-cd__ic {
            display: inline-flex;
            width: .85rem;
            height: .85rem;
        }
        .sla-cd__ic svg {
            width: 100%;
            height: 100%;
        }
        .sla-cd--ok {
            background: rgba(16,185,129,.12);
            color: #047857;
        }
        .dark .sla-cd--ok {
            background: rgba(16,185,129,.20);
            color: #6ee7b7;
        }
        .sla-cd--soon {
            background: rgba(251,146,60,.15);
            color: #c2410c;
        }
        .dark .sla-cd--soon {
            background: rgba(251,146,60,.20);
            color: #fdba74;
        }
        .sla-cd--over {
            background: rgba(239,68,68,.15);
            color: #b91c1c;
            animation: slaPulse 1.6s ease-in-out infinite;
        }
        .dark .sla-cd--over {
            background: rgba(239,68,68,.22);
            color: #fca5a5;
        }
        @keyframes slaPulse {
            0%, 100% { box-shadow: 0 0 0 0 rgba(239,68,68,.55); }
            70%      { box-shadow: 0 0 0 6px rgba(239,68,68,0); }
        }
        @media (prefers-reduced-motion: reduce) {
            .sla-cd--over { animation: none; }
        }
    </style>
@endassets
