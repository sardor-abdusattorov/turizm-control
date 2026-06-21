@include('partials.document-editor', [
    'pageTitle' => $template->name.' — '.__('app.label.contract_template_single'),
    'docNumber' => $template->name,
    'docTitle' => $template->orderType?->title ?? '',
    'statusLabel' => __('app.label.contract_template_single'),
    'statusTone' => 'primary',
    'backUrl' => $backUrl,
    'apiScriptUrl' => $apiScriptUrl,
    'config' => $config,
])
