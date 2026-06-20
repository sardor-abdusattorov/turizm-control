@include('partials.document-editor', [
    'pageTitle' => $order->title.' — '.__('app.label.order_single'),
    'docNumber' => $order->title,
    'docTitle' => $order->orderType?->title ?? '',
    'statusLabel' => __('app.label.order_single'),
    'statusTone' => 'primary',
    'backUrl' => $backUrl,
    'apiScriptUrl' => $apiScriptUrl,
    'config' => $config,
])
