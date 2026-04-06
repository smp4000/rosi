@php
    $alerts = $this->getAlerts();
@endphp

<div>
@if(count($alerts) > 0)
<x-filament-widgets::widget>
    <div class="space-y-3">
        <h3 style="font-size: 1rem; font-weight: 700; color: rgb(55 65 81); margin: 0 0 0.25rem;">
            {{ svg('heroicon-o-bell-alert', '', ['style' => 'width: 1.25rem; height: 1.25rem; display: inline; vertical-align: -0.2rem; margin-right: 0.375rem;']) }}Aktuelle Warnungen
        </h3>
        @foreach($alerts as $alert)
            @php
                $isDanger = $alert['level'] === 'danger';
                $bgColor = $isDanger ? 'rgb(254 242 242)' : 'rgb(255 251 235)';
                $borderColor = $isDanger ? 'rgb(239 68 68)' : 'rgb(245 158 11)';
                $iconColor = $isDanger ? 'rgb(220 38 38)' : 'rgb(217 119 6)';
                $textColor = $isDanger ? 'rgb(153 27 27)' : 'rgb(146 64 14)';
                $iconBg = $isDanger ? 'rgb(254 226 226)' : 'rgb(254 243 199)';
                $icon = $isDanger ? 'heroicon-o-exclamation-circle' : 'heroicon-o-exclamation-triangle';
            @endphp
            <div
                x-data="{ show: true }"
                x-show="show"
                x-transition
                style="background: {{ $bgColor }}; border-left: 4px solid {{ $borderColor }}; border-radius: 0.5rem; padding: 0.875rem 1rem; display: flex; align-items: flex-start; justify-content: space-between; gap: 0.75rem;"
            >
                <div style="display: flex; align-items: flex-start; gap: 0.75rem; flex: 1; min-width: 0;">
                    <div style="display: flex; align-items: center; justify-content: center; width: 2rem; height: 2rem; border-radius: 50%; background: {{ $iconBg }}; flex-shrink: 0; margin-top: 0.1rem;">
                        {{ svg($icon, '', ['style' => 'width: 1.25rem; height: 1.25rem; color: ' . $iconColor]) }}
                    </div>
                    <div style="min-width: 0;">
                        @if($alert['url'])
                            <a href="{{ $alert['url'] }}" style="text-decoration: none;">
                        @endif
                            <p style="font-size: 0.875rem; color: {{ $textColor }}; margin: 0; {{ $isDanger ? 'font-weight: 700;' : 'font-weight: 500;' }}">
                                {{ $alert['title'] }}
                            </p>
                            <p style="font-size: 0.8rem; color: {{ $textColor }}; margin: 0.25rem 0 0; opacity: 0.8;">
                                {{ $alert['message'] }}
                            </p>
                        @if($alert['url'])
                            </a>
                        @endif
                    </div>
                </div>
                <button
                    @click="show = false"
                    style="background: none; border: none; cursor: pointer; padding: 0.25rem; flex-shrink: 0; color: {{ $iconColor }}; opacity: 0.6;"
                    title="Ausblenden"
                >
                    {{ svg('heroicon-o-x-mark', '', ['style' => 'width: 1.25rem; height: 1.25rem;']) }}
                </button>
            </div>
        @endforeach
    </div>
</x-filament-widgets::widget>
@endif
</div>
