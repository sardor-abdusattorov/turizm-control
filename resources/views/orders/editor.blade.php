<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="robots" content="noindex,nofollow">
    <meta http-equiv="Cache-Control" content="no-store, no-cache, must-revalidate, max-age=0">
    <meta http-equiv="Pragma" content="no-cache">
    <meta http-equiv="Expires" content="0">
    <title>{{ $order->title }} — {{ __('app.label.order_single') }}</title>
    <style>
        html, body { height: 100%; margin: 0; padding: 0; background: #f3f4f6; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; }
        .editor-shell { position: fixed; inset: 0; display: flex; flex-direction: column; }
        .editor-topbar {
            display: flex; align-items: center; gap: 14px;
            padding: 10px 18px; background: #ffffff; color: #111827;
            border-bottom: 1px solid #e5e7eb; flex-shrink: 0;
            box-shadow: 0 1px 2px rgba(0,0,0,.04);
        }
        .editor-topbar .close-btn {
            display: inline-flex; align-items: center; justify-content: center;
            width: 36px; height: 36px; border-radius: 50%; color: #374151;
            background: #f3f4f6; border: 1px solid #e5e7eb; cursor: pointer;
            text-decoration: none; flex-shrink: 0;
            transition: background 120ms ease, color 120ms ease;
        }
        .editor-topbar .close-btn:hover { background: #fee2e2; color: #b91c1c; border-color: #fecaca; }
        .editor-topbar .doc-meta { flex: 1; min-width: 0; display: flex; align-items: baseline; gap: 10px; }
        .editor-topbar .doc-number { font-weight: 700; font-size: 14px; color: #111827; flex-shrink: 0; }
        .editor-topbar .doc-title { font-size: 14px; color: #6b7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
        .editor-topbar .status-pill {
            font-size: 12px; font-weight: 600; padding: 4px 10px; border-radius: 999px;
            background: #eef2ff; color: #4338ca; flex-shrink: 0;
        }
        .editor-stage { flex: 1; position: relative; background: #f3f4f6; }
        #onlyoffice-editor { position: absolute; inset: 0; }
        .editor-loading {
            position: absolute; inset: 0; display: flex; align-items: center; justify-content: center;
            color: #9ca3af; font-size: 14px; gap: 10px;
        }
        .editor-loading .spinner {
            width: 18px; height: 18px; border: 2px solid #e5e7eb; border-top-color: #6b7280;
            border-radius: 50%; animation: spin 700ms linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
    </style>
</head>
<body>
    <div class="editor-shell">
        <div class="editor-topbar">
            <a href="{{ $backUrl }}"
               class="close-btn"
               title="{{ __('app.action.close') }}"
               data-back-url="{{ $backUrl }}"
               onclick="closeEditor(event)">
                <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    <line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>
                </svg>
            </a>

            <div class="doc-meta">
                <span class="doc-number">{{ $order->title }}</span>
                <span class="doc-title">{{ $order->orderType?->title }}</span>
            </div>

            <span class="status-pill">{{ __('app.label.order_single') }}</span>
        </div>

        <div class="editor-stage">
            <div class="editor-loading" id="editor-loading">
                <span class="spinner"></span>{{ __('app.message.loading_editor') }}
            </div>
            <div id="onlyoffice-editor"></div>
        </div>
    </div>

    <script src="{{ $apiScriptUrl }}"></script>
    <script>
        function closeEditor(event) {
            event.preventDefault();
            var backUrl = event.currentTarget.getAttribute('data-back-url');

            if (document.referrer && window.history.length > 1) {
                window.history.back();
                return;
            }

            window.location.href = backUrl;
        }

        (function () {
            var config = @json($config);
            var attempts = 0;

            function start() {
                if (window.DocsAPI && typeof window.DocsAPI.DocEditor === 'function') {
                    var loading = document.getElementById('editor-loading');
                    if (loading) { loading.remove(); }
                    new DocsAPI.DocEditor('onlyoffice-editor', config);
                    return;
                }

                if (attempts++ < 100) {
                    setTimeout(start, 150);
                }
            }

            start();
        })();
    </script>
</body>
</html>
