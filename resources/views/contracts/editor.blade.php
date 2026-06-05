<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <title>{{ $contract->number }} — {{ $contract->title }}</title>
    <style>
        html, body { height: 100%; margin: 0; padding: 0; background: #1f2937; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .editor-shell { position: fixed; inset: 0; display: flex; flex-direction: column; }
        .editor-topbar {
            display: flex; align-items: center; gap: 12px;
            padding: 8px 16px; background: #111827; color: #f9fafb;
            border-bottom: 1px solid #374151; flex-shrink: 0;
        }
        .editor-topbar .back-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 6px 12px; border-radius: 6px; color: #f9fafb;
            text-decoration: none; font-size: 13px; font-weight: 500;
            background: transparent; border: 1px solid #374151; cursor: pointer;
            transition: background 120ms ease, border-color 120ms ease;
        }
        .editor-topbar .back-btn:hover { background: #1f2937; border-color: #4b5563; }
        .editor-topbar .doc-title { flex: 1; min-width: 0; font-size: 14px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .editor-topbar .doc-number { color: #9ca3af; font-weight: 500; margin-right: 8px; }
        .editor-topbar .close-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 32px; height: 32px; border-radius: 6px; color: #f9fafb;
            background: transparent; border: 1px solid transparent; cursor: pointer;
            transition: background 120ms ease, border-color 120ms ease;
        }
        .editor-topbar .close-btn:hover { background: #7f1d1d; border-color: #b91c1c; }
        .editor-stage { flex: 1; position: relative; background: #f3f4f6; }
        #onlyoffice-editor { position: absolute; inset: 0; }
    </style>
</head>
<body>
    <div class="editor-shell">
        <div class="editor-topbar">
            <a href="{{ $backUrl }}" class="back-btn" title="{{ __('app.action.back_to_contract') }}">
                <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M19 12H5"/><path d="m12 19-7-7 7-7"/>
                </svg>
                {{ __('app.action.back_to_contract') }}
            </a>

            <div class="doc-title">
                <span class="doc-number">{{ $contract->number }}</span>{{ $contract->title }}
            </div>

            <a href="{{ $backUrl }}" class="close-btn" title="{{ __('app.action.close') }}">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </a>
        </div>

        <div class="editor-stage">
            <div id="onlyoffice-editor"></div>
        </div>
    </div>

    <script src="{{ $apiScriptUrl }}"></script>
    <script>
        window.addEventListener('load', function () {
            new DocsAPI.DocEditor('onlyoffice-editor', @json($config));
        });
    </script>
</body>
</html>
