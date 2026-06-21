@php
    /** @var \App\Models\Order $record */
    $record = $getRecord();
    $name = basename((string) $record->file_path);
    $ext = strtoupper((string) $record->extension());

    $size = null;
    if ($record->fileExists()) {
        $bytes = filesize($record->fileAbsolutePath());
        if ($bytes !== false) {
            $size = $bytes >= 1024 * 1024
                ? number_format($bytes / 1024 / 1024, 2, ',', ' ').' MB'
                : number_format($bytes / 1024, 0, ',', ' ').' KB';
        }
    }

    $openUrl = $record->isOpenableInOnlyOffice()
        ? route('orders.editor', ['order' => $record, 'mode' => 'view'])
        : (in_array(strtolower((string) $record->extension()), ['pdf'], true)
            ? route('orders.file.inline', ['order' => $record])
            : null);
    $editUrl = $record->isOpenableInOnlyOffice()
        ? route('orders.editor', ['order' => $record, 'mode' => 'edit'])
        : null;
@endphp

<div class="of">
    <div class="of-card">
        {{-- Stylized document thumbnail --}}
        <div class="of-thumb" aria-hidden="true">
            <svg viewBox="0 0 64 80" xmlns="http://www.w3.org/2000/svg">
                <path d="M8 4 h36 l12 12 v60 H8 Z" fill="#fff" stroke="#cbd5e1" stroke-width="1.5"/>
                <path d="M44 4 v12 h12" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1.5"/>
                <rect x="14" y="30" width="34" height="2" rx="1" fill="#e2e8f0"/>
                <rect x="14" y="36" width="28" height="2" rx="1" fill="#e2e8f0"/>
                <rect x="14" y="42" width="34" height="2" rx="1" fill="#e2e8f0"/>
                <rect x="14" y="48" width="22" height="2" rx="1" fill="#e2e8f0"/>
            </svg>
            <span class="of-thumb__ext">{{ $ext ?: '—' }}</span>
        </div>

        {{-- Metadata --}}
        <div class="of-meta">
            <div class="of-meta__row">
                <div class="of-meta__lb">{{ __('app.label.file_name') }}</div>
                <div class="of-meta__vl" title="{{ $name }}">{{ $name }}</div>
            </div>
            <div class="of-meta__row">
                <div class="of-meta__lb">{{ __('app.label.size') }}</div>
                <div class="of-meta__vl">{{ $size ?? '—' }}</div>
            </div>
            <div class="of-meta__row">
                <div class="of-meta__lb">{{ __('app.label.created_at') }}</div>
                <div class="of-meta__vl">{{ $record->created_at?->translatedFormat('d M Y H:i') ?? '—' }}</div>
            </div>

            {{-- Actions --}}
            <div class="of-act">
                @if ($editUrl)
                    <x-filament::button tag="a" :href="$editUrl" icon="heroicon-o-pencil-square" color="primary" size="sm">
                        {{ __('app.action.open_editor') }}
                    </x-filament::button>
                @endif
                @if ($openUrl)
                    <x-filament::button tag="a" :href="$openUrl" icon="heroicon-o-arrow-top-right-on-square" color="gray" size="sm" target="_blank">
                        {{ __('app.action.open_in_new_tab') }}
                    </x-filament::button>
                @endif
            </div>
        </div>
    </div>
</div>

<style>
    .of {
        --s: #fff;
        --r: rgba(15,20,25,.08);
        --t: #0f1419;
        --m: #57606a;
        --m2: #8b949e;
        --d: rgba(15,20,25,.07);
        --soft: #f8fafc;
        --accent: #6366f1;
    }
    .dark .of {
        --s: #18181b;
        --r: rgba(255,255,255,.08);
        --t: #f0f6fc;
        --m: #9aa4b2;
        --m2: #6e7681;
        --d: rgba(255,255,255,.07);
        --soft: rgba(255,255,255,.03);
        --accent: #818cf8;
    }
    .of-card {
        display: flex;
        gap: 1.5rem;
        align-items: flex-start;
        background: var(--s);
        border: 1px solid var(--d);
        border-radius: 1rem;
        padding: 1.25rem 1.5rem;
        flex-wrap: wrap;
    }
    .of-thumb {
        position: relative;
        flex-shrink: 0;
        width: 8rem;
        height: 10rem;
        display: flex;
        align-items: center;
        justify-content: center;
        background: linear-gradient(180deg,#f8fafc,#eef2ff);
        border-radius: .75rem;
    }
    .dark .of-thumb {
        background: linear-gradient(180deg, rgba(255,255,255,.04), rgba(99,102,241,.10));
    }
    .of-thumb svg {
        width: 5rem;
        height: auto;
    }
    .of-thumb__ext {
        position: absolute;
        bottom: 1.65rem;
        left: 50%;
        transform: translateX(-50%);
        font-size: .7rem;
        font-weight: 700;
        letter-spacing: .05em;
        color: #fff;
        background: var(--accent);
        padding: .15rem .55rem;
        border-radius: .3rem;
    }
    .of-meta {
        flex: 1;
        min-width: 16rem;
        display: flex;
        flex-direction: column;
        gap: .85rem;
    }
    .of-meta__row {
        display: flex;
        flex-direction: column;
        gap: .15rem;
    }
    .of-meta__lb {
        font-size: .7rem;
        font-weight: 600;
        color: var(--m);
        text-transform: uppercase;
        letter-spacing: .04em;
    }
    .of-meta__vl {
        font-size: .95rem;
        font-weight: 600;
        color: var(--t);
        overflow: hidden;
        text-overflow: ellipsis;
        white-space: nowrap;
    }
    .of-act {
        display: flex;
        gap: .5rem;
        flex-wrap: wrap;
        margin-top: .5rem;
    }

    /* Mobile (< 640px): shrink the thumb and drop fixed widths */
    @media (max-width: 640px) {
        .of-thumb {
            width: 5.5rem;
            height: 7rem;
        }
        .of-thumb svg {
            width: 3.5rem;
        }
        .of-meta {
            min-width: 0;
        }
    }
</style>
