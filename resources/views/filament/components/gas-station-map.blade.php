<div
    x-data="{
        lat: $wire.get('data.latitude') || null,
        lng: $wire.get('data.longitude') || null,
        map: null,
        marker: null,
        init() {
            this.$nextTick(() => {
                const startLat = this.lat || 51.1657;
                const startLng = this.lng || 10.4515;
                const startZoom = this.lat ? 15 : 6;

                this.map = L.map(this.$refs.map).setView([startLat, startLng], startZoom);

                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '&copy; <a href=&quot;https://www.openstreetmap.org/copyright&quot;>OpenStreetMap</a>',
                    maxZoom: 19
                }).addTo(this.map);

                if (this.lat && this.lng) {
                    this.marker = L.marker([this.lat, this.lng]).addTo(this.map);
                }

                this.map.on('click', (e) => {
                    this.setCoordinates(e.latlng.lat, e.latlng.lng);
                });

                // Auf Aenderungen der Koordinaten-Felder reagieren
                $wire.on('updateMapMarker', (data) => {
                    if (data.lat && data.lng) {
                        this.setCoordinates(parseFloat(data.lat), parseFloat(data.lng));
                    }
                });
            });
        },
        setCoordinates(lat, lng) {
            this.lat = lat;
            this.lng = lng;
            $wire.set('data.latitude', lat.toFixed(8));
            $wire.set('data.longitude', lng.toFixed(8));

            if (this.marker) {
                this.marker.setLatLng([lat, lng]);
            } else {
                this.marker = L.marker([lat, lng]).addTo(this.map);
            }

            this.map.setView([lat, lng], Math.max(this.map.getZoom(), 15));
        },
        async geocodeAddress() {
            const street = $wire.get('data.street') || '';
            const houseNumber = $wire.get('data.house_number') || '';
            const zip = $wire.get('data.zip') || '';
            const city = $wire.get('data.city') || '';

            if (!street && !city) {
                return;
            }

            const query = encodeURIComponent(
                `${street} ${houseNumber}, ${zip} ${city}, Deutschland`
            );

            try {
                const response = await fetch(
                    `https://nominatim.openstreetmap.org/search?q=${query}&format=json&limit=1`,
                    { headers: { 'Accept-Language': 'de' } }
                );
                const data = await response.json();

                if (data.length > 0) {
                    this.setCoordinates(parseFloat(data[0].lat), parseFloat(data[0].lon));
                } else {
                    alert('Adresse konnte nicht gefunden werden.');
                }
            } catch (error) {
                alert('Geocoding fehlgeschlagen. Bitte versuchen Sie es erneut.');
            }
        }
    }"
    class="space-y-3"
>
    @once
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
    @endonce

    <div x-ref="map" style="height: 400px; width: 100%; border-radius: 0.5rem; z-index: 0; border: 1px solid rgb(209, 213, 219);"></div>

    <div class="flex items-center gap-3">
        <button
            type="button"
            x-on:click="geocodeAddress()"
            class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm"
            style="background-color: rgb(79, 70, 229); color: white;"
        >
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16" style="width:16px;height:16px;min-width:16px;">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" />
                <path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" />
            </svg>
            Von Adresse uebernehmen
        </button>

        <span class="text-xs text-gray-500" x-show="lat && lng" x-text="'Koordinaten: ' + (lat ? parseFloat(lat).toFixed(6) : '') + ', ' + (lng ? parseFloat(lng).toFixed(6) : '')"></span>
    </div>

    <p class="text-xs text-gray-400">Klicken Sie auf die Karte, um die Position zu setzen. Daten: &copy; OpenStreetMap</p>
</div>
