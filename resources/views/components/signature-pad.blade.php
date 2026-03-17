@php
    $height = $getHeight();
    $confirmationText = $getConfirmationText();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            isDrawing: false,
            canvas: null,
            ctx: null,
            lastX: 0,
            lastY: 0,
            hasSignature: false,

            init() {
                this.canvas = this.$refs.canvas;
                this.ctx = this.canvas.getContext('2d');
                this.resizeCanvas();

                // Bestehende Signatur laden
                const existing = $wire.get('{{ $statePath }}');
                if (existing) {
                    const img = new Image();
                    img.onload = () => {
                        this.ctx.drawImage(img, 0, 0);
                        this.hasSignature = true;
                    };
                    img.src = existing;
                }
            },

            resizeCanvas() {
                const rect = this.canvas.parentElement.getBoundingClientRect();
                this.canvas.width = rect.width;
                this.canvas.height = {{ $height }};
                this.ctx.strokeStyle = '#1e293b';
                this.ctx.lineWidth = 2;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
            },

            getPos(e) {
                const rect = this.canvas.getBoundingClientRect();
                const touch = e.touches ? e.touches[0] : e;
                return {
                    x: touch.clientX - rect.left,
                    y: touch.clientY - rect.top
                };
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
                    $wire.set('{{ $statePath }}', this.canvas.toDataURL('image/png'));
                }
            },

            clear() {
                this.ctx.clearRect(0, 0, this.canvas.width, this.canvas.height);
                this.hasSignature = false;
                $wire.set('{{ $statePath }}', null);
            }
        }"
        class="space-y-2"
    >
        @if($confirmationText)
            <p class="text-sm text-gray-600 italic">{{ $confirmationText }}</p>
        @endif

        <div class="relative rounded-lg border-2 border-dashed border-gray-300 bg-white overflow-hidden"
             :class="{ 'border-primary-400': isDrawing }">
            <canvas
                x-ref="canvas"
                class="w-full cursor-crosshair touch-none"
                style="height: {{ $height }}px"
                @mousedown="startDrawing($event)"
                @mousemove="draw($event)"
                @mouseup="stopDrawing()"
                @mouseleave="stopDrawing()"
                @touchstart="startDrawing($event)"
                @touchmove="draw($event)"
                @touchend="stopDrawing()"
            ></canvas>

            {{-- Hint wenn leer --}}
            <div x-show="!hasSignature" class="pointer-events-none absolute inset-0 flex items-center justify-center">
                <span class="text-sm text-gray-400">Hier unterschreiben (Maus oder Touch)</span>
            </div>
        </div>

        <div class="flex justify-end">
            <button
                type="button"
                @click="clear()"
                class="inline-flex items-center gap-1 rounded-md px-3 py-1.5 text-xs font-medium text-gray-600 hover:text-red-600 hover:bg-red-50 transition"
            >
                <svg class="h-3.5 w-3.5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                </svg>
                Unterschrift loeschen
            </button>
        </div>
    </div>
</x-dynamic-component>
