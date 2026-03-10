<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="description" content="ROSI - Die digitale Plattform fuer Tankstellenpartner. Verwalten Sie Ihre Tankstellen, Mitarbeiter, Kunden und Dokumente an einem Ort.">
    <title>ROSI - Tankstellen-Partner-Plattform</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="antialiased bg-white text-gray-900">

    {{-- ===== NAVIGATION ===== --}}
    <nav class="fixed top-0 z-50 w-full border-b border-gray-100 bg-white/80 backdrop-blur-lg"
         x-data="{ mobileOpen: false }">
        <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
            {{-- Logo --}}
            <a href="/" class="flex items-center gap-2.5">
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-primary-500 to-accent-600 shadow-md shadow-primary-500/20">
                    <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                    </svg>
                </div>
                <span class="text-xl font-bold tracking-tight text-gray-900">ROSI</span>
            </a>

            {{-- Desktop Navigation --}}
            <div class="hidden items-center gap-8 md:flex">
                <a href="#features" class="text-sm font-medium text-gray-600 transition hover:text-primary-600">Features</a>
                <a href="#how-it-works" class="text-sm font-medium text-gray-600 transition hover:text-primary-600">So funktioniert's</a>
                <a href="#pricing" class="text-sm font-medium text-gray-600 transition hover:text-primary-600">Preise</a>
            </div>

            {{-- CTA Buttons --}}
            <div class="hidden items-center gap-3 md:flex">
                @auth
                    <a href="{{ route('filament.partner.pages.dashboard') }}"
                       class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary-600/25 transition hover:bg-primary-700">
                        Dashboard
                    </a>
                @else
                    <a href="{{ route('login') }}"
                       class="rounded-lg border border-gray-200 px-4 py-2 text-sm font-semibold text-gray-700 transition hover:border-primary-300 hover:text-primary-700">
                        Anmelden
                    </a>
                    <a href="{{ route('register') }}"
                       class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-md shadow-primary-600/25 transition hover:bg-primary-700">
                        Kostenlos testen
                    </a>
                @endauth
            </div>

            {{-- Mobile Hamburger --}}
            <button @click="mobileOpen = !mobileOpen" class="md:hidden rounded-lg p-2 text-gray-600 hover:bg-gray-100">
                <svg x-show="!mobileOpen" class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
                <svg x-show="mobileOpen" x-cloak class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        {{-- Mobile Menu --}}
        <div x-show="mobileOpen" x-cloak
             x-transition:enter="transition ease-out duration-200"
             x-transition:enter-start="opacity-0 -translate-y-1"
             x-transition:enter-end="opacity-100 translate-y-0"
             x-transition:leave="transition ease-in duration-150"
             x-transition:leave-start="opacity-100 translate-y-0"
             x-transition:leave-end="opacity-0 -translate-y-1"
             class="border-t border-gray-100 bg-white px-4 pb-4 md:hidden">
            <div class="flex flex-col gap-3 pt-3">
                <a href="#features" class="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Features</a>
                <a href="#how-it-works" class="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">So funktioniert's</a>
                <a href="#pricing" class="rounded-lg px-3 py-2 text-sm font-medium text-gray-600 hover:bg-gray-50">Preise</a>
                <hr class="border-gray-100">
                @auth
                    <a href="{{ route('filament.partner.pages.dashboard') }}" class="rounded-lg bg-primary-600 px-4 py-2.5 text-center text-sm font-semibold text-white">Dashboard</a>
                @else
                    <a href="{{ route('login') }}" class="rounded-lg border border-gray-200 px-4 py-2.5 text-center text-sm font-semibold text-gray-700">Anmelden</a>
                    <a href="{{ route('register') }}" class="rounded-lg bg-primary-600 px-4 py-2.5 text-center text-sm font-semibold text-white">Kostenlos testen</a>
                @endauth
            </div>
        </div>
    </nav>

    {{-- ===== HERO SECTION ===== --}}
    <section class="relative overflow-hidden pt-24 sm:pt-32">
        {{-- Hintergrund-Dekorationen --}}
        <div class="absolute inset-0 -z-10">
            <div class="absolute -top-24 right-0 h-[500px] w-[500px] rounded-full bg-primary-100/50 blur-3xl"></div>
            <div class="absolute -bottom-24 left-0 h-[400px] w-[400px] rounded-full bg-accent-400/15 blur-3xl"></div>
            <div class="absolute top-1/2 left-1/2 h-[300px] w-[300px] -translate-x-1/2 -translate-y-1/2 rounded-full bg-primary-50/80 blur-3xl"></div>
        </div>

        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-3xl text-center">
                {{-- Badge --}}
                <div class="mb-6 inline-flex items-center gap-2 rounded-full border border-primary-200 bg-primary-50 px-4 py-1.5 text-sm font-medium text-primary-700">
                    <span class="flex h-2 w-2 rounded-full bg-primary-500">
                        <span class="inline-flex h-2 w-2 animate-ping rounded-full bg-primary-400 opacity-75"></span>
                    </span>
                    14 Tage kostenlos testen
                </div>

                {{-- Headline --}}
                <h1 class="text-4xl font-extrabold tracking-tight text-gray-900 sm:text-5xl lg:text-6xl">
                    Ihre Tankstellen.
                    <span class="bg-gradient-to-r from-primary-600 to-accent-600 bg-clip-text text-transparent">Digital verwaltet.</span>
                </h1>

                {{-- Subheadline --}}
                <p class="mx-auto mt-6 max-w-2xl text-lg text-gray-500 sm:text-xl">
                    ROSI ist die moderne SaaS-Plattform fuer Tankstellenpartner.
                    Mitarbeiter, Kunden, Lieferanten, Dokumente und Schichtplaene &ndash;
                    alles an einem Ort.
                </p>

                {{-- CTA Buttons --}}
                <div class="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                    <a href="{{ route('register') }}"
                       class="group flex items-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-8 py-3.5 text-base font-semibold text-white shadow-xl shadow-primary-600/25 transition-all hover:from-primary-700 hover:to-primary-800 hover:shadow-2xl hover:shadow-primary-600/30">
                        Jetzt kostenlos starten
                        <svg class="h-4 w-4 transition-transform group-hover:translate-x-1" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                    <a href="#features"
                       class="flex items-center gap-2 rounded-xl border border-gray-200 bg-white px-8 py-3.5 text-base font-semibold text-gray-700 shadow-sm transition-all hover:border-primary-300 hover:text-primary-700 hover:shadow-md">
                        Mehr erfahren
                    </a>
                </div>

                {{-- Trust Indicators --}}
                <div class="mt-12 flex flex-wrap items-center justify-center gap-x-8 gap-y-3 text-sm text-gray-400">
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        Keine Kreditkarte noetig
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        DSGVO-konform
                    </span>
                    <span class="flex items-center gap-1.5">
                        <svg class="h-4 w-4 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                        </svg>
                        Made in Germany
                    </span>
                </div>
            </div>

            {{-- Dashboard Preview (Placeholder) --}}
            <div class="relative mx-auto mt-16 max-w-5xl">
                <div class="rounded-2xl border border-gray-200 bg-gradient-to-b from-gray-50 to-white p-2 shadow-2xl shadow-gray-300/30">
                    <div class="rounded-xl bg-white">
                        {{-- Browser Chrome --}}
                        <div class="flex items-center gap-2 border-b border-gray-100 px-4 py-3">
                            <div class="flex gap-1.5">
                                <div class="h-3 w-3 rounded-full bg-red-300"></div>
                                <div class="h-3 w-3 rounded-full bg-amber-300"></div>
                                <div class="h-3 w-3 rounded-full bg-green-300"></div>
                            </div>
                            <div class="ml-4 flex-1 rounded-md bg-gray-100 px-3 py-1 text-xs text-gray-400">
                                rosi.de/dashboard
                            </div>
                        </div>
                        {{-- Dashboard Content Mockup --}}
                        <div class="p-6 sm:p-8">
                            <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <div class="rounded-xl border border-gray-100 bg-gradient-to-br from-blue-50 to-white p-4">
                                    <div class="text-xs font-medium text-gray-500">Tankstellen</div>
                                    <div class="mt-1 text-2xl font-bold text-gray-900">12</div>
                                    <div class="mt-1 text-xs text-green-600">+2 diesen Monat</div>
                                </div>
                                <div class="rounded-xl border border-gray-100 bg-gradient-to-br from-green-50 to-white p-4">
                                    <div class="text-xs font-medium text-gray-500">Mitarbeiter</div>
                                    <div class="mt-1 text-2xl font-bold text-gray-900">48</div>
                                    <div class="mt-1 text-xs text-green-600">Alle aktiv</div>
                                </div>
                                <div class="rounded-xl border border-gray-100 bg-gradient-to-br from-amber-50 to-white p-4">
                                    <div class="text-xs font-medium text-gray-500">Kunden</div>
                                    <div class="mt-1 text-2xl font-bold text-gray-900">156</div>
                                    <div class="mt-1 text-xs text-green-600">+8 diese Woche</div>
                                </div>
                                <div class="rounded-xl border border-gray-100 bg-gradient-to-br from-purple-50 to-white p-4">
                                    <div class="text-xs font-medium text-gray-500">Dokumente</div>
                                    <div class="mt-1 text-2xl font-bold text-gray-900">234</div>
                                    <div class="mt-1 text-xs text-blue-600">12 zur Unterschrift</div>
                                </div>
                            </div>
                            {{-- Chart Placeholder --}}
                            <div class="mt-6 flex items-end gap-1.5 rounded-xl border border-gray-100 p-6">
                                @for ($i = 0; $i < 24; $i++)
                                    <div class="flex-1 rounded-t bg-gradient-to-t from-primary-500/80 to-primary-400/60"
                                         style="height: {{ rand(20, 100) }}%"></div>
                                @endfor
                            </div>
                        </div>
                    </div>
                </div>
                {{-- Schatten-Glow --}}
                <div class="absolute -inset-4 -z-10 rounded-3xl bg-gradient-to-r from-primary-100/40 via-accent-100/20 to-primary-100/40 blur-2xl"></div>
            </div>
        </div>
    </section>

    {{-- ===== FEATURES SECTION ===== --}}
    <section id="features" class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            {{-- Section Header --}}
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-widest text-primary-600">Alles was Sie brauchen</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                    Eine Plattform. Alle Funktionen.
                </h2>
                <p class="mt-4 text-lg text-gray-500">
                    Von der Mitarbeiterverwaltung bis zur KI-gestuetzten Dokumentenerstellung &ndash; ROSI deckt alle Bereiche Ihres Tankstellenbetriebs ab.
                </p>
            </div>

            {{-- Features Grid --}}
            <div class="mt-16 grid grid-cols-1 gap-6 sm:grid-cols-2 lg:grid-cols-3">

                {{-- Feature 1: Tankstellen --}}
                <div class="group rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition-all hover:border-primary-200 hover:shadow-lg hover:shadow-primary-100/50">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-blue-100 text-blue-600 transition-colors group-hover:bg-blue-600 group-hover:text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900">Tankstellen-Verwaltung</h3>
                    <p class="mt-2 text-sm text-gray-500">Alle Ihre Standorte auf einen Blick. Stammdaten, Oeffnungszeiten, Services und Mitarbeiterzuordnung zentral verwalten.</p>
                </div>

                {{-- Feature 2: Mitarbeiter --}}
                <div class="group rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition-all hover:border-primary-200 hover:shadow-lg hover:shadow-primary-100/50">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-green-100 text-green-600 transition-colors group-hover:bg-green-600 group-hover:text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 0 0 2.625.372 9.337 9.337 0 0 0 4.121-.952 4.125 4.125 0 0 0-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 0 1 8.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0 1 11.964-3.07M12 6.375a3.375 3.375 0 1 1-6.75 0 3.375 3.375 0 0 1 6.75 0Zm8.25 2.25a2.625 2.625 0 1 1-5.25 0 2.625 2.625 0 0 1 5.25 0Z" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900">Mitarbeiter & Personalbogen</h3>
                    <p class="mt-2 text-sm text-gray-500">Digitaler Personalbogen, Einladung per Link, Tankstellen-Zuordnung und Rollen mit granularen Berechtigungen.</p>
                </div>

                {{-- Feature 3: Dokumente --}}
                <div class="group rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition-all hover:border-primary-200 hover:shadow-lg hover:shadow-primary-100/50">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-purple-100 text-purple-600 transition-colors group-hover:bg-purple-600 group-hover:text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 0 0-3.375-3.375h-1.5A1.125 1.125 0 0 1 13.5 7.125v-1.5a3.375 3.375 0 0 0-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 0 0-9-9Z" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900">Dokumenten-Management</h3>
                    <p class="mt-2 text-sm text-gray-500">Arbeitsvertraege, Kuendigungen und Zeugnisse per Klick erstellen. Mit digitaler Unterschrift und PDF-Export.</p>
                </div>

                {{-- Feature 4: CRM --}}
                <div class="group rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition-all hover:border-primary-200 hover:shadow-lg hover:shadow-primary-100/50">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-amber-100 text-amber-600 transition-colors group-hover:bg-amber-600 group-hover:text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M15.75 6a3.75 3.75 0 1 1-7.5 0 3.75 3.75 0 0 1 7.5 0ZM4.501 20.118a7.5 7.5 0 0 1 14.998 0A17.933 17.933 0 0 1 12 21.75c-2.676 0-5.216-.584-7.499-1.632Z" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900">Kunden- & Lieferanten-CRM</h3>
                    <p class="mt-2 text-sm text-gray-500">Kontakte, Kommunikationshistorie, Wiedervorlagen und Preislisten. B2B und B2C Kunden separat verwalten.</p>
                </div>

                {{-- Feature 5: Schichtplanung --}}
                <div class="group rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition-all hover:border-primary-200 hover:shadow-lg hover:shadow-primary-100/50">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-rose-100 text-rose-600 transition-colors group-hover:bg-rose-600 group-hover:text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900">Schichtplanung</h3>
                    <p class="mt-2 text-sm text-gray-500">Dienstplaene per Drag & Drop erstellen. Schichttausch-Boerse fuer Mitarbeiter und automatische Konflikterkennung.</p>
                </div>

                {{-- Feature 6: KI --}}
                <div class="group rounded-2xl border border-gray-100 bg-white p-6 shadow-sm transition-all hover:border-primary-200 hover:shadow-lg hover:shadow-primary-100/50">
                    <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-indigo-100 text-indigo-600 transition-colors group-hover:bg-indigo-600 group-hover:text-white">
                        <svg class="h-6 w-6" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M9.813 15.904 9 18.75l-.813-2.846a4.5 4.5 0 0 0-3.09-3.09L2.25 12l2.846-.813a4.5 4.5 0 0 0 3.09-3.09L9 5.25l.813 2.846a4.5 4.5 0 0 0 3.09 3.09L15.75 12l-2.846.813a4.5 4.5 0 0 0-3.09 3.09ZM18.259 8.715 18 9.75l-.259-1.035a3.375 3.375 0 0 0-2.455-2.456L14.25 6l1.036-.259a3.375 3.375 0 0 0 2.455-2.456L18 2.25l.259 1.035a3.375 3.375 0 0 0 2.455 2.456L21.75 6l-1.036.259a3.375 3.375 0 0 0-2.455 2.456ZM16.894 20.567 16.5 21.75l-.394-1.183a2.25 2.25 0 0 0-1.423-1.423L13.5 18.75l1.183-.394a2.25 2.25 0 0 0 1.423-1.423l.394-1.183.394 1.183a2.25 2.25 0 0 0 1.423 1.423l1.183.394-1.183.394a2.25 2.25 0 0 0-1.423 1.423Z" />
                        </svg>
                    </div>
                    <h3 class="mt-4 text-lg font-semibold text-gray-900">KI-Assistent</h3>
                    <p class="mt-2 text-sm text-gray-500">Vertraege und Zeugnisse per KI generieren. Chat-Assistent fuer Fragen zu Arbeitsrecht und Personalmanagement.</p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== HOW IT WORKS ===== --}}
    <section id="how-it-works" class="bg-gradient-to-b from-gray-50 to-white py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-widest text-primary-600">Einfach starten</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                    In 3 Schritten startklar
                </h2>
            </div>

            <div class="mt-16 grid grid-cols-1 gap-12 sm:grid-cols-3">
                {{-- Step 1 --}}
                <div class="text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-500 to-primary-600 text-2xl font-bold text-white shadow-lg shadow-primary-500/25">
                        1
                    </div>
                    <h3 class="mt-6 text-lg font-semibold text-gray-900">Registrieren</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        Erstellen Sie Ihr Konto in unter 2 Minuten. 14 Tage kostenlos testen &ndash; keine Kreditkarte noetig.
                    </p>
                </div>

                {{-- Step 2 --}}
                <div class="text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-primary-500 to-accent-600 text-2xl font-bold text-white shadow-lg shadow-primary-500/25">
                        2
                    </div>
                    <h3 class="mt-6 text-lg font-semibold text-gray-900">Einrichten</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        Fuegen Sie Ihre Tankstellen hinzu und laden Sie Ihre Mitarbeiter per Link ein. Alles ist sofort einsatzbereit.
                    </p>
                </div>

                {{-- Step 3 --}}
                <div class="text-center">
                    <div class="mx-auto flex h-16 w-16 items-center justify-center rounded-2xl bg-gradient-to-br from-accent-500 to-accent-600 text-2xl font-bold text-white shadow-lg shadow-accent-500/25">
                        3
                    </div>
                    <h3 class="mt-6 text-lg font-semibold text-gray-900">Loslegen</h3>
                    <p class="mt-2 text-sm text-gray-500">
                        Verwalten Sie Mitarbeiter, erstellen Sie Dokumente und planen Sie Schichten &ndash; alles digital, alles einfach.
                    </p>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== DSGVO SECTION ===== --}}
    <section class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 items-center gap-12 lg:grid-cols-2">
                {{-- Text --}}
                <div>
                    <p class="text-sm font-semibold uppercase tracking-widest text-primary-600">Datenschutz</p>
                    <h2 class="mt-3 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                        DSGVO-konform. Von Anfang an.
                    </h2>
                    <p class="mt-4 text-lg text-gray-500">
                        Ihre Daten und die Ihrer Mitarbeiter sind bei uns sicher. ROSI wurde von Grund auf nach den strengen Anforderungen der DSGVO entwickelt.
                    </p>

                    <ul class="mt-8 space-y-4">
                        <li class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-100">
                                <svg class="h-3.5 w-3.5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                            <div>
                                <span class="font-medium text-gray-900">AES-256 Verschluesselung</span>
                                <p class="text-sm text-gray-500">Sensible Daten wie Bankverbindungen und Sozialversicherungsnummern werden verschluesselt gespeichert.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-100">
                                <svg class="h-3.5 w-3.5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                            <div>
                                <span class="font-medium text-gray-900">Lueckenloses Audit-Log</span>
                                <p class="text-sm text-gray-500">Jede Datenveraenderung wird protokolliert &ndash; wer, wann, was. Vollstaendige Nachvollziehbarkeit.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-100">
                                <svg class="h-3.5 w-3.5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                            <div>
                                <span class="font-medium text-gray-900">Betroffenenrechte</span>
                                <p class="text-sm text-gray-500">Datenexport, Berichtigung und Loeschung per Knopfdruck. Art. 15-21 DSGVO vollstaendig umgesetzt.</p>
                            </div>
                        </li>
                        <li class="flex items-start gap-3">
                            <div class="mt-0.5 flex h-6 w-6 shrink-0 items-center justify-center rounded-full bg-green-100">
                                <svg class="h-3.5 w-3.5 text-green-600" fill="none" viewBox="0 0 24 24" stroke-width="3" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                            </div>
                            <div>
                                <span class="font-medium text-gray-900">Mandantentrennung</span>
                                <p class="text-sm text-gray-500">Vollstaendige Datenisolation zwischen Mandanten. Kein Partner sieht jemals fremde Daten.</p>
                            </div>
                        </li>
                    </ul>
                </div>

                {{-- Illustration --}}
                <div class="flex justify-center">
                    <div class="relative">
                        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-xl">
                            <div class="flex items-center gap-3 border-b border-gray-100 pb-6">
                                <div class="flex h-12 w-12 items-center justify-center rounded-xl bg-primary-100">
                                    <svg class="h-6 w-6 text-primary-600" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75m-3-7.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.749c0 5.592 3.824 10.29 9 11.623 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.571-.598-3.751h-.152c-3.196 0-6.1-1.248-8.25-3.285Z" />
                                    </svg>
                                </div>
                                <div>
                                    <div class="font-semibold text-gray-900">Datenschutz-Status</div>
                                    <div class="text-sm text-green-600">Alle Anforderungen erfuellt</div>
                                </div>
                            </div>
                            <div class="mt-6 space-y-4">
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Verschluesselung</span>
                                    <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Aktiv</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Audit-Logging</span>
                                    <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Aktiv</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Einwilligungen</span>
                                    <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Vollstaendig</span>
                                </div>
                                <div class="flex items-center justify-between">
                                    <span class="text-sm text-gray-600">Daten-Aufbewahrung</span>
                                    <span class="rounded-full bg-green-100 px-2.5 py-0.5 text-xs font-medium text-green-700">Konform</span>
                                </div>
                            </div>
                        </div>
                        <div class="absolute -inset-4 -z-10 rounded-3xl bg-gradient-to-r from-primary-100/40 to-accent-100/40 blur-2xl"></div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== PRICING SECTION ===== --}}
    <section id="pricing" class="bg-gradient-to-b from-gray-50 to-white py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="mx-auto max-w-2xl text-center">
                <p class="text-sm font-semibold uppercase tracking-widest text-primary-600">Faire Preise</p>
                <h2 class="mt-3 text-3xl font-bold tracking-tight text-gray-900 sm:text-4xl">
                    Starten Sie mit dem kostenlosen Trial
                </h2>
                <p class="mt-4 text-lg text-gray-500">
                    14 Tage vollen Zugang. Keine Kreditkarte. Keine versteckten Kosten. Preismodelle kommen bald.
                </p>
            </div>

            <div class="mx-auto mt-16 max-w-lg">
                <div class="relative rounded-2xl border-2 border-primary-500 bg-white p-8 shadow-xl shadow-primary-100/50">
                    {{-- Badge --}}
                    <div class="absolute -top-4 left-1/2 -translate-x-1/2">
                        <span class="rounded-full bg-primary-600 px-4 py-1 text-sm font-semibold text-white shadow-lg">
                            Jetzt starten
                        </span>
                    </div>

                    <div class="text-center">
                        <h3 class="text-xl font-bold text-gray-900">14-Tage-Trial</h3>
                        <div class="mt-4 flex items-baseline justify-center gap-1">
                            <span class="text-5xl font-extrabold tracking-tight text-gray-900">0</span>
                            <span class="text-2xl font-bold text-gray-400">&euro;</span>
                        </div>
                        <p class="mt-1 text-sm text-gray-500">Voller Zugang, keine Einschraenkungen</p>
                    </div>

                    <ul class="mt-8 space-y-3">
                        @foreach ([
                            'Alle Funktionen freigeschaltet',
                            'Unbegrenzt Tankstellen & Mitarbeiter',
                            'Dokumenten-Management & KI',
                            'E-Mail-Support inklusive',
                            'DSGVO-konforme Datenhaltung',
                            'Kein automatisches Abo',
                        ] as $feature)
                            <li class="flex items-center gap-3 text-sm text-gray-600">
                                <svg class="h-5 w-5 shrink-0 text-primary-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m4.5 12.75 6 6 9-13.5" />
                                </svg>
                                {{ $feature }}
                            </li>
                        @endforeach
                    </ul>

                    <a href="{{ route('register') }}"
                       class="mt-8 flex w-full items-center justify-center gap-2 rounded-xl bg-gradient-to-r from-primary-600 to-primary-700 px-6 py-3 text-base font-semibold text-white shadow-lg shadow-primary-600/25 transition-all hover:from-primary-700 hover:to-primary-800">
                        Kostenlos registrieren
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2.5" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                        </svg>
                    </a>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== CTA SECTION ===== --}}
    <section class="py-24 sm:py-32">
        <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
            <div class="relative overflow-hidden rounded-3xl bg-gradient-to-r from-primary-600 via-primary-700 to-accent-600 px-8 py-16 text-center shadow-2xl sm:px-16 sm:py-20">
                {{-- Dekorative Elemente --}}
                <div class="absolute -top-20 -right-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>
                <div class="absolute -bottom-20 -left-20 h-64 w-64 rounded-full bg-white/10 blur-3xl"></div>

                <div class="relative">
                    <h2 class="text-3xl font-bold tracking-tight text-white sm:text-4xl">
                        Bereit, Ihre Tankstellen digital zu verwalten?
                    </h2>
                    <p class="mx-auto mt-4 max-w-xl text-lg text-primary-100">
                        Starten Sie heute mit ROSI und erleben Sie, wie einfach Tankstellen-Management sein kann.
                    </p>
                    <div class="mt-10 flex flex-col items-center gap-4 sm:flex-row sm:justify-center">
                        <a href="{{ route('register') }}"
                           class="rounded-xl bg-white px-8 py-3.5 text-base font-semibold text-primary-700 shadow-lg transition hover:bg-primary-50">
                            14 Tage kostenlos testen
                        </a>
                        <a href="{{ route('login') }}"
                           class="rounded-xl border border-white/30 px-8 py-3.5 text-base font-semibold text-white transition hover:border-white/60 hover:bg-white/10">
                            Ich habe bereits ein Konto
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </section>

    {{-- ===== FOOTER ===== --}}
    <footer class="border-t border-gray-100 bg-gray-50">
        <div class="mx-auto max-w-7xl px-4 py-12 sm:px-6 lg:px-8">
            <div class="grid grid-cols-1 gap-8 sm:grid-cols-2 lg:grid-cols-4">
                {{-- Logo & Beschreibung --}}
                <div class="sm:col-span-2 lg:col-span-1">
                    <div class="flex items-center gap-2.5">
                        <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-gradient-to-br from-primary-500 to-accent-600">
                            <svg class="h-5 w-5 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21" />
                            </svg>
                        </div>
                        <span class="text-lg font-bold text-gray-900">ROSI</span>
                    </div>
                    <p class="mt-3 text-sm text-gray-500">
                        Die moderne SaaS-Plattform fuer Tankstellenpartner. Digital, sicher, DSGVO-konform.
                    </p>
                </div>

                {{-- Produkt --}}
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Produkt</h4>
                    <ul class="mt-4 space-y-2">
                        <li><a href="#features" class="text-sm text-gray-500 transition hover:text-primary-600">Features</a></li>
                        <li><a href="#pricing" class="text-sm text-gray-500 transition hover:text-primary-600">Preise</a></li>
                        <li><a href="#" class="text-sm text-gray-500 transition hover:text-primary-600">Updates</a></li>
                    </ul>
                </div>

                {{-- Rechtliches --}}
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Rechtliches</h4>
                    <ul class="mt-4 space-y-2">
                        <li><a href="#" class="text-sm text-gray-500 transition hover:text-primary-600">Datenschutz</a></li>
                        <li><a href="#" class="text-sm text-gray-500 transition hover:text-primary-600">Impressum</a></li>
                        <li><a href="#" class="text-sm text-gray-500 transition hover:text-primary-600">AGB</a></li>
                    </ul>
                </div>

                {{-- Kontakt --}}
                <div>
                    <h4 class="text-sm font-semibold text-gray-900">Kontakt</h4>
                    <ul class="mt-4 space-y-2">
                        <li><a href="#" class="text-sm text-gray-500 transition hover:text-primary-600">Support</a></li>
                        <li><a href="#" class="text-sm text-gray-500 transition hover:text-primary-600">E-Mail</a></li>
                    </ul>
                </div>
            </div>

            <div class="mt-12 border-t border-gray-200 pt-6 text-center text-sm text-gray-400">
                &copy; {{ date('Y') }} ROSI. Alle Rechte vorbehalten. Made with &#10084; in Germany.
            </div>
        </div>
    </footer>

</body>
</html>
