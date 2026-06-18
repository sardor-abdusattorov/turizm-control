<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\OnlyOffice\OnlyOfficeCallbackHandler;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class OnlyOfficeOrderController extends Controller
{
    public function __construct(public OnlyOfficeCallbackHandler $callback) {}

    public function document(Request $request, Order $order): BinaryFileResponse
    {
        $this->callback->ensureSharedKey($request, $order->document_key);

        abort_unless($order->fileExists(), 404);

        return response()->download(
            $order->fileAbsolutePath(),
            basename((string) $order->file_path),
        );
    }

    public function callback(Request $request, Order $order): JsonResponse
    {
        $this->callback->ensureSharedKey($request, $order->document_key);

        $result = $this->callback->handle(
            request: $request,
            subject: $order,
            savedEvent: 'Order Document Saved',
            forcesaveEvent: 'Order Document Forcesave',
            logDescription: 'Order "'.$order->title.'" saved via OnlyOffice',
            persist: $this->callback->persistToDisk('local', $order->file_path),
            onFinalSave: fn () => $order->refreshDocumentKey(),
            logProperties: ['order_title' => $order->title],
        );

        return response()->json($result);
    }
}
