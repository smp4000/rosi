<x-filament-widgets::widget>
    <div x-data="{ open: false }">
        {{-- Link --}}
        <div style="text-align: center; padding: 0.5rem 0;">
            <button
                @click="open = true"
                type="button"
                style="display: inline-flex; align-items: center; gap: 0.375rem; font-size: 0.8125rem; color: rgb(100 116 139); background: none; border: none; cursor: pointer; text-decoration: underline; text-underline-offset: 2px;"
            >
                {{ svg('heroicon-o-document-text', '', ['style' => 'width: 1rem; height: 1rem;']) }}
                Versionshistorie anzeigen
            </button>
        </div>

        {{-- Modal --}}
        <div
            x-show="open"
            x-transition.opacity
            @keydown.escape.window="open = false"
            style="position: fixed; inset: 0; z-index: 50; display: flex; align-items: center; justify-content: center; padding: 1rem;"
        >
            {{-- Backdrop --}}
            <div @click="open = false" style="position: fixed; inset: 0; background: rgba(0,0,0,0.5);"></div>

            {{-- Dialog --}}
            <div
                x-show="open"
                x-transition
                style="position: relative; background: white; border-radius: 0.75rem; box-shadow: 0 25px 50px -12px rgba(0,0,0,0.25); width: 100%; max-width: 36rem; max-height: 80vh; display: flex; flex-direction: column; overflow: hidden;"
                class="dark:bg-gray-800"
            >
                {{-- Header --}}
                <div style="display: flex; align-items: center; justify-content: space-between; padding: 1.25rem 1.5rem; border-bottom: 1px solid rgb(229 231 235);">
                    <div style="display: flex; align-items: center; gap: 0.5rem;">
                        {{ svg('heroicon-o-document-text', '', ['style' => 'width: 1.25rem; height: 1.25rem; color: rgb(99 102 241);']) }}
                        <h2 style="font-size: 1.125rem; font-weight: 600; margin: 0;">Versionshistorie (Web)</h2>
                    </div>
                    <button @click="open = false" type="button" style="background: none; border: none; cursor: pointer; color: rgb(156 163 175); padding: 0.25rem;">
                        {{ svg('heroicon-o-x-mark', '', ['style' => 'width: 1.25rem; height: 1.25rem;']) }}
                    </button>
                </div>

                {{-- Content --}}
                <div style="overflow-y: auto; padding: 1.5rem; flex: 1; min-height: 0;">
                    @php $versions = $this->getVersions(); @endphp

                    @forelse ($versions as $index => $version)
                        <div style="margin-bottom: 1.5rem; {{ $index > 0 ? 'padding-top: 1.5rem; border-top: 1px solid rgb(243 244 246);' : '' }}">
                            <div style="display: flex; align-items: center; gap: 0.75rem; margin-bottom: 0.5rem;">
                                <span style="display: inline-flex; padding: 0.125rem 0.625rem; background: rgb(238 242 255); color: rgb(67 56 202); border-radius: 9999px; font-size: 0.8125rem; font-weight: 600;">
                                    v{{ $version['version'] }}
                                </span>
                                <span style="font-size: 0.8125rem; color: rgb(107 114 128);">
                                    {{ $version['date'] }}
                                </span>
                            </div>
                            <ul style="margin: 0; padding-left: 1.25rem; list-style: disc;">
                                @foreach ($version['changes'] as $change)
                                    <li style="font-size: 0.875rem; color: rgb(55 65 81); margin-bottom: 0.25rem;">{{ $change }}</li>
                                @endforeach
                            </ul>
                        </div>
                    @empty
                        <p style="text-align: center; color: rgb(156 163 175); font-size: 0.875rem;">Noch keine Versionen vorhanden.</p>
                    @endforelse
                </div>

                {{-- Footer mit Scroll-Hinweis + Gradient --}}
                <div style="position: relative; flex-shrink: 0;">
                    {{-- Gradient-Schatten ueber dem Content --}}
                    <div style="position: absolute; bottom: 100%; left: 0; right: 0; height: 3rem; background: linear-gradient(to top, white, transparent); pointer-events: none;"></div>

                    {{-- Footer --}}
                    <div style="display: flex; align-items: center; justify-content: center; gap: 0.375rem; padding: 0.75rem 1rem; border-top: 1px solid rgb(229 231 235); background: rgb(243 244 246); font-size: 0.8125rem; color: rgb(107 114 128);">
                        ↓ Scrollen fuer aeltere Versionen
                    </div>
                </div>
            </div>
        </div>
    </div>
</x-filament-widgets::widget>
