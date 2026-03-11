<div
    x-data="{
        hours: 0,
        init() {
            this.$nextTick(() => {
                this.calculate();
                // Auf alle time-Input-Aenderungen im gesamten Formular lauschen
                document.addEventListener('change', (e) => {
                    if (e.target && e.target.type === 'time') {
                        this.calculate();
                    }
                });
                document.addEventListener('input', (e) => {
                    if (e.target && e.target.type === 'time') {
                        this.calculate();
                    }
                });
                // Nach Livewire DOM-Update neu berechnen (z.B. 24h-Toggle)
                Livewire.hook('morph.updated', ({ el, component }) => {
                    this.$nextTick(() => this.calculate());
                });
            });
        },
        calculate() {
            const timeInputs = document.querySelectorAll('input[type=time]');

            // Keine Zeitfelder im DOM = 24h-Modus aktiv → 168 Stunden
            if (timeInputs.length === 0) {
                this.hours = 168;
                return;
            }

            let totalMinutes = 0;

            // Time-Inputs kommen paarweise (open, close) pro Tag
            for (let i = 0; i < timeInputs.length - 1; i += 2) {
                const openVal = timeInputs[i].value;
                const closeVal = timeInputs[i + 1].value;

                if (openVal && closeVal) {
                    const [openH, openM] = openVal.split(':').map(Number);
                    const [closeH, closeM] = closeVal.split(':').map(Number);
                    const diff = (closeH * 60 + closeM) - (openH * 60 + openM);
                    if (diff > 0) {
                        totalMinutes += diff;
                    }
                }
            }

            this.hours = Math.round(totalMinutes / 60 * 10) / 10;
        }
    }"
    class="mt-2 mb-4"
>
    <div style="display:inline-flex;align-items:center;gap:8px;border-radius:8px;background-color:rgb(238,242,255);padding:8px 16px;font-size:14px;font-weight:500;color:rgb(67,56,202);border:1px solid rgb(199,210,254);">
        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20" style="width:20px;height:20px;min-width:20px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <span>Oeffnungsstunden pro Woche: <strong x-text="hours"></strong> Stunden</span>
    </div>
</div>
