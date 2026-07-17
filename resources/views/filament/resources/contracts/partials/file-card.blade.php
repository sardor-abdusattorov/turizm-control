@php
    use Illuminate\Support\Facades\Storage;

    /** @var \App\Models\Contract $record */
    $record = $getRecord();

    if (! $record || ! $record->documentExists()) {
        return;
    }

    $bytes = Storage::disk('local')->size($record->documentPath());
    $size = $bytes >= 1024 * 1024
        ? number_format($bytes / 1024 / 1024, 2, '.', ' ').' MB'
        : number_format($bytes / 1024, 1, '.', ' ').' KB';

    $createdLabel = $record->created_at?->translatedFormat('d M Y H:i');
    $canEdit = $record->canBeEditedBy();
    $editUrl = route('contracts.editor', ['contract' => $record, 'mode' => $canEdit ? 'edit' : 'view']);

    // Opening the editor mid-approval lets the user change the document, which
    // resets the whole chain once they close it — warn them first.
    $warnReset = $canEdit && $record->documentEditWouldResetApprovals();
    $previewUrl = $record->documentExists() && $record->status === \App\Models\Contract::STATUS_APPROVED
        ? route('contracts.pdf.inline', ['contract' => $record])
        : null;

    $ic = fn (string $name, int $size = 16) => svg($name, '', ['width' => $size, 'height' => $size])->toHtml();
@endphp

<div class="cf-file">
    <div class="cf-file__row">
        <div class="cf-file__thumb" aria-hidden="true">
            <svg viewBox="0 0 64 80" xmlns="http://www.w3.org/2000/svg">
                <path d="M8 4 h36 l12 12 v60 H8 Z" fill="#fff" stroke="#cbd5e1" stroke-width="1.5"/>
                <path d="M44 4 v12 h12" fill="#f1f5f9" stroke="#cbd5e1" stroke-width="1.5"/>
                <rect x="14" y="30" width="34" height="2" rx="1" fill="#e2e8f0"/>
                <rect x="14" y="36" width="28" height="2" rx="1" fill="#e2e8f0"/>
                <rect x="14" y="42" width="34" height="2" rx="1" fill="#e2e8f0"/>
                <rect x="14" y="48" width="22" height="2" rx="1" fill="#e2e8f0"/>
            </svg>
            <span class="cf-file__ext">DOCX</span>
        </div>
        <div class="cf-file__body">
            <div class="cf-file__field">
                <div class="cf-file__lb">{{ __('app.label.file_name') }}</div>
                <div class="cf-file__vl">{{ $record->number }}.docx</div>
            </div>
            <div class="cf-file__field">
                <div class="cf-file__lb">{{ __('app.label.size') }}</div>
                <div class="cf-file__vl">{{ $size }}</div>
            </div>
            @if ($createdLabel)
                <div class="cf-file__field">
                    <div class="cf-file__lb">{{ __('app.label.created_at') }}</div>
                    <div class="cf-file__vl">{{ $createdLabel }}</div>
                </div>
            @endif
        </div>
    </div>
    <div class="cf-file__act">
        <a href="{{ $editUrl }}" class="cf-btn cf-btn--primary"
            @if ($warnReset) onclick="return confirm(@js(__('app.message.editor_reset_warning')))" @endif
        >
            {!! $ic('heroicon-o-pencil-square', 16) !!}
            <span>{{ __('app.action.open_editor') }}</span>
        </a>
        @if ($previewUrl)
            <a href="{{ $previewUrl }}" target="_blank" rel="noopener" class="cf-btn cf-btn--ghost">
                {!! $ic('heroicon-o-arrow-top-right-on-square', 16) !!}
                <span>{{ __('app.action.open_in_new_tab') }}</span>
            </a>
        @endif
    </div>
</div>

@once
    <style>
        .cf-file {
            display: flex;
            flex-direction: column;
            gap: 1rem;
            padding: 1.1rem 1.25rem;
            border-radius: .75rem;
            background: rgba(37,99,235,.04);
            border: 1px solid rgba(37,99,235,.18);
        }
        .dark .cf-file {
            background: rgba(37,99,235,.07);
            border-color: rgba(37,99,235,.22);
        }
        .cf-file__row {
            display: flex;
            align-items: flex-start;
            gap: 1.1rem;
        }
        .cf-file__thumb {
            position: relative;
            width: 5rem;
            height: 6rem;
            flex-shrink: 0;
            background: #fff;
            border-radius: .6rem;
            display: flex;
            align-items: center;
            justify-content: center;
            box-shadow: inset 0 0 0 1px rgba(37,99,235,.10);
        }
        .dark .cf-file__thumb {
            background: rgba(255,255,255,.06);
            box-shadow: inset 0 0 0 1px rgba(37,99,235,.15);
        }
        .cf-file__thumb svg {
            width: 60%;
            height: auto;
        }
        .cf-file__ext {
            position: absolute;
            left: .45rem;
            bottom: .55rem;
            padding: .16rem .42rem;
            border-radius: .3rem;
            background: #2563eb;
            color: #fff;
            font-size: .6rem;
            font-weight: 700;
            letter-spacing: .04em;
            line-height: 1;
        }
        .cf-file__body {
            display: flex;
            flex-direction: column;
            gap: .6rem;
            min-width: 0;
            flex: 1;
            padding-top: .15rem;
        }
        .cf-file__field {
            display: flex;
            flex-direction: column;
            gap: .15rem;
            min-width: 0;
        }
        .cf-file__lb {
            font-size: .62rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: .06em;
            color: #94a3b8;
        }
        .cf-file__vl {
            font-size: .86rem;
            font-weight: 650;
            color: currentColor;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .cf-file__act {
            display: flex;
            align-items: center;
            gap: .5rem;
            flex-wrap: wrap;
        }
        .cf-btn {
            display: inline-flex;
            align-items: center;
            gap: .4rem;
            padding: .5rem .85rem;
            border-radius: .5rem;
            font-size: .81rem;
            font-weight: 600;
            text-decoration: none;
            transition: opacity .12s, background .12s;
        }
        .cf-btn--primary {
            background: #2563eb;
            color: #fff;
        }
        .cf-btn--primary:hover {
            opacity: .88;
        }
        .cf-btn--ghost {
            background: transparent;
            color: currentColor;
            box-shadow: inset 0 0 0 1px rgba(127,127,127,.22);
        }
        .cf-btn--ghost:hover {
            background: rgba(127,127,127,.08);
        }

        /* Mobile (< 640px): stack the file card vertically + tighter thumb */
        @media (max-width: 640px) {
            .cf-file__row {
                flex-direction: column;
                align-items: stretch;
                gap: .8rem;
            }
            .cf-file__thumb {
                width: 100%;
                height: 4.5rem;
            }
            .cf-file__act {
                flex-wrap: wrap;
            }
        }
    </style>
@endonce
