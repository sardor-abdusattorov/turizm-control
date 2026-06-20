<?php

namespace App\Http\Controllers;

use App\Models\Payment;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

class PaymentScreenshotController extends Controller
{
    public function __invoke(Payment $payment): BinaryFileResponse
    {
        $user = auth()->user();

        abort_unless(
            $user && (
                $user->hasRole('super_admin')
                || $user->can('view_any_payment')
                || ($user->can('view_payment') && $payment->contract?->canBeViewedBy($user))
            ),
            403,
        );

        $disk = Storage::disk('local');

        if (! $payment->screenshot || ! $disk->exists($payment->screenshot)) {
            throw new NotFoundHttpException('Payment screenshot not found.');
        }

        return response()->file($disk->path($payment->screenshot));
    }
}
