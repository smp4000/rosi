<div
    x-data="{
        status: '',
        statusColor: '',
        is24h: false,
        init() {
            this.$nextTick(() => {
                this.updateStatus();
                setInterval(() => this.updateStatus(), 60000);
                document.addEventListener('change', (e) => {
                    if (e.target && (e.target.type === 'time' || e.target.type === 'checkbox')) {
                        this.updateStatus();
                    }
                });
            });
        },
        updateStatus() {
            this.is24h = !!$wire.get('data.opening_hours.is_24h');

            if (this.is24h) {
                this.status = 'Rund um die Uhr geoeffnet (24/7)';
                this.statusColor = 'green';
                return;
            }

            const dayNames = ['sunday', 'monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday'];
            const todayIndex = new Date().getDay();
            const today = dayNames[todayIndex];
            const dayOrder = ['monday', 'tuesday', 'wednesday', 'thursday', 'friday', 'saturday', 'sunday'];
            const domIndex = dayOrder.indexOf(today);

            const timeInputs = document.querySelectorAll('input[type=time]');
            const openInput = timeInputs[domIndex * 2];
            const closeInput = timeInputs[domIndex * 2 + 1];

            if (!openInput || !closeInput || !openInput.value || !closeInput.value) {
                this.status = '';
                this.statusColor = '';
                return;
            }

            const now = new Date();
            const currentMinutes = now.getHours() * 60 + now.getMinutes();
            const [openH, openM] = openInput.value.split(':').map(Number);
            const [closeH, closeM] = closeInput.value.split(':').map(Number);
            const openMinutes = openH * 60 + openM;
            const closeMinutes = closeH * 60 + closeM;

            if (currentMinutes >= openMinutes && currentMinutes < closeMinutes) {
                const remaining = closeMinutes - currentMinutes;
                const h = Math.floor(remaining / 60);
                const m = remaining % 60;
                let timeStr = '';
                if (h > 0) timeStr += h + ' Std ';
                timeStr += m + ' Min';
                this.status = 'Geoeffnet \u2013 schliesst in ' + timeStr;
                this.statusColor = 'green';
            } else {
                let nextOpen = '';
                if (currentMinutes < openMinutes) {
                    const remaining = openMinutes - currentMinutes;
                    const h = Math.floor(remaining / 60);
                    const m = remaining % 60;
                    let timeStr = '';
                    if (h > 0) timeStr += h + ' Std ';
                    timeStr += m + ' Min';
                    nextOpen = ' \u2013 oeffnet in ' + timeStr;
                }
                this.status = 'Geschlossen' + nextOpen;
                this.statusColor = 'red';
            }
        }
    }"
    x-show="status"
    x-cloak
    class="mb-4"
>
    <div
        style="display:inline-flex;align-items:center;gap:8px;border-radius:8px;padding:8px 16px;font-size:14px;font-weight:500;"
        :style="{
            'background-color': statusColor === 'green' ? 'rgb(240,253,244)' : 'rgb(254,242,242)',
            'color': statusColor === 'green' ? 'rgb(21,128,61)' : 'rgb(185,28,28)',
            'border': statusColor === 'green' ? '1px solid rgb(187,247,208)' : '1px solid rgb(254,202,202)'
        }"
    >
        <svg x-show="statusColor === 'green'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20" style="width:20px;height:20px;min-width:20px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <svg x-show="statusColor === 'red'" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="20" height="20" style="width:20px;height:20px;min-width:20px;">
            <path stroke-linecap="round" stroke-linejoin="round" d="m9.75 9.75 4.5 4.5m0-4.5-4.5 4.5M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
        </svg>
        <span x-text="status"></span>
    </div>
</div>
