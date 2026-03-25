@php
    $height = $getHeight();
    $confirmationText = $getConfirmationText();
    $statePath = $getStatePath();
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        x-data="{
            isDrawing: false,
            ctx: null,
            lastX: 0,
            lastY: 0,
            hasSignature: false,
            ready: false,

            init() {
                const c = this.$refs.sigCanvas;
                if (!c) return;

                // Sofort Context holen ohne width/height zu aendern (wuerde Canvas loeschen)
                this.ctx = c.getContext('2d');
                this.ctx.strokeStyle = '#1e293b';
                this.ctx.lineWidth = 2.5;
                this.ctx.lineCap = 'round';
                this.ctx.lineJoin = 'round';
                this.ready = true;

                const existing = $wire.get('{{ $statePath }}');
                if (existing) {
                    const img = new Image();
                    img.onload = () => { this.ctx.drawImage(img, 0, 0); this.hasSignature = true; };
                    img.src = existing;
                }
            },

            getPos(e) {
                const c = this.$refs.sigCanvas;
                const rect = c.getBoundingClientRect();
                const t = e.touches ? e.touches[0] : e;
                const scaleX = c.width / rect.width;
                const scaleY = c.height / rect.height;
                return { x: (t.clientX - rect.left) * scaleX, y: (t.clientY - rect.top) * scaleY };
            },
            startDrawing(e) { if (!this.ready) this.setup(); e.preventDefault(); this.isDrawing = true; const p = this.getPos(e); this.lastX = p.x; this.lastY = p.y; },
            draw(e) { if (!this.isDrawing) return; e.preventDefault(); const p = this.getPos(e); this.ctx.beginPath(); this.ctx.moveTo(this.lastX, this.lastY); this.ctx.lineTo(p.x, p.y); this.ctx.stroke(); this.lastX = p.x; this.lastY = p.y; this.hasSignature = true; },
            stopDrawing() { this.isDrawing = false; if (this.hasSignature) { $wire.set('{{ $statePath }}', this.$refs.sigCanvas.toDataURL('image/png')); } },
            clear() { this.ctx.clearRect(0, 0, this.$refs.sigCanvas.width, this.$refs.sigCanvas.height); this.hasSignature = false; $wire.set('{{ $statePath }}', null); }
        }"
    >
        @if($confirmationText)
            <p style="font-size: 13px; color: #6b7280; font-style: italic; margin-bottom: 8px;">{{ $confirmationText }}</p>
        @endif

        {{-- Zeichenflaeche --}}
        <div style="width: 100%; padding: 0; margin: 0;">
            <canvas
                x-ref="sigCanvas"
                width="500"
                height="{{ $height }}"
                style="display: block; width: 100%; height: {{ $height }}px; border: 2px solid #9ca3af; border-radius: 8px; background: #f8fafc; cursor: crosshair; touch-action: none;"
                x-bind:style="isDrawing ? 'display: block; width: 100%; height: {{ $height }}px; border: 2px solid #6366f1; border-radius: 8px; background: #ffffff; cursor: crosshair; touch-action: none;' : 'display: block; width: 100%; height: {{ $height }}px; border: 2px solid #9ca3af; border-radius: 8px; background: #f8fafc; cursor: crosshair; touch-action: none;'"
                @mousedown="startDrawing($event)"
                @mousemove="draw($event)"
                @mouseup="stopDrawing()"
                @mouseleave="stopDrawing()"
                @touchstart="startDrawing($event)"
                @touchmove="draw($event)"
                @touchend="stopDrawing()"
            ></canvas>
        </div>

        {{-- Hint + Loeschen --}}
        <div style="display: flex; justify-content: space-between; align-items: center; margin-top: 6px;">
            <span x-show="!hasSignature" style="font-size: 12px; color: #9ca3af;">Hier unterschreiben (Maus oder Touch)</span>
            <span x-show="hasSignature" style="font-size: 12px; color: #9ca3af;">&nbsp;</span>
            <button
                type="button"
                @click="clear()"
                style="font-size: 12px; color: #6b7280; background: none; border: 1px solid #d1d5db; border-radius: 4px; padding: 2px 8px; cursor: pointer;"
            >✕ Loeschen</button>
        </div>
    </div>
</x-dynamic-component>
