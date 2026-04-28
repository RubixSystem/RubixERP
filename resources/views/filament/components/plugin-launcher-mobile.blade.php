@auth
    @php
        $navigation = filament()->getNavigation();
        $isRtl = __('filament-panels::layout.direction') === 'rtl';

        $bottomIcons = ['icon-plugin', 'icon-settings'];

        $launcherGroups = collect($navigation)
            ->filter(fn ($group) => $group->getLabel() && $group->getIcon() && $group->getItems()->first()?->getUrl())
            ->values();

        [$bottomGroups, $topGroups] = $launcherGroups->partition(
            fn ($group) => in_array($group->getIcon(), $bottomIcons, true)
        );
    @endphp

    <x-filament::dropdown
        placement="bottom-start"
        teleport
        width="md"
        class="fi-plugin-launcher-mobile"
    >
        <x-slot name="trigger">
            <button
                type="button"
                class="fi-plugin-launcher-mobile-trigger"
                aria-label="{{ __('Modules') }}"
            >
                @svg('heroicon-o-squares-2x2', 'fi-plugin-launcher-mobile-trigger-icon')
            </button>
        </x-slot>

        <div class="fi-plugin-launcher-mobile-panel">
            @if ($topGroups->isNotEmpty())
                <div class="fi-plugin-launcher-mobile-grid">
                    @foreach ($topGroups as $group)
                        @php
                            $label = $group->getLabel();
                            $icon = $group->getIcon();
                            $url = $group->getItems()->first()->getUrl();
                            $isActive = $group->isActive();
                        @endphp

                        <a
                            href="{{ $url }}"
                            @class([
                                'fi-plugin-launcher-mobile-item',
                                'fi-active' => $isActive,
                            ])
                            aria-label="{{ $label }}"
                        >
                            @svg($icon, 'fi-plugin-launcher-mobile-item-icon')
                            <span class="fi-plugin-launcher-mobile-item-label">{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            @endif

            @if ($bottomGroups->isNotEmpty())
                <div class="fi-plugin-launcher-mobile-divider"></div>

                <div class="fi-plugin-launcher-mobile-grid">
                    @foreach ($bottomGroups as $group)
                        @php
                            $label = $group->getLabel();
                            $icon = $group->getIcon();
                            $url = $group->getItems()->first()->getUrl();
                            $isActive = $group->isActive();
                        @endphp

                        <a
                            href="{{ $url }}"
                            @class([
                                'fi-plugin-launcher-mobile-item',
                                'fi-active' => $isActive,
                            ])
                            aria-label="{{ $label }}"
                        >
                            @svg($icon, 'fi-plugin-launcher-mobile-item-icon')
                            <span class="fi-plugin-launcher-mobile-item-label">{{ $label }}</span>
                        </a>
                    @endforeach
                </div>
            @endif
        </div>
    </x-filament::dropdown>
@endauth
