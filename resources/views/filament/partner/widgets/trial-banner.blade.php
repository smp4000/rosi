<x-filament-widgets::widget>
    <x-filament::section>
        <div style="display: flex; align-items: center; justify-content: space-between; gap: 1rem;">
            <div style="display: flex; align-items: center; gap: 0.75rem;">
                <div style="display: flex; align-items: center; justify-content: center; width: 2.5rem; height: 2.5rem; border-radius: 0.5rem; background: rgb(254 243 199); flex-shrink: 0;">
                    {{ svg('heroicon-o-clock', '', ['style' => 'width: 1.25rem; height: 1.25rem; color: rgb(217 119 6);']) }}
                </div>
                <div>
                    <h3 style="font-size: 0.875rem; font-weight: 600; color: rgb(146 64 14);">
                        {{ __('partner.dashboard.trial_banner.title') }}
                    </h3>
                    <p style="font-size: 0.875rem; color: rgb(217 119 6);">
                        {{ __('partner.dashboard.trial_banner.message', [
                            'days' => $this->getDaysRemaining(),
                            'date' => $this->getTrialEndDate(),
                        ]) }}
                    </p>
                </div>
            </div>
            <a href="#"
               style="flex-shrink: 0; padding: 0.5rem 1rem; background: rgb(217 119 6); color: white; border-radius: 0.5rem; font-size: 0.875rem; font-weight: 500; text-decoration: none;">
                {{ __('partner.dashboard.trial_banner.choose_plan') }}
            </a>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
