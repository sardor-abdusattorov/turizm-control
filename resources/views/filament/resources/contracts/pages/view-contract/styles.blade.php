    <style>
        [x-cloak] {
            display: none !important;
        }
        .cw {
            display: flex;
            flex-direction: column;
            gap: 1.5rem;
            --track: #e6ebf1;
            --accent-soft: rgba(37,99,235,.12);
            --accent-softer: rgba(37,99,235,.05);
            --accent-ring: rgba(37,99,235,.18);
            --accent-on: #fff;
        }
        .dark .cw {
            --track: rgba(255,255,255,.10);
            --accent-strong: #a5b4fc;
            --accent-soft: rgba(129,140,248,.16);
            --accent-softer: rgba(129,140,248,.08);
            --accent-ring: rgba(129,140,248,.25);
        }
        .cw-card {
            background: var(--s);
            border: 1px solid var(--d);
            border-radius: .75rem;
            overflow: hidden;
        }
        .cw-hd {
            display: flex;
            align-items: center;
            gap: .65rem;
            padding: 1rem 1.25rem;
            border-bottom: 1px solid var(--d);
        }
        .cw-hd__ic {
            color: var(--m2);
            display: inline-flex;
        }
        .cw-hd__t {
            font-size: .9375rem;
            font-weight: 600;
            color: var(--t);
            margin: 0;
            flex: 1;
        }

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
        .cw-row__k {
            display: flex;
            align-items: center;
            gap: .55rem;
            padding: .8rem 1.25rem;
            background: var(--soft);
            border-right: 1px solid var(--d);
        }
        .cw-row__v {
            display: flex;
            align-items: center;
            padding: .8rem 1.25rem;
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
        .dark .cw-file__ext {
            background: #64748b;
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
        .dark .cw-tl__ic--success {
            background: rgba(16,185,129,.18);
        }
        .dark .cw-tl__ic--danger {
            background: rgba(239,68,68,.18);
        }
        .dark .cw-tl__ic--warning {
            background: rgba(251,146,60,.18);
        }
        .dark .cw-tl__ic--info {
            background: rgba(59,130,246,.18);
        }
        .dark .cw-tl__ic--gray {
            background: rgba(255,255,255,.07);
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

        .cw-rt-wrap {
            overflow-x: auto;
            min-width: 0;
            max-width: 100%;
        }
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

        .cw-rt tbody td:first-child {
            border-left: 3px solid var(--row-accent, transparent);
        }

        @media (max-width: 640px) {
            .cw-dets {
                overflow-x: auto;
            }
            .cw-row {
                min-width: 34rem;
                grid-template-columns: 12rem 1fr;
            }

            .cw-stats {
                grid-template-columns: 1fr;
                padding: .85rem 1rem;
            }
        }
    </style>
