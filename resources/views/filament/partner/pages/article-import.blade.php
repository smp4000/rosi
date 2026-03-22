<x-filament-panels::page>
    <form wire:submit="importCsv">
        {{ $this->form }}

        <div class="mt-6 flex justify-end">
            <x-filament::button type="submit" color="primary" icon="heroicon-o-arrow-up-tray">
                Import starten
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
