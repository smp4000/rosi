<div>
    {{-- ===== FEHLER-ZUSTAENDE ===== --}}
    @if($notFound)
        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-xl text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-red-100">
                <svg class="h-8 w-8 text-red-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126ZM12 15.75h.007v.008H12v-.008Z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Dokument nicht gefunden</h2>
            <p class="mt-2 text-gray-500">Dieser Link ist ungueltig. Bitte kontaktieren Sie Ihren Arbeitgeber.</p>
        </div>
    @elseif($expired)
        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-xl text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-amber-100">
                <svg class="h-8 w-8 text-amber-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Link abgelaufen</h2>
            <p class="mt-2 text-gray-500">Dieser Signatur-Link ist abgelaufen. Bitte bitten Sie Ihren Arbeitgeber, einen neuen Link zu senden.</p>
        </div>
    @elseif($alreadySigned)
        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-xl text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                <svg class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Bereits unterschrieben</h2>
            <p class="mt-2 text-gray-500">Das Dokument <strong>{{ $documentTitle }}</strong> wurde bereits unterschrieben.</p>
        </div>

    {{-- ===== ERFOLGREICH UNTERSCHRIEBEN ===== --}}
    @elseif($signed)
        <div class="rounded-2xl border border-gray-200 bg-white p-8 shadow-xl text-center">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full bg-green-100">
                <svg class="h-8 w-8 text-green-500" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                </svg>
            </div>
            <h2 class="text-xl font-bold text-gray-900">Vielen Dank!</h2>
            <p class="mt-2 text-gray-500">
                Das Dokument <strong>{{ $documentTitle }}</strong> wurde erfolgreich unterschrieben.<br>
                Ihr Arbeitgeber wird benachrichtigt.
            </p>
        </div>

    {{-- ===== DOKUMENT ANZEIGEN + UNTERSCHRIFT ===== --}}
    @else
        <div class="space-y-6">
            {{-- Header --}}
            <div class="rounded-2xl border border-gray-200 bg-white px-8 py-6 shadow-xl">
                <div class="flex items-center justify-between">
                    <div>
                        <h1 class="text-2xl font-bold text-gray-900">{{ $documentTitle }}</h1>
                        @if($companyName)
                            <p class="mt-1 text-gray-500">{{ $companyName }}</p>
                        @endif
                    </div>
                    <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-100 px-3 py-1 text-sm font-medium text-amber-800">
                        <svg class="h-4 w-4" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                        </svg>
                        Unterschrift erforderlich
                    </span>
                </div>
            </div>

            {{-- Dokument-Inhalt --}}
            <div class="rounded-2xl border border-gray-200 bg-white shadow-xl">
                <div class="border-b border-gray-100 px-8 py-4">
                    <h2 class="font-semibold text-gray-700">Dokumentinhalt</h2>
                </div>
                <div class="prose prose-sm max-w-none px-8 py-6">
                    {!! $documentContent !!}
                </div>
            </div>

            {{-- Signatur-Bereich --}}
            @if($requiresSignature)
                <div class="rounded-2xl border border-gray-200 bg-white px-8 py-6 shadow-xl">
                    <h2 class="mb-4 text-lg font-bold text-gray-900">Ihre Unterschrift</h2>

                    <div
                        x-data="{
                            isDrawing: false,
                            canvas: null,
                            ctx: null,
                            lastX: 0,
                            lastY: 0,
                            hasSignature: false,

                            init() {
                                this.canvas = this.$refs.signatureCanvas;
                                this.ctx = this.canvas.getContext('2d');
                                this.resizeCanvas();
                            },

                            resizeCanvas() {
                                const rect = this.canvas.parentElement.getBoundingClientRect();
                                this.canvas.width = rect.width;
                                this.canvas.height = 150;
                                this.ctx.strokeStyle = '#1e293b';
                                this.ctx.lineWidth = 2;
                                this.ctx.lineCap = 'round';
                                this.ctx.lineJoin = 'round';
                            },

                            getPos(e) {
                                const rect = this.canvas.getBoundingClientRect();
                                const touch = e.touches ? e.touches[0] : e;
                                return { x: touch.clientX - rect.left, y: touch.clientY - rect.top };
                            },

                            startDrawing(e) {
                                e.preventDefault();
                                this.isDrawing = true;
                                const pos = this.getPos(e);
                                this.lastX = pos.x;
                                this.lastY = pos.y;
                            },

                            draw(e) {
                                if (!this.isDrawing) return;
                                e.preventDefault();
                                const pos = this.getPos(e);
                                this.ctx.beginPath();
                                this.ctx.moveTo(this.lastX, this.lastY);
                                this.ctx.lineTo(pos.x, pos.y);
                                this.ctx.stroke();
                                this.lastX = pos.x;
                                this.lastY = pos.y;
                                this.hasSignature = true;
                            },

                            stopDrawing() {
                                this.isDrawing = false;
                                if (this.hasSignature) {
                                    @this.set('signatureData', this.canvas.toDataURL('image/png'));
                                }
                            },

                            clear() {
                                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                                this.hasSignature = false;
                                @this.set('signatureData', null);
                            }
                        }"
                    >
                        {{-- Canvas --}}
                        <div class="relative rounded-lg border-2 border-dashed border-gray-300 bg-gray-50 overflow-hidden"
                             :class="{ 'border-blue-400 bg-white': isDrawing }">
                            <canvas
                                x-ref="signatureCanvas"
                                class="w-full cursor-crosshair touch-none"
                                style="height: 150px"
                                @mousedown="startDrawing($event)"
                                @mousemove="draw($event)"
                                @mouseup="stopDrawing()"
                                @mouseleave="stopDrawing()"
                                @touchstart="startDrawing($event)"
                                @touchmove="draw($event)"
                                @touchend="stopDrawing()"
                            ></canvas>
                            <div x-show="!hasSignature" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                                <span class="text-sm text-gray-400">Hier unterschreiben (Maus oder Touch)</span>
                            </div>
                        </div>

                        {{-- Loeschen-Button --}}
                        <div class="mt-2 flex justify-end">
                            <button type="button" @click="clear()"
                                    class="inline-flex items-center gap-1 rounded-md px-3 py-1.5 text-xs font-medium text-gray-500 hover:text-red-600 hover:bg-red-50 transition">
                                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                                </svg>
                                Loeschen
                            </button>
                        </div>
                    </div>
                    @error('signatureData')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    {{-- Bestaetigung --}}
                    <label class="mt-4 flex items-start gap-3 cursor-pointer">
                        <input type="checkbox" wire:model="confirmed"
                               class="mt-0.5 h-5 w-5 rounded border-gray-300 text-blue-600 focus:ring-blue-500">
                        <span class="text-sm text-gray-700">
                            Ich bestaettige, dass ich das Dokument vollstaendig gelesen habe und mit meiner digitalen Unterschrift rechtsverbindlich unterzeichne.
                        </span>
                    </label>
                    @error('confirmed')
                        <p class="mt-1 text-sm text-red-600">{{ $message }}</p>
                    @enderror

                    {{-- Abschicken --}}
                    <div class="mt-6">
                        <button wire:click="submitSignature" wire:loading.attr="disabled"
                                class="inline-flex w-full items-center justify-center gap-2 rounded-xl bg-[#2563eb] px-6 py-3 text-base font-bold text-white shadow-lg hover:bg-[#1d4ed8] focus:ring-4 focus:ring-blue-200 disabled:opacity-50 transition">
                            <span wire:loading.remove wire:target="submitSignature">
                                <svg class="h-5 w-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                                    <path stroke-linecap="round" stroke-linejoin="round" d="m16.862 4.487 1.687-1.688a1.875 1.875 0 1 1 2.652 2.652L6.832 19.82a4.5 4.5 0 0 1-1.897 1.13l-2.685.8.8-2.685a4.5 4.5 0 0 1 1.13-1.897L16.863 4.487Zm0 0L19.5 7.125" />
                                </svg>
                            </span>
                            <span wire:loading wire:target="submitSignature">
                                <svg class="h-5 w-5 animate-spin" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                                </svg>
                            </span>
                            Unterschreiben & Absenden
                        </button>
                    </div>
                </div>
            @endif
        </div>
    @endif
</div>
