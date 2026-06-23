@if ($ids === [])
    <p style="margin:0;color:#9ca3af;font-size:.875rem;">{{ __('app.helper.approval_chain_empty') }}</p>
@else
    <div style="display:flex;flex-direction:column;gap:.45rem;margin-top:.25rem;">
        @foreach ($ids as $id)
            @php($user = $users->get($id))

            @continue(! $user)

            @php($avatar = $user->getFilamentAvatarUrl() ?? 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&color=7F9CF5&background=EBF4FF')
            @php($meta = trim(($user->department?->name ?? '').($user->position?->name ? ' · '.$user->position->name : ''), ' ·'))

            <div style="display:flex;align-items:center;gap:.7rem;padding:.6rem .8rem;border:1px solid rgba(127,127,127,.22);border-radius:.65rem;background:rgba(127,127,127,.05);">
                <span style="flex-shrink:0;width:1.55rem;height:1.55rem;display:flex;align-items:center;justify-content:center;border-radius:50%;background:rgba(99,102,241,.18);color:#6366f1;font-size:.78rem;font-weight:700;">
                    {{ $loop->iteration }}
                </span>
                <img src="{{ $avatar }}" alt="" style="width:2rem;height:2rem;border-radius:50%;object-fit:cover;flex-shrink:0;">
                <span style="min-width:0;display:flex;flex-direction:column;gap:.12rem;">
                    <span style="font-size:.92rem;font-weight:600;color:currentColor;">{{ $user->name }}</span>
                    <span style="font-size:.82rem;color:currentColor;opacity:.65;">{{ $meta }}</span>
                </span>
            </div>
        @endforeach
    </div>
@endif
