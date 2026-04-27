@auth
    @php
        $navigation = filament()->getNavigation();
        $isRtl = __('filament-panels::layout.direction') === 'rtl';

        // Icons of groups that should be pinned to the bottom of the rail
        $bottomIcons = ['icon-plugin', 'icon-settings'];

        // Only keep groups that have a navigable item and an icon
        $railGroups = collect($navigation)
            ->filter(fn ($group) => $group->getLabel() && $group->getIcon() && $group->getItems()->first()?->getUrl())
            ->values();

        // Split: top (most groups) and bottom (plugin + settings)
        [$bottomGroups, $topGroups] = $railGroups->partition(
            fn ($group) => in_array($group->getIcon(), $bottomIcons, true)
        );

        $visibleLimit = 8;
        $visibleTopGroups = $topGroups->take($visibleLimit);
        $overflowTopGroups = $topGroups->slice($visibleLimit);
    @endphp

    <aside
        class="fi-plugin-rail"
        aria-label="{{ __('Modules') }}"
    >
        <nav class="fi-plugin-rail-nav">
            <div class="fi-plugin-rail-section fi-plugin-rail-section-top">
                @foreach ($visibleTopGroups as $group)
                    @php
                        $label = $group->getLabel();
                        $icon = $group->getIcon();
                        $url = $group->getItems()->first()->getUrl();
                        $isActive = $group->isActive();
                    @endphp

                    <a
                        href="{{ $url }}"
                        @class([
                            'fi-plugin-rail-item',
                            'fi-active' => $isActive,
                        ])
                        x-data="{}"
                        x-tooltip.raw.placement.{{ $isRtl ? 'left' : 'right' }}="{{ $label }}"
                        aria-label="{{ $label }}"
                    >
                        @svg($icon, 'fi-plugin-rail-item-icon')
                    </a>
                @endforeach

                @if ($overflowTopGroups->isNotEmpty())
                    <x-filament::dropdown
                        placement="{{ $isRtl ? 'left-start' : 'right-start' }}"
                        teleport
                        width="xs"
                    >
                        <x-slot name="trigger">
                            <button
                                type="button"
                                class="fi-plugin-rail-item fi-plugin-rail-more"
                                x-data="{}"
                                x-tooltip.raw.placement.{{ $isRtl ? 'left' : 'right' }}="{{ __('More') }}"
                                aria-label="{{ __('More') }}"
                            >
                                @svg('heroicon-o-ellipsis-horizontal', 'fi-plugin-rail-item-icon')
                            </button>
                        </x-slot>

                        <x-filament::dropdown.list>
                            @foreach ($overflowTopGroups as $group)
                                <x-filament::dropdown.list.item
                                    :href="$group->getItems()->first()->getUrl()"
                                    :icon="$group->getIcon()"
                                    :color="$group->isActive() ? 'primary' : 'gray'"
                                    tag="a"
                                >
                                    {{ $group->getLabel() }}
                                </x-filament::dropdown.list.item>
                            @endforeach
                        </x-filament::dropdown.list>
                    </x-filament::dropdown>
                @endif
            </div>

            @if ($bottomGroups->isNotEmpty())
                <div class="fi-plugin-rail-section fi-plugin-rail-section-bottom">
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
                                'fi-plugin-rail-item',
                                'fi-active' => $isActive,
                            ])
                            x-data="{}"
                            x-tooltip.raw.placement.{{ $isRtl ? 'left' : 'right' }}="{{ $label }}"
                            aria-label="{{ $label }}"
                        >
                            @svg($icon, 'fi-plugin-rail-item-icon')
                        </a>
                    @endforeach
                </div>
            @endif
        </nav>
    </aside>
@endauth
