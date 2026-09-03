@php
    use App\Enums\PaymentStatus;

    /** @var \App\Models\Contract $record */
    $record = $getRecord();

    $status = $record->payment_status ?? PaymentStatus::NotPaid;
    $percent = (float) $record->paid_percent;
    $percentLabel = format_percent($percent);

    $tone = match ($status) {
        PaymentStatus::FullyPaid => ['bg' => 'rgba(16,185,129,.14)', 'fg' => '#047857', 'dot' => '#10b981'],
        PaymentStatus::PartiallyPaid => ['bg' => 'rgba(251,146,60,.16)', 'fg' => '#c2410c', 'dot' => '#fb923c'],
        default => ['bg' => 'rgba(127,127,127,.14)', 'fg' => '#475569', 'dot' => '#94a3b8'],
    };

    $text = $status === PaymentStatus::PartiallyPaid ? $percentLabel.'%' : $status->label();
@endphp

<span style="display:inline-flex;align-items:center;gap:.4rem;padding:.35rem .7rem;
             border-radius:.45rem;background:{{ $tone['bg'] }};color:{{ $tone['fg'] }};
             font-size:.83rem;font-weight:600;line-height:1.1;white-space:nowrap;">
    <span style="width:.42rem;height:.42rem;border-radius:50%;background:{{ $tone['dot'] }};flex-shrink:0;"></span>
    {{ $text }}
</span>
