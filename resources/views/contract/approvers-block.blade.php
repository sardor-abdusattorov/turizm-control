@php
    /** @var \Illuminate\Database\Eloquent\Collection<int, \App\Models\ContractApprover> $approvers */
    /** @var string $locale */
    /** @var ?\App\Models\User $responsible */

    $columns = collect();

    if ($responsible) {
        $columns->push([
            'header' => __('app.approvers_block.submitted_by'),
            'role' => __('app.approvers_block.role_initiator'),
            'name' => $responsible->name,
            'position' => $responsible->department?->getTranslation('name', $locale) ?? '',
            'acted_at' => null,
            'status' => null,
        ]);
    }

    foreach ($approvers as $approver) {
        $columns->push([
            'header' => __('app.approvers_block.agreed_by'),
            'role' => $approver->user?->department?->getTranslation('name', $locale) ?? '',
            'name' => $approver->user?->name ?? '',
            'position' => $approver->user?->department?->getTranslation('name', $locale) ?? '',
            'acted_at' => $approver->acted_at,
            'status' => $approver->status,
        ]);
    }
@endphp

<table style="width: 100%; border-collapse: collapse; margin-top: 24px;">
    <thead>
        <tr>
            @foreach ($columns as $col)
                <th style="border: 1px solid #000; padding: 8px; text-align: center; font-size: 11px; vertical-align: top;">
                    <strong>{{ $col['header'] }}</strong><br>
                    <em style="font-weight: normal;">({{ $col['role'] }})</em>
                </th>
            @endforeach
        </tr>
    </thead>
    <tbody>
        <tr>
            @foreach ($columns as $col)
                <td style="border: 1px solid #000; padding: 16px 8px; text-align: center; font-size: 11px; vertical-align: top; height: 80px;">
                    <strong>{{ $col['name'] }}</strong><br>
                    <span style="font-size: 10px;">{{ $col['position'] }}</span>
                </td>
            @endforeach
        </tr>
        <tr>
            @foreach ($columns as $col)
                <td style="border: 1px solid #000; padding: 16px 8px; text-align: center; font-size: 11px; vertical-align: top;">
                    @if ($col['status'] === \App\Models\ContractApprover::STATUS_APPROVED && $col['acted_at'])
                        <span style="font-style: italic;">
                            {{ __('app.approvers_block.signed_on', ['date' => $col['acted_at']->format('d.m.Y')]) }}
                        </span>
                    @else
                        ____________<br>
                        <em style="font-size: 10px;">({{ __('app.approvers_block.signature') }})</em>
                    @endif
                </td>
            @endforeach
        </tr>
    </tbody>
</table>
