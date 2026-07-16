@php
    $groups = [
        __('app.label.placeholder_group_contract') => [
            '{{number}}', '{{title}}', '{{amount}}', '{{currency}}',
        ],
        __('app.label.placeholder_group_date') => [
            '{{date.day}}', '{{date.month}}', '{{date.year}}', '{{date.full}}',
        ],
        __('app.label.placeholder_group_contact') => [
            '{{contact.name}}', '{{contact.legal_form}}', '{{contact.director}}',
            '{{contact.inn}}', '{{contact.pinfl}}', '{{contact.oked}}',
            '{{contact.address}}', '{{contact.phone}}', '{{contact.email}}',
        ],
        __('app.label.placeholder_group_bank') => [
            '{{contact.bank_account}}', '{{contact.bank_name}}', '{{contact.bank_address}}',
            '{{contact.mfo}}', '{{contact.swift}}',
        ],
    ];
@endphp

<div
    x-data="{
        copied: null,
        copy(value) {
            navigator.clipboard.writeText(value).then(() => {
                this.copied = value;
                setTimeout(() => { if (this.copied === value) this.copied = null; }, 1200);
            });
        },
    }"
    class="ph-wrap"
>
    @foreach ($groups as $title => $items)
        <div class="ph-group">
            <div class="ph-title">{{ $title }}</div>
            <div class="ph-chips">
                @foreach ($items as $item)
                    <button
                        type="button"
                        @click="copy('{{ $item }}')"
                        :class="copied === '{{ $item }}' ? 'ph-chip ph-chip--copied' : 'ph-chip'"
                        :title="copied === '{{ $item }}' ? '{{ __('app.message.copied') }}' : '{{ __('app.message.click_to_copy') }}'"
                    >
                        <span class="ph-code">{{ $item }}</span>
                        <svg x-show="copied !== '{{ $item }}'" class="ph-ic" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <svg x-show="copied === '{{ $item }}'" x-cloak class="ph-ic ph-ic--ok" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"/></svg>
                    </button>
                @endforeach
            </div>
        </div>
    @endforeach
</div>

<style>
    .ph-wrap {
        display: flex;
        flex-direction: column;
        gap: 1rem;
    }
    .ph-title {
        font-size: .72rem;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: .04em;
        color: rgba(100, 116, 139, 1);
        margin-bottom: .45rem;
    }
    .ph-chips {
        display: flex;
        flex-wrap: wrap;
        gap: .4rem;
    }
    .ph-chip {
        display: inline-flex;
        align-items: center;
        gap: .4rem;
        padding: .32rem .58rem;
        border-radius: .42rem;
        background: rgba(127, 127, 127, .10);
        border: 1px solid rgba(127, 127, 127, .15);
        color: inherit;
        font-family: ui-monospace, SFMono-Regular, Menlo, monospace;
        font-size: .8rem;
        line-height: 1;
        cursor: pointer;
        transition: background .15s ease, border-color .15s ease, transform .1s ease;
    }
    .ph-chip:hover {
        background: rgba(127, 127, 127, .18);
        border-color: rgba(127, 127, 127, .28);
    }
    .ph-chip:active {
        transform: translateY(1px);
    }
    .ph-chip--copied {
        background: rgba(34, 197, 94, .14);
        border-color: rgba(34, 197, 94, .42);
        color: rgb(21, 128, 61);
    }
    .ph-ic {
        width: 13px;
        height: 13px;
        opacity: .55;
    }
    .ph-ic--ok {
        opacity: 1;
        color: rgb(34, 197, 94);
    }
    .dark .ph-title {
        color: rgba(148, 163, 184, 1);
    }
    .dark .ph-chip--copied {
        color: rgb(134, 239, 172);
    }
</style>
