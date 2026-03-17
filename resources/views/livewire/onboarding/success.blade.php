<div>
    <div class="rounded-2xl bg-white/80 p-8 shadow-xl ring-1 ring-gray-900/5 backdrop-blur-sm text-center">
        <div class="mx-auto mb-6 flex h-20 w-20 items-center justify-center rounded-full bg-green-100">
            <svg class="h-10 w-10 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
        </div>

        <h2 class="text-2xl font-bold text-gray-900 mb-3">{{ __('partner.onboarding.success.title') }}</h2>
        <p class="text-gray-600 mb-8">{{ __('partner.onboarding.success.message') }}</p>

        <a href="{{ route('login') }}" class="inline-flex items-center gap-2 rounded-lg bg-primary-600 px-8 py-3 text-sm font-semibold text-white hover:bg-primary-700 shadow-sm transition">
            <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
            </svg>
            {{ __('partner.onboarding.success.login_button') }}
        </a>
    </div>
</div>
