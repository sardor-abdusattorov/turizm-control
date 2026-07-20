    @php
        $countdownUnits = [
            'd' => __('app.unit.day_short'),
            'h' => __('app.unit.hour_short'),
            'm' => __('app.unit.minute_short'),
            's' => __('app.unit.second_short'),
        ];
    @endphp
    <script>
        // Live SLA countdown for the current approver's due_at. Re-ticks every
        // second so the meta-strip pill reads "1д 12ч 04м" / "3ч 22м" / "12м"
        // in the panel's language. Switches to a red "Overdue · X ago" once
        // the deadline passes.
        window.contractCountdownUnits = @json($countdownUnits);

        window.contractCountdown = (iso) => ({
            due: new Date(iso),
            overdue: false,
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

                const d = Math.floor(diff / 86400);
                const h = Math.floor((diff % 86400) / 3600);
                const m = Math.floor((diff % 3600) / 60);
                const s = diff % 60;
                const u = window.contractCountdownUnits;

                let pretty;
                if (d > 0) {
                    pretty = `${d}${u.d} ${h}${u.h} ${m}${u.m}`;
                } else if (h > 0) {
                    pretty = `${h}${u.h} ${m}${u.m} ${s}${u.s}`;
                } else {
                    pretty = `${m}${u.m} ${s}${u.s}`;
                }

                this.label = this.overdue
                    ? @json(__('app.label.overdue')) + ' · ' + pretty
                    : @json(__('app.label.due_in', ['time' => ''])) + pretty;
            },
        });
    </script>
