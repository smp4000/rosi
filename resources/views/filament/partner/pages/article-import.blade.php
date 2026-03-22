<x-filament-panels::page>
    <form wire:submit="importCsv">
        {{ $this->form }}

        <div class="mt-6 flex items-center justify-end gap-4">
            {{-- Spinner waehrend Import --}}
            <div wire:loading wire:target="importCsv" class="flex items-center gap-2">
                <x-filament::loading-indicator class="h-5 w-5 text-primary-600" />
                <span class="text-sm text-gray-600 dark:text-gray-400">Import wird verarbeitet... Bitte warten.</span>
            </div>

            <x-filament::button
                type="submit"
                color="primary"
                icon="heroicon-o-arrow-up-tray"
                wire:loading.attr="disabled"
                wire:target="importCsv"
            >
                <span wire:loading.remove wire:target="importCsv">Import starten</span>
                <span wire:loading wire:target="importCsv">Verarbeite...</span>
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
