@php
    /** @var \Illuminate\Support\Collection<int, \App\Models\User> $users */
@endphp

@once
    <style>
        .acp {
            display: flex;
            flex-direction: column;
            gap: .45rem;
            margin-top: .25rem;
        }
        .acp-empty {
            margin: 0;
            color: #9ca3af;
            font-size: .875rem;
        }
        .acp-row {
            display: flex;
            align-items: center;
            gap: .7rem;
            padding: .6rem .8rem;
            border: 1px solid rgba(127,127,127,.22);
            border-radius: .65rem;
            background: rgba(127,127,127,.05);
        }
        .acp-step {
            flex-shrink: 0;
            width: 1.55rem;
            height: 1.55rem;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 50%;
            background: rgba(99,102,241,.18);
            color: #6366f1;
            font-size: .78rem;
            font-weight: 700;
        }
        .acp-avatar {
            width: 2rem;
            height: 2rem;
            border-radius: 50%;
            object-fit: cover;
            flex-shrink: 0;
        }
        .acp-identity {
            min-width: 0;
            display: flex;
            flex-direction: column;
            gap: .12rem;
        }
        .acp-name {
            font-size: .92rem;
            font-weight: 600;
            color: currentColor;
        }
        .acp-meta {
            font-size: .82rem;
            color: currentColor;
            opacity: .65;
        }
    </style>
@endonce

@if ($users->isEmpty())
    <p class="acp-empty">{{ __('app.helper.approval_chain_empty') }}</p>
@else
    <div class="acp">
        @foreach ($users as $user)
            @php
                $avatar = $user->getFilamentAvatarUrl()
                    ?? 'https://ui-avatars.com/api/?name='.urlencode($user->name).'&color=7F9CF5&background=EBF4FF';
                $meta = trim(($user->department?->name ?? '').($user->position?->name ? ' · '.$user->position->name : ''), ' ·');
            @endphp

            <div class="acp-row">
                <span class="acp-step">{{ $loop->iteration }}</span>
                <img src="{{ $avatar }}" alt="" class="acp-avatar">
                <span class="acp-identity">
                    <span class="acp-name">{{ $user->name }}</span>
                    <span class="acp-meta">{{ $meta }}</span>
                </span>
            </div>
        @endforeach
    </div>
@endif
