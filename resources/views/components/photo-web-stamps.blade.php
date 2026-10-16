@props(['account'])
@php($program = $account->resolvedProgram())
<div id="stamp-circles-container" data-photo="true" class="photo-web-stamps" aria-label="Stamp progress">
    @if($account->reward_target > 20)
        <progress id="photo-stamp-progress" max="{{ $account->reward_target }}" value="{{ $account->stamp_count }}" aria-label="Stamps collected"></progress>
    @else
        @for($i = 1; $i <= $account->reward_target; $i++)
            <span class="stamp-circle photo-web-stamp {{ $i <= $account->stamp_count ? 'is-filled' : '' }}" aria-label="Stamp {{ $i }}: {{ $i <= $account->stamp_count ? 'collected' : 'empty' }}">
                <span class="photo-web-stamp-icon" aria-hidden="true">
                    @if($program->stamp_icon === 'custom' && $program->stamp_icon_path)
                        <img src="{{ \App\Support\StoreAssets::url($program->stamp_icon_path) }}" alt="">
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round">
                            @switch($program->stamp_icon)
                                @case('star')<path d="m12 3 2.8 5.7 6.2.9-4.5 4.4 1.1 6.2-5.6-3-5.6 3 1.1-6.2L3 9.6l6.2-.9Z"/>@break
                                @case('heart')<path d="M12 21 3.5 12.5C-2 6 7 1 12 7c5-6 14-1 8.5 5.5Z"/>@break
                                @case('bean')<ellipse cx="12" cy="12" rx="7" ry="10" transform="rotate(35 12 12)"/><path d="M16 4c-9 3 1 10-8 16"/>@break
                                @default<path d="M4 9h13v6a5 5 0 0 1-5 5H9a5 5 0 0 1-5-5Zm13 1h2a3 3 0 0 1 0 6h-2M3 23h16M8 2c-3 2 3 3 0 5M13 2c-3 2 3 3 0 5"/>
                            @endswitch
                        </svg>
                    @endif
                </span>
            </span>
        @endfor
    @endif
</div>
