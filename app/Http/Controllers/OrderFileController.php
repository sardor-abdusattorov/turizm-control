<?php

namespace App\Http\Controllers;

use App\Models\Order;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderFileController extends Controller
{
    public function inline(Order $order): BinaryFileResponse
    {
        abort_unless(auth()->user()?->can('view', $order) ?? false, 403);

        if (! $order->fileExists()) {
            throw new NotFoundHttpException('Order file not found.');
        }

        $name = basename((string) $order->file_path);

        return response()->file($order->fileAbsolutePath(), [
            'Content-Type' => $this->mimeFor($order->extension()),
            'Content-Disposition' => 'inline; filename="'.$name.'"',
        ]);
    }

    private function mimeFor(?string $extension): string
    {
        return match ($extension) {
            'pdf' => 'application/pdf',
            'doc' => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'xls' => 'application/vnd.ms-excel',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'ppt' => 'application/vnd.ms-powerpoint',
            'pptx' => 'application/vnd.openxmlformats-officedocument.presentationml.presentation',
            'csv' => 'text/csv',
            default => 'application/octet-stream',
        };
    }
}
