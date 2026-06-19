<?php

namespace App\Http\Controllers;

use App\Filament\Resources\Orders\OrderResource;
use App\Models\Order;
use App\Services\OnlyOffice\OnlyOfficeService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class OrderEditorController extends Controller
{
    public function show(Request $request, Order $order, OnlyOfficeService $service): Response
    {
        abort_unless($request->user()->can('view', $order), 403);

        if (! $order->fileExists() || ! $order->isOpenableInOnlyOffice()) {
            throw new NotFoundHttpException('Order file is missing or unsupported by OnlyOffice.');
        }

        return response()
            ->view('orders.editor', [
                'order' => $order,
                'apiScriptUrl' => $service->apiScriptUrl(),
                'config' => $service->orderEditorConfig($order, auth()->user(), $request->query('mode')),
                'backUrl' => OrderResource::getUrl('index'),
            ])
            ->header('Cache-Control', 'no-store, no-cache, must-revalidate, max-age=0')
            ->header('Pragma', 'no-cache')
            ->header('Expires', '0');
    }
}
