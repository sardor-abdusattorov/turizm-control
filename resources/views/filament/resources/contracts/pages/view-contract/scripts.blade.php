    <script>
        // Live SLA countdown for the current approver's due_at. Re-ticks every
        // second so the meta-strip pill reads "1d 12h 04m" / "3h 22m" / "12m".
        // Switches to a red "Overdue · X ago" once the deadline passes.
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

                let pretty;
                if (d > 0) {
                    pretty = `${d}d ${h}h ${m}m`;
                } else if (h > 0) {
                    pretty = `${h}h ${m}m ${s}s`;
                } else {
                    pretty = `${m}m ${s}s`;
                }

                this.label = this.overdue
                    ? @json(__('app.label.overdue')) + ' · ' + pretty
                    : @json(__('app.label.due_in', ['time' => ''])) + pretty;
            },
        });
    </script>
