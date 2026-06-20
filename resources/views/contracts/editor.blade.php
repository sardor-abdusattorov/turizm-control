@include('partials.document-editor', [
    'pageTitle' => $contract->number.' — '.$contract->title,
    'docNumber' => $contract->number,
    'docTitle' => $contract->title,
    'statusLabel' => $contract->status->label(),
    'statusTone' => 'gray',
    'backUrl' => $backUrl,
    'apiScriptUrl' => $apiScriptUrl,
    'config' => $config,
])
