<x-filament-widgets::widget>
    <x-filament::section>
        <x-slot name="heading">{{ __('partner.dashboard.quick_actions.title') }}</x-slot>
        <div style="display: flex; flex-wrap: wrap; gap: 0.75rem;">
            <a href="{{ \App\Filament\Partner\Resources\GasStationResource::getUrl('create') }}"
               style="display: inline-flex; align-items: center; gap: 0.5rem; padding: 0.625rem 1rem; border: 1px solid rgb(199 210 254); background: rgb(238 242 255); border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; color: rgb(67 56 202); text-decoration: none;">
                {{ svg('heroicon-o-plus-circle', '', ['style' => 'width: 1.25rem; height: 1.25rem;']) }}
                {{ __('partner.dashboard.quick_actions.add_station') }}
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
