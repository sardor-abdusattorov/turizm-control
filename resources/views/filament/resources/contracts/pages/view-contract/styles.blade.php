    <style>
        [x-cloak] {
            display: none !important;
        }
        /* Base palette tokens (--s/--t/--m/--accent/...) live in theme.css.
           The cw page tunes a few values for tighter contrast against the
           chain/progress bars and keeps its page-specific extras (--r,
           --track, --accent-ring, --accent-on). */
        .cw {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            --r: rgba(15,20,25,.08);
            --d: rgba(15,20,25,.07);
            --track: #e6ebf1;
            --accent-soft: rgba(99,102,241,.12);
            --accent-softer: rgba(99,102,241,.05);
            --accent-ring: rgba(99,102,241,.18);
            --accent-on: #fff;
        }
        .dark .cw {
            --r: rgba(255,255,255,.08);
            --d: rgba(255,255,255,.07);
            --track: rgba(255,255,255,.10);
            --accent-strong: #a5b4fc;
            --accent-soft: rgba(129,140,248,.16);
            --accent-softer: rgba(129,140,248,.08);
            --accent-ring: rgba(129,140,248,.25);
        }
        .cw-card {
            background: var(--s);
            border-radius: 1rem;
            box-shadow: 0 0 0 1px var(--r), 0 1px 3px rgba(15,20,25,.06), 0 1px 2px rgba(15,20,25,.04);
            overflow: hidden;
        }
        .cw-hd {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: 1.15rem 1.5rem;
            border-bottom: 1px solid var(--d);
        }
        .cw-hd__ic {
            color: var(--m2);
            display: inline-flex;
        }
        .cw-hd__t {
            font-size: 0.9rem;
            font-weight: 650;
            color: var(--t);
            margin: 0;
            flex: 1;
            letter-spacing: -.005em;
        }
        .cw-hd__c {
            font-size: 0.724rem;
            font-weight: 600;
            color: var(--m);
            background: var(--soft);
            padding: .18rem .6rem;
            border-radius: 999px;
        }
        .cw-bd {
            padding: 1.25rem;
        }

        /* layout */
        .cw-cols {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            align-items: stretch;
        }
        .cw-main,.cw-side {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            min-width: 0;
        }
        .cw-panel {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
        }
        .cw-tabs {
            display: flex;
            align-items: center;
            gap: .15rem;
            flex-wrap: wrap;
        }
        .cw-tabs__status {
            margin-left: auto;
        }
        .cw-tab {
            display: flex;
            align-items: center;
            gap: .45rem;
            padding: .7rem 1.15rem;
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--m);
            background: transparent;
            border: 0;
            border-radius: .7rem;
            cursor: pointer;
            transition: all .15s;
        }
        .cw-tab:hover {
            color: var(--t);
            background: var(--soft);
        }
        .cw-tab--active {
            color: var(--accent);
            background: var(--s);
            box-shadow: 0 0 0 1px var(--r), 0 1px 2px rgba(0,0,0,.05);
        }
        .cw-tab__c {
            font-size: 0.74rem;
            font-weight: 700;
            color: var(--m);
            background: var(--soft);
            border-radius: 999px;
            padding: .12rem .5rem;
        }
        .cw-tab--active .cw-tab__c {
            background: var(--accent-soft);
            color: var(--accent);
        }

        /* hero */
        .cw-hero {
            position: relative;
            display: flex;
            align-items: center;
            gap: 1.25rem;
            flex-wrap: wrap;
            border-radius: 1.1rem;
            padding: 1.35rem 1.5rem 1.35rem 1.65rem;
            overflow: hidden;
            box-shadow: 0 0 0 1px var(--r), 0 1px 2px rgba(15,20,25,.05);
        }
        .cw-hero::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 4px;
        }
        .cw-hero--gray {
            background: linear-gradient(120deg,rgba(148,163,184,.08),var(--s) 52%);
        }
        .cw-hero--gray::before {
            background: #94a3b8;
        }
        .cw-hero--warning {
            background: linear-gradient(120deg,rgba(251,146,60,.09),var(--s) 52%);
        }
        .cw-hero--warning::before {
            background: #fb923c;
        }
        .dark .cw-hero--warning {
            background: linear-gradient(120deg,rgba(251,146,60,.10),var(--s) 52%);
        }
        .cw-hero--success {
            background: linear-gradient(120deg,rgba(16,185,129,.08),var(--s) 52%);
        }
        .cw-hero--success::before {
            background: #10b981;
        }
        .dark .cw-hero--success {
            background: linear-gradient(120deg,rgba(16,185,129,.10),var(--s) 52%);
        }
        .cw-hero--danger {
            background: linear-gradient(120deg,rgba(239,68,68,.08),var(--s) 52%);
        }
        .cw-hero--danger::before {
            background: #ef4444;
        }
        .dark .cw-hero--danger {
            background: linear-gradient(120deg,rgba(239,68,68,.10),var(--s) 52%);
        }
        .cw-hero--info {
            background: linear-gradient(120deg,var(--accent-soft),var(--s) 52%);
        }
        .cw-hero--info::before {
            background: var(--accent);
        }
        .cw-hero__l {
            display: flex;
            flex-direction: column;
            gap: .5rem;
            min-width: 0;
        }
        .cw-hero__msg {
            font-size: 1.02rem;
            font-weight: 650;
            color: var(--t);
            letter-spacing: -.01em;
        }
        .cw-hero__sla {
            font-size: 0.765rem;
            font-weight: 650;
            color: #dc2626;
            display: inline-flex;
            align-items: center;
            gap: .3rem;
        }
        .cw-hero__due {
            font-size: 0.765rem;
            color: var(--m);
            display: inline-flex;
            align-items: center;
            gap: .3rem;
        }
        .cw-hero__r {
            display: flex;
            align-items: center;
            gap: 1.4rem;
            margin-left: auto;
        }
        .cw-hero__cur {
            display: flex;
            align-items: center;
            gap: .6rem;
        }
        .cw-hero__cur img {
            width: 2.4rem;
            height: 2.4rem;
            border-radius: 999px;
            object-fit: cover;
            box-shadow: 0 0 0 2px #fb923c;
        }
        .cw-hero__lbl {
            font-size: 0.664rem;
            color: var(--m2);
            text-transform: uppercase;
            letter-spacing: .05em;
        }
        .cw-hero__nm {
            font-size: 0.854rem;
            font-weight: 650;
            color: var(--t);
        }
        .cw-ring {
            position: relative;
            width: 3.7rem;
            height: 3.7rem;
            flex-shrink: 0;
            border-radius: 50%;
            background: conic-gradient(#10b981 calc(var(--p)*1%), var(--track) 0);
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .cw-ring::before {
            content: '';
            position: absolute;
            inset: .32rem;
            border-radius: 50%;
            background: var(--s);
        }
        .cw-ring span {
            position: relative;
            font-size: 0.765rem;
            font-weight: 750;
            color: var(--t);
        }

        /* pills */
        .cw-pill {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .24rem .62rem .24rem .5rem;
            border-radius: 999px;
            font-size: 0.724rem;
            font-weight: 650;
            line-height: 1;
            white-space: nowrap;
        }
        .cw-pill::before {
            content: '';
            width: .42rem;
            height: .42rem;
            border-radius: 50%;
            background: currentColor;
            flex-shrink: 0;
        }
        .cw-pill--lg {
            padding: .36rem .9rem .36rem .7rem;
            font-size: 0.805rem;
        }
        .cw-pill--lg::before {
            width: .5rem;
            height: .5rem;
        }
        .cw-pill--success {
            background: #d1fae5;
            color: #047857;
        }
        .dark .cw-pill--success {
            background: rgba(16,185,129,.16);
            color: #6ee7b7;
        }
        .cw-pill--warning {
            background: #ffedd5;
            color: #c2410c;
        }
        .dark .cw-pill--warning {
            background: rgba(251,146,60,.16);
            color: #fdba74;
        }
        .cw-pill--danger {
            background: #fee2e2;
            color: #b91c1c;
        }
        .dark .cw-pill--danger {
            background: rgba(239,68,68,.16);
            color: #fca5a5;
        }
        .cw-pill--info {
            background: #dbeafe;
            color: #1d4ed8;
        }
        .dark .cw-pill--info {
            background: rgba(59,130,246,.16);
            color: #93c5fd;
        }
        .cw-pill--primary {
            background: var(--accent-soft);
            color: var(--accent-strong);
        }
        .dark .cw-pill--primary {
            background: var(--accent-ring);
            color: var(--accent);
        }
        .cw-pill--gray {
            background: #f1f5f9;
            color: #64748b;
        }
        .dark .cw-pill--gray {
            background: rgba(255,255,255,.07);
            color: #cbd5e1;
        }

        /* approval chain — progress band */
        .cw-prog {
            display: flex;
            flex-direction: column;
            gap: .8rem;
            padding: 1.05rem 1.5rem 1.2rem;
            border-bottom: 1px solid var(--d);
        }
        .cw-prog__top {
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: .65rem 1.25rem;
            flex-wrap: wrap;
        }
        .cw-prog__l {
            display: flex;
            align-items: center;
            gap: .3rem .7rem;
            flex-wrap: wrap;
            min-width: 0;
        }
        .cw-prog__count {
            font-size: .85rem;
            color: var(--m);
            font-weight: 500;
            white-space: nowrap;
        }
        .cw-prog__count b {
            color: var(--t);
            font-weight: 700;
            font-size: .95rem;
        }
        .cw-prog__sub {
            font-size: .745rem;
            color: var(--m2);
            display: inline-flex;
            align-items: center;
            gap: .3rem;
            white-space: nowrap;
        }
        .cw-prog__await {
            display: inline-flex;
            align-items: center;
            gap: .5rem;
            padding: .28rem .65rem .28rem .42rem;
            border-radius: 999px;
            background: var(--accent-softer);
            box-shadow: inset 0 0 0 1px var(--accent-ring);
        }
        .cw-prog__await-lb {
            font-size: .6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--accent-strong);
        }
        .cw-prog__await img {
            width: 1.5rem;
            height: 1.5rem;
            border-radius: 50%;
            object-fit: cover;
            box-shadow: 0 0 0 1.5px var(--accent);
        }
        .cw-prog__await-nm {
            font-size: .8rem;
            font-weight: 600;
            color: var(--t);
            white-space: nowrap;
        }

        /* Single continuous track + green fill — readable at a glance, no
           ambiguous per-step segments. */
        .cw-prog__track {
            height: .5rem;
            border-radius: 999px;
            background: var(--track);
            overflow: hidden;
        }
        .cw-prog__fill {
            display: block;
            height: 100%;
            border-radius: 999px;
            transition: width .3s ease;
            animation: cw-prog-grow .8s ease-out both;
        }
        @keyframes cw-prog-grow {
            from {
                width: 0 !important;
            }
        }
        @media (prefers-reduced-motion: reduce) {
            .cw-prog__fill {
                animation: none;
            }
        }
        .cw-prog__legend {
            display: flex;
            flex-wrap: wrap;
            gap: .35rem .9rem;
        }
        .cw-lg {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            font-size: .74rem;
            color: var(--m);
        }
        .cw-lg i {
            width: .5rem;
            height: .5rem;
            border-radius: 50%;
            flex-shrink: 0;
        }

        /* approval chain — vertical timeline */
        .cw-chain {
            padding: .9rem 1.25rem 1.1rem;
        }
        .cw-step {
            position: relative;
            display: flex;
            align-items: flex-start;
            gap: .9rem;
            padding: .6rem .65rem;
            border-radius: .85rem;
            transition: background .15s;
        }
        .cw-step:not(:last-child) {
            margin-bottom: .15rem;
        }
        .cw-step:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 2rem;
            top: 2.95rem;
            bottom: -.45rem;
            width: 2px;
            background: var(--track);
            border-radius: 2px;
        }
        .cw-step--approved:not(:last-child)::before {
            background: linear-gradient(#34d399, var(--track));
        }
        .cw-step--current {
            background: linear-gradient(90deg, var(--accent-soft), transparent 70%);
        }
        @keyframes cwPulse {
            0% {
                box-shadow: 0 0 0 0 rgba(99,102,241,.55);
            }
            70% {
                box-shadow: 0 0 0 8px rgba(99,102,241,0);
            }
            100% {
                box-shadow: 0 0 0 0 rgba(99,102,241,0);
            }
        }
        @media (prefers-reduced-motion: no-preference) {
            .cw-step--current .cw-badge--current {
                animation: cwPulse 1.8s ease-out infinite;
            }
        }
        .cw-step:hover {
            background: var(--soft);
        }
        .cw-node {
            position: relative;
            flex-shrink: 0;
        }
        .cw-node img {
            width: 2.75rem;
            height: 2.75rem;
            border-radius: 999px;
            object-fit: cover;
            display: block;
            box-shadow: 0 0 0 2px var(--s), 0 0 0 3px var(--track);
        }
        .cw-step--current .cw-node img {
            box-shadow: 0 0 0 2px var(--s), 0 0 0 3px var(--accent), 0 0 0 7px var(--accent-ring);
        }
        .cw-step--approved .cw-node img {
            box-shadow: 0 0 0 2px var(--s), 0 0 0 3px #34d399, 0 0 0 7px rgba(52,211,153,.15);
        }
        .cw-step--rejected .cw-node img {
            box-shadow: 0 0 0 2px var(--s), 0 0 0 3px #f87171, 0 0 0 7px rgba(248,113,113,.15);
        }

        /* Director = final sign-off: gold ring on the node, kept after the state
           rules so it wins. The state badge (check/clock) still shows on top. */
        .cw-step--director .cw-node img {
            box-shadow: 0 0 0 2px var(--s), 0 0 0 3px #f59e0b, 0 0 0 7px rgba(245,158,11,.18);
        }
        .cw-badge {
            position: absolute;
            bottom: -.25rem;
            right: -.25rem;
            width: 1.4rem;
            height: 1.4rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: 0 0 0 2px var(--s);
            color: #fff;
        }
        .cw-badge--approved {
            background: #10b981;
        }
        .cw-badge--rejected {
            background: #ef4444;
        }
        .cw-badge--returned {
            background: #3b82f6;
        }
        .cw-badge--current {
            background: var(--accent);
            color: var(--accent-on);
        }
        .cw-badge--queued {
            background: #cbd5e1;
            color: #64748b;
        }
        .dark .cw-badge--queued {
            background: #3f3f46;
            color: #a1a1aa;
        }
        .cw-step__bd {
            min-width: 0;
            flex: 1;
            padding-top: .15rem;
        }
        .cw-step__nm {
            font-size: 0.9rem;
            font-weight: 600;
            color: var(--t);
            display: flex;
            align-items: center;
            gap: .4rem;
        }
        .cw-ord {
            font-size: 0.664rem;
            font-weight: 700;
            color: var(--m2);
            background: var(--soft);
            border-radius: 999px;
            padding: .05rem .42rem;
        }
        .cw-director {
            display: inline-flex;
            align-items: center;
            gap: .22rem;
            font-size: .6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: #b45309;
            background: rgba(245,158,11,.15);
            padding: .12rem .42rem;
            border-radius: 999px;
            white-space: nowrap;
        }
        .dark .cw-director {
            color: #fcd34d;
            background: rgba(245,158,11,.22);
        }
        .cw-step__dp {
            font-size: 0.8rem;
            font-weight: 450;
            color: var(--m);
            margin-top: .15rem;
        }
        .cw-step__meta {
            display: flex;
            align-items: center;
            gap: .6rem;
            margin-top: .5rem;
            flex-wrap: wrap;
        }
        .cw-when {
            font-size: 0.724rem;
            color: var(--m2);
            display: inline-flex;
            align-items: center;
            gap: .25rem;
        }
        .cw-when--over {
            color: #dc2626;
            font-weight: 650;
        }
        .cw-when--soon {
            color: var(--accent);
            font-weight: 600;
        }
        .cw-cmt {
            margin-top: .55rem;
            padding: .45rem .7rem;
            border-radius: .55rem;
            background: var(--soft);
            font-size: 0.784rem;
            color: var(--m);
            border: 1px solid var(--d);
        }
        .cw-eye {
            flex-shrink: 0;
            width: 2rem;
            height: 2rem;
            border-radius: .6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            color: var(--m2);
            background: transparent;
            border: 1px solid var(--d);
            cursor: pointer;
            transition: all .15s;
        }
        .cw-eye:hover {
            color: var(--accent);
            border-color: var(--accent);
            background: var(--soft);
        }

        /* dropped-from-chain row — muted, still clickable for its history */
        .cw-step--ghost {
            opacity: .62;
        }
        .cw-step--ghost .cw-node img {
            filter: grayscale(.4);
            box-shadow: 0 0 0 2px var(--s), 0 0 0 3px var(--track);
        }
        .cw-step--ghost:hover {
            opacity: .85;
        }

        /* document */
        .cw-doc {
            display: flex;
            align-items: center;
            gap: 1rem;
            flex-wrap: wrap;
        }
        .cw-doc__ic {
            width: 3.25rem;
            height: 3.25rem;
            border-radius: .85rem;
            background: linear-gradient(135deg, var(--accent-ring), var(--accent-softer));
            color: var(--accent-strong);
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .dark .cw-doc__ic {
            color: var(--accent);
        }
        .cw-doc__nm {
            font-weight: 600;
            color: var(--t);
            font-size: 0.926rem;
        }
        .cw-doc__mt {
            font-size: 0.784rem;
            color: var(--m);
            margin-top: .15rem;
        }
        .cw-doc__act {
            margin-left: auto;
            display: flex;
            gap: .5rem;
            flex-wrap: wrap;
        }
        .cw-pdf {
            margin-top: 1.1rem;
            border: 1px solid var(--d);
            border-radius: .85rem;
            overflow: hidden;
        }
        .cw-pdf iframe {
            width: 100%;
            height: 64vh;
            min-height: 30rem;
            background: #fff;
            display: block;
            border: 0;
        }
        .cw-empty {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: .6rem;
            padding: 2.75rem 0;
            color: var(--m2);
        }
        .cw-empty span {
            font-size: 0.825rem;
        }

        /* details — table grid (label cell tinted, value cell) */
        .cw-dets {
            display: flex;
            flex-direction: column;
            border-top: 1px solid var(--d);
        }
        .cw-row {
            display: grid;
            grid-template-columns: minmax(10rem,15rem) 1fr;
            align-items: stretch;
            border-bottom: 1px solid var(--d);
        }
        /* Row atoms (icon, label, value, muted, :last-child) live in
           theme.css. cw keeps its tighter k/v padding + value transition. */
        .cw-row__k {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .85rem 1.25rem;
            background: var(--soft);
            border-right: 1px solid var(--d);
        }
        .cw-row__v {
            display: flex;
            align-items: center;
            padding: .85rem 1.25rem;
            min-width: 0;
            transition: background .12s ease;
        }
        button.cw-row {
            width: 100%;
            background: transparent;
            border: 0;
            border-bottom: 1px solid var(--d);
            cursor: pointer;
            text-align: left;
            font: inherit;
            padding: 0;
        }
        .cw-row--link:hover .cw-row__v {
            background: var(--accent-softer);
        }
        .cw-row--link .cw-row__vl {
            color: var(--accent);
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }
        .cw-show-more {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: .4rem;
            width: 100%;
            padding: .85rem 1.5rem;
            background: transparent;
            border: 0;
            border-top: 1px solid var(--d);
            color: var(--accent);
            font-size: .815rem;
            font-weight: 600;
            cursor: pointer;
            transition: background .12s ease;
        }
        .cw-show-more:hover {
            background: var(--accent-softer);
        }
        .cw-show-more > span {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
        }

        /* combined card — file block */
        .cw-file {
            display: flex;
            align-items: flex-start;
            gap: 1.1rem;
            padding: 1.25rem 1.5rem .5rem;
        }
        .cw-file__thumb {
            position: relative;
            width: 5rem;
            height: 6rem;
            flex-shrink: 0;
            background: var(--accent-softer);
            border-radius: .7rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 0 1px var(--accent-soft);
        }
        .cw-file__thumb svg {
            width: 60%;
            height: auto;
        }
        .cw-file__ext {
            position: absolute;
            left: .45rem;
            bottom: .55rem;
            padding: .16rem .42rem;
            border-radius: .3rem;
            background: var(--accent);
            color: #fff;
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: .04em;
            line-height: 1;
        }
        .cw-file__body {
            display: flex;
            flex-direction: column;
            gap: .6rem;
            min-width: 0;
            flex: 1;
            padding-top: .15rem;
        }
        .cw-file__field {
            display: flex;
            flex-direction: column;
            gap: .15rem;
            min-width: 0;
        }
        .cw-file__lb {
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: var(--m2);
        }
        .cw-file__vl {
            font-size: .86rem;
            font-weight: 500;
            color: var(--t);
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cw-file__act {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
            padding: .75rem 1.5rem 1.2rem;
        }
        .cw-btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .5rem .85rem;
            border-radius: .5rem;
            font-size: .81rem;
            font-weight: 600;
            text-decoration: none;
            transition: opacity .12s ease, background .12s ease, box-shadow .12s ease;
        }
        .cw-btn--primary {
            background: var(--accent);
            color: #fff;
        }
        .cw-btn--primary:hover {
            opacity: .88;
        }
        .cw-btn--ghost {
            background: transparent;
            color: var(--t);
            box-shadow: inset 0 0 0 1px var(--d);
        }
        .cw-btn--ghost:hover {
            background: var(--soft);
        }
        .cw-divider {
            height: 1px;
            background: var(--d);
            margin: 0 1.5rem;
        }
        .cw-contact-group {
            display: flex;
            flex-direction: column;
            gap: .1rem;
        }
        .cw-contact-group + .cw-contact-group {
            margin-top: 1.1rem;
            padding-top: .9rem;
            border-top: 1px solid var(--d);
        }
        .cw-contact-group__t {
            font-size: .7rem;
            font-weight: 700;
            color: var(--m2);
            text-transform: uppercase;
            letter-spacing: .06em;
            padding: 0 .25rem .35rem;
        }
        .cw-contact-rows {
            display: flex;
            flex-direction: column;
        }
        .cw-crow {
            display: flex;
            align-items: center;
            gap: .7rem;
            padding: .55rem .25rem;
        }
        .cw-crow + .cw-crow {
            border-top: 1px dashed var(--d);
        }
        .cw-crow__ic {
            color: var(--m2);
            display: inline-flex;
            flex-shrink: 0;
        }
        .cw-crow__lb {
            font-size: 0.8125rem;
            color: var(--m);
            flex: 1;
        }
        .cw-crow__vl {
            font-size: 0.8125rem;
            font-weight: 600;
            color: var(--t);
            max-width: 62%;
            text-align: right;
        }

        /* execution timeline */
        .cw-filters {
            display: flex;
            gap: .3rem;
            padding: .95rem 1.25rem;
            border-bottom: 1px solid var(--d);
            flex-wrap: wrap;
        }
        .cw-chip {
            display: inline-flex;
            align-items: center;
            gap: .35rem;
            padding: .34rem .7rem;
            font-size: 0.744rem;
            font-weight: 600;
            color: var(--m);
            background: var(--soft);
            border: 0;
            border-radius: 999px;
            cursor: pointer;
            transition: all .15s;
        }
        .cw-chip:hover {
            color: var(--t);
        }
        .cw-chip--active {
            background: var(--accent-soft);
            color: var(--accent);
        }
        .cw-chip__c {
            font-size: 0.664rem;
            font-weight: 700;
            padding: .02rem .35rem;
            border-radius: 999px;
            background: rgba(0,0,0,.07);
        }
        .dark .cw-chip__c {
            background: rgba(255,255,255,.10);
        }
        .cw-loadmore {
            width: 100%;
            padding: .7rem;
            margin-top: .5rem;
            font-size: 0.805rem;
            font-weight: 600;
            color: var(--accent);
            background: var(--soft);
            border: 1px dashed var(--d);
            border-radius: .7rem;
            cursor: pointer;
            transition: all .15s;
        }
        .cw-loadmore:hover {
            background: var(--accent-softer);
            border-style: solid;
        }
        .cw-day__hd {
            display: inline-flex;
            align-items: center;
            font-size: 0.75rem;
            font-weight: 700;
            color: var(--m);
            text-transform: uppercase;
            letter-spacing: .05em;
            padding: .3rem .7rem;
            margin: .15rem 0 .75rem;
            background: var(--soft);
            border-left: 3px solid var(--accent);
            border-radius: .35rem;
        }
        .cw-day + .cw-day {
            margin-top: .5rem;
        }
        .cw-tl {
            position: relative;
            display: flex;
            gap: .85rem;
            padding-bottom: 2.1rem;
        }
        .cw-tl__time {
            width: 2.6rem;
            flex-shrink: 0;
            text-align: right;
            font-size: 0.76rem;
            color: var(--m2);
            padding-top: .5rem;
            font-variant-numeric: tabular-nums;
        }
        .cw-tl:last-child {
            padding-bottom: 0;
        }
        .cw-tl:not(:last-child)::before {
            content: '';
            position: absolute;
            left: 4.5rem;
            top: 2.3rem;
            bottom: -.2rem;
            width: 2px;
            background: var(--track);
            border-radius: 2px;
        }
        .cw-tl__ic {
            width: 2.1rem;
            height: 2.1rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
            box-shadow: 0 0 0 3px var(--s);
        }
        .cw-tl__ic--success {
            background: #d1fae5;
            color: #059669;
        }
        .dark .cw-tl__ic--success {
            background: rgba(16,185,129,.18);
        }
        .cw-tl__ic--danger {
            background: #fee2e2;
            color: #dc2626;
        }
        .dark .cw-tl__ic--danger {
            background: rgba(239,68,68,.18);
        }
        .cw-tl__ic--warning {
            background: #ffedd5;
            color: #c2410c;
        }
        .dark .cw-tl__ic--warning {
            background: rgba(251,146,60,.18);
        }
        .cw-tl__ic--info {
            background: #dbeafe;
            color: #2563eb;
        }
        .dark .cw-tl__ic--info {
            background: rgba(59,130,246,.18);
        }
        .cw-tl__ic--gray {
            background: #f1f5f9;
            color: #64748b;
        }
        .dark .cw-tl__ic--gray {
            background: rgba(255,255,255,.07);
        }
        .cw-tl__bd {
            min-width: 0;
            padding-top: .15rem;
        }
        .cw-tl__ds {
            font-size: 0.92rem;
            font-weight: 600;
            color: var(--t);
            line-height: 1.35;
        }
        .cw-tl__mt {
            font-size: 0.8rem;
            color: var(--m2);
            margin-top: .2rem;
            display: flex;
            align-items: center;
            gap: .4rem;
            flex-wrap: wrap;
        }
        .cw-tl__dot {
            width: 3px;
            height: 3px;
            border-radius: 999px;
            background: var(--m2);
            display: inline-block;
        }

        /* modal */
        .cw-modal {
            position: fixed;
            inset: 0;
            z-index: 60;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
        }
        .cw-modal__bg {
            position: absolute;
            inset: 0;
            background: rgba(15,23,42,.55);
            backdrop-filter: blur(3px);
        }
        .cw-modal__card {
            position: relative;
            width: 100%;
            max-width: 30rem;
            max-height: 86vh;
            overflow: auto;
            background: var(--s);
            border-radius: 1.1rem;
            box-shadow: 0 20px 25px rgba(15,20,25,.18), 0 10px 10px rgba(15,20,25,.08);
        }
        .cw-modal__hd {
            display: flex;
            align-items: center;
            gap: .85rem;
            padding: 1.25rem 1.25rem 1rem;
            border-bottom: 1px solid var(--d);
        }
        .cw-modal__hd img {
            width: 3rem;
            height: 3rem;
            border-radius: 999px;
            object-fit: cover;
            box-shadow: 0 0 0 2px var(--track);
        }
        .cw-modal__nm {
            font-size: 1.01rem;
            font-weight: 700;
            color: var(--t);
        }
        .cw-modal__dp {
            font-size: 0.805rem;
            color: var(--m);
            margin-top: .1rem;
        }
        .cw-modal__x {
            margin-left: auto;
            width: 2rem;
            height: 2rem;
            border-radius: .6rem;
            border: 1px solid var(--d);
            background: transparent;
            color: var(--m);
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .cw-modal__x:hover {
            background: var(--soft);
            color: var(--t);
        }
        .cw-modal__bd {
            padding: 1.1rem 1.25rem 1.35rem;
        }
        .cw-kv {
            display: flex;
            align-items: center;
            gap: .6rem;
            padding: .5rem 0;
        }
        .cw-kv__lb {
            font-size: 0.784rem;
            color: var(--m);
            width: 35%;
            flex-shrink: 0;
        }
        .cw-kv__vl {
            font-size: 0.854rem;
            font-weight: 600;
            color: var(--t);
        }
        .cw-sub {
            display: flex;
            align-items: center;
            justify-content: space-between;
            font-size: 0.724rem;
            font-weight: 650;
            color: var(--m2);
            text-transform: uppercase;
            letter-spacing: .04em;
            margin: 1.1rem 0 .5rem;
        }
        .cw-sub__c {
            font-size: 0.684rem;
            font-weight: 700;
            padding: .05rem .4rem;
            border-radius: 999px;
            background: var(--soft);
            color: var(--m);
            text-transform: none;
            letter-spacing: 0;
        }
        .cw-act {
            border: 1px solid var(--d);
            border-radius: .7rem;
            overflow: hidden;
        }
        .cw-act__row {
            display: grid;
            grid-template-columns: 2.4rem 1.75rem 1fr auto;
            align-items: center;
            gap: .65rem;
            padding: .55rem .75rem;
        }
        .cw-act__row + .cw-act__row {
            border-top: 1px solid var(--d);
        }
        .cw-act__row:nth-child(odd) {
            background: var(--soft);
        }
        .cw-act__time {
            font-size: 0.7rem;
            color: var(--m2);
            font-variant-numeric: tabular-nums;
        }
        .cw-act__ic {
            width: 1.7rem;
            height: 1.7rem;
            border-radius: 999px;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .cw-act__ic--success {
            background: #d1fae5;
            color: #059669;
        }
        .dark .cw-act__ic--success {
            background: rgba(16,185,129,.18);
        }
        .cw-act__ic--danger {
            background: #fee2e2;
            color: #dc2626;
        }
        .dark .cw-act__ic--danger {
            background: rgba(239,68,68,.18);
        }
        .cw-act__ic--warning {
            background: #ffedd5;
            color: #c2410c;
        }
        .dark .cw-act__ic--warning {
            background: rgba(251,146,60,.18);
        }
        .cw-act__ic--info {
            background: #dbeafe;
            color: #2563eb;
        }
        .dark .cw-act__ic--info {
            background: rgba(59,130,246,.18);
        }
        .cw-act__ic--gray {
            background: #f1f5f9;
            color: #64748b;
        }
        .dark .cw-act__ic--gray {
            background: rgba(255,255,255,.07);
        }
        .cw-act__ds {
            font-size: 0.805rem;
            font-weight: 600;
            color: var(--t);
            min-width: 0;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .cw-act__rel {
            font-size: 0.7rem;
            color: var(--m2);
            white-space: nowrap;
        }
        .cw-act__toggle {
            width: 100%;
            padding: .55rem;
            font-size: 0.74rem;
            font-weight: 600;
            color: var(--accent);
            background: var(--soft);
            border: 0;
            border-top: 1px solid var(--d);
            cursor: pointer;
            transition: background .15s;
        }
        .cw-act__toggle:hover {
            background: var(--accent-softer);
        }
        .cw-modal__empty {
            font-size: 0.825rem;
            color: var(--m2);
            padding: .4rem 0;
        }

        /* Per-record table inside the eye-modal — every row this user has on
           the contract (current + cancelled attempts), newest first. Mirrors
           the chain modal's table, scoped to one person. */
        .cw-rt {
            width: 100%;
            border-collapse: collapse;
            font-size: .86rem;
        }
        .cw-rt thead th {
            text-align: left;
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .04em;
            color: var(--m2);
            padding: .5rem .6rem;
            border-bottom: 1px solid var(--d);
            white-space: nowrap;
        }
        .cw-rt tbody td {
            padding: .7rem .6rem;
            border-bottom: 1px solid var(--d);
            vertical-align: top;
        }
        .cw-rt tbody tr:last-child td {
            border-bottom: 0;
        }
        .cw-rt tbody tr.is-past td {
            opacity: .62;
        }
        .cw-rt__st {
            white-space: nowrap;
        }
        .cw-rt__st .cw-pill {
            font-size: .78rem;
        }
        .cw-rt__ord {
            display: block;
            margin-top: .3rem;
            font-size: .68rem;
            font-weight: 600;
            color: var(--m2);
            font-variant-numeric: tabular-nums;
        }
        .cw-rt__tag {
            display: inline-flex;
            align-items: center;
            gap: .2rem;
            margin-top: .3rem;
            font-size: .64rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .03em;
            color: #c2410c;
        }
        .cw-rt__overdue {
            display: inline-flex;
            align-items: center;
            gap: .2rem;
            margin-top: .3rem;
            font-size: .66rem;
            font-weight: 700;
            color: #dc2626;
        }
        .cw-rt__cmt {
            line-height: 1.42;
            font-size: .84rem;
            color: var(--t);
        }
        .cw-rt__cmt--muted {
            color: var(--m2);
            font-style: italic;
        }
        .cw-rt__sys {
            margin-top: .35rem;
            padding: .35rem .5rem;
            border-radius: .4rem;
            background: rgba(251,146,60,.10);
            border: 1px solid rgba(251,146,60,.30);
            font-size: .74rem;
            color: #c2410c;
            line-height: 1.38;
        }
        .cw-rt__sys-lb {
            font-weight: 700;
            margin-right: .3rem;
        }
        .cw-rt__date {
            font-size: .8rem;
            color: var(--m);
            white-space: nowrap;
            font-variant-numeric: tabular-nums;
        }
        .cw-rt__date small {
            display: block;
            opacity: .6;
            font-size: .72rem;
            margin-top: .1rem;
        }
        .cw-rt__date--muted {
            opacity: .4;
        }
        /* status colour bar at the very top of the eye-modal card */
        .cw-modal__bar {
            height: .3rem;
            width: 100%;
            flex-shrink: 0;
        }
        .cw-modal__hd-pill {
            margin-left: auto;
            display: flex;
            align-items: center;
            gap: .5rem;
        }

        /* metric tiles strip (step / timing / reminders) */
        .cw-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(8.5rem, 1fr));
            gap: .6rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--d);
            background: var(--soft);
        }
        .cw-stat {
            border: 1px solid var(--d);
            border-radius: .65rem;
            padding: .6rem .7rem;
            background: var(--s);
        }
        .cw-stat__lb {
            font-size: .72rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: .05em;
            color: var(--m2);
            display: inline-flex;
            align-items: center;
            gap: .35rem;
        }
        .cw-stat__vl {
            margin-top: .3rem;
            font-size: 1.02rem;
            font-weight: 600;
            color: var(--t);
            line-height: 1.2;
            font-variant-numeric: tabular-nums;
        }
        .cw-stat__sub {
            margin-top: .2rem;
            font-size: .78rem;
            font-weight: 500;
            color: var(--m);
        }
        .cw-stat--success {
            background: #ecfdf5;
            border-color: #a7f3d0;
        }
        .cw-stat--success .cw-stat__vl {
            color: #047857;
        }
        .dark .cw-stat--success {
            background: rgba(16,185,129,.10);
            border-color: rgba(16,185,129,.30);
        }
        .dark .cw-stat--success .cw-stat__vl {
            color: #6ee7b7;
        }
        .cw-stat--warning {
            background: #fff7ed;
            border-color: #fed7aa;
        }
        .cw-stat--warning .cw-stat__vl {
            color: #c2410c;
        }
        .dark .cw-stat--warning {
            background: rgba(251,146,60,.10);
            border-color: rgba(251,146,60,.30);
        }
        .dark .cw-stat--warning .cw-stat__vl {
            color: #fdba74;
        }
        .cw-stat--danger {
            background: #fef2f2;
            border-color: #fecaca;
        }
        .cw-stat--danger .cw-stat__vl {
            color: #b91c1c;
        }
        .dark .cw-stat--danger {
            background: rgba(239,68,68,.10);
            border-color: rgba(239,68,68,.30);
        }
        .dark .cw-stat--danger .cw-stat__vl {
            color: #fca5a5;
        }
        .cw-stat--primary {
            background: var(--accent-softer);
            border-color: var(--accent-ring);
        }
        .cw-stat--primary .cw-stat__vl {
            color: var(--accent-strong);
        }

        /* coloured left accent per record row, set inline by status */
        .cw-rt tbody td:first-child {
            border-left: 3px solid var(--row-accent, transparent);
        }

        /* ─── Mobile (< 640px) ─────────────────────────────────────────── */
        @media (max-width: 640px) {
            /* Basic-information table keeps its two columns and scrolls
               sideways (like the index tables) instead of squeezing values. */
            .cw-dets {
                overflow-x: auto;
            }
            .cw-row {
                min-width: 34rem;
                grid-template-columns: 12rem 1fr;
            }

            /* Approval-chain step: let the name/role/pill wrap instead of
               overflowing, and pull the eye button up to the top. */
            .cw-step {
                gap: .7rem;
            }
            .cw-step__nm {
                flex-wrap: wrap;
            }

            /* Per-approver modal: give it the screen, stack the metric tiles,
               wrap the header, and turn the record table into stacked blocks. */
            .cw-modal {
                padding: .5rem;
            }
            .cw-modal__card {
                max-height: 92vh;
            }
            .cw-modal__hd {
                flex-wrap: wrap;
                padding: 1rem 3rem 1rem 1rem;
            }
            .cw-modal__hd-pill {
                margin-left: 0;
            }
            /* keep the close button pinned to the top-right corner instead of
               dropping it onto its own row */
            .cw-modal__x {
                position: absolute;
                top: .9rem;
                right: .9rem;
                margin-left: 0;
            }
            .cw-stats {
                grid-template-columns: 1fr;
                padding: .85rem 1rem;
            }
            .cw-modal__bd {
                padding: .9rem 1rem 1.2rem;
            }
            .cw-rt thead {
                display: none;
            }
            .cw-rt tbody tr {
                display: block;
                padding: .35rem 0;
                border-bottom: 1px solid var(--d);
            }
            .cw-rt tbody tr:last-child {
                border-bottom: 0;
            }
            .cw-rt tbody td {
                display: block;
                border-bottom: 0;
                padding: .2rem .6rem;
            }
            .cw-rt tbody td:first-child {
                padding-top: .6rem;
            }
        }
    </style>
