@include('partials.document-editor', [
    'pageTitle' => $attachment->original_name.' — '.$contract->number,
    'docNumber' => $contract->number,
    'docTitle' => $attachment->original_name,
    'statusLabel' => __('app.label.attachments'),
    'statusTone' => 'primary',
    'backUrl' => $backUrl,
    'apiScriptUrl' => $apiScriptUrl,
    'config' => $config,
])
