<x-filament-panels::page>
    <form wire:submit="importPdfs">
        {{ $this->form }}

        <div class="mt-4">
            <x-filament::button type="submit" size="lg">
                📄 Rechnungen importieren
            </x-filament::button>
        </div>
    </form>
</x-filament-panels::page>
