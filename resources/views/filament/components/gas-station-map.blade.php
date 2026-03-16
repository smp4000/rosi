<div
    x-data="gasStationMap($wire)"
    x-on:competitors-updated.window="refreshCompetitors()"
    class="space-y-3"
>
    @once
        <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"
              integrity="sha256-p4NxAoJBhIIN+hmNHrzRCf9tD/miZyoHS5obTRR9BMY=" crossorigin="" />
        <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"
                integrity="sha256-20nQCchB9co0qIjJZRGuk2/Z9VM+kNiyxNV1lvTlZBo=" crossorigin=""></script>
        <style>
            .station-marker-icon, .competitor-marker-icon { background:none!important; border:none!important; }

            /* --- Preis-Tag Styles (wie benzinpreis-aktuell.de) --- */
            .map-price-tag {
                white-space: nowrap; text-align: center; position: relative;
                border-radius: 6px; padding: 0; overflow: visible;
                box-shadow: 0 2px 8px rgba(0,0,0,0.25); font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            }
            .map-price-tag::after {
                content: ''; position: absolute; left: 50%; bottom: -8px; transform: translateX(-50%);
                border-left: 8px solid transparent; border-right: 8px solid transparent;
            }
            .map-price-tag .tag-brand {
                font-size: 10px; font-weight: 700; padding: 3px 10px 1px; border-radius: 6px 6px 0 0;
                letter-spacing: 0.02em; text-transform: uppercase;
            }
            .map-price-tag .tag-price {
                font-size: 14px; font-weight: 800; padding: 1px 10px 4px;
                border-radius: 0 0 6px 6px; letter-spacing: -0.02em;
                font-variant-numeric: tabular-nums;
            }

            /* Eigene Station: Blau */
            .map-price-tag.own-tag { border: 2px solid #4338CA; }
            .map-price-tag.own-tag .tag-brand { background: #4338CA; color: #fff; }
            .map-price-tag.own-tag .tag-price { background: #EEF2FF; color: #312E81; }
            .map-price-tag.own-tag::after { border-top: 8px solid #4338CA; }

            /* Wettbewerber: Gruen (wie Benzinpreis-Seite) */
            .map-price-tag.comp-tag { border: 2px solid #15803d; }
            .map-price-tag.comp-tag .tag-brand { background: #15803d; color: #fff; }
            .map-price-tag.comp-tag .tag-price { background: #f0fdf4; color: #14532d; }
            .map-price-tag.comp-tag::after { border-top: 8px solid #15803d; }

            /* Wettbewerber ohne Preis: Rot */
            .map-price-tag.comp-tag-noprice { border: 2px solid #DC2626; }
            .map-price-tag.comp-tag-noprice .tag-brand { background: #DC2626; color: #fff; }
            .map-price-tag.comp-tag-noprice .tag-price { background: #FEF2F2; color: #991B1B; font-size: 11px; font-weight: 600; }
            .map-price-tag.comp-tag-noprice::after { border-top: 8px solid #DC2626; }

            /* Eigene ohne Preis */
            .map-price-tag.own-tag-noprice { border: 2px solid #4338CA; }
            .map-price-tag.own-tag-noprice .tag-brand { background: #4338CA; color: #fff; }
            .map-price-tag.own-tag-noprice .tag-price { background: #EEF2FF; color: #6366F1; font-size: 11px; font-weight: 600; }
            .map-price-tag.own-tag-noprice::after { border-top: 8px solid #4338CA; }

            /* Layout */
            .map-sidebar-layout {
                display: flex; gap: 0; height: 75vh; min-height: 500px; max-height: 800px;
                border: 1px solid rgb(209,213,219); border-radius: 0.75rem; overflow: hidden;
            }
            .map-sidebar-layout .map-container { flex: 1; min-width: 0; position: relative; }
            .map-sidebar-layout .map-container .leaflet-container { height:100%!important; width:100%!important; }
            .map-sidebar-layout .station-sidebar {
                width: 300px; flex-shrink: 0; border-left: 1px solid rgb(209,213,219);
                background: white; display: flex; flex-direction: column; overflow: hidden;
            }
            .station-list-item { transition: background-color 0.15s ease; cursor: pointer; }
            .station-list-item:hover { background-color: rgba(79,70,229,0.06); }
            .station-list-item.active-own { background-color: rgba(79,70,229,0.1); border-left: 3px solid #4F46E5; }
            .station-list-item.competitor:hover { background-color: rgba(220,38,38,0.06); }
            .station-list-item.active-comp { background-color: rgba(220,38,38,0.1); border-left: 3px solid #DC2626; }

            /* Preisvergleichstabelle */
            .price-compare-table { width: 100%; border-collapse: collapse; font-size: 12px; }
            .price-compare-table th { background: #f9fafb; font-weight: 600; text-align: left; padding: 6px 10px; border-bottom: 2px solid #e5e7eb; font-size: 11px; color: #6B7280; text-transform: uppercase; letter-spacing: 0.03em; }
            .price-compare-table td { padding: 5px 10px; border-bottom: 1px solid #f3f4f6; }
            .price-compare-table tr:hover { background: #fafafa; }
            .price-compare-table .own-row { background: #EEF2FF; }
            .price-compare-table .own-row:hover { background: #E0E7FF; }
            .price-compare-table .price-cell { font-weight: 600; font-variant-numeric: tabular-nums; text-align: right; }
            .price-compare-table .price-lowest { color: #16a34a; }
            .price-compare-table .price-highest { color: #DC2626; }
        </style>
        <script>
            document.addEventListener('alpine:init', function() {
                Alpine.data('gasStationMap', function(wire) {
                    return {
                        lat: wire.get('data.latitude') || null,
                        lng: wire.get('data.longitude') || null,
                        map: null, marker: null, competitorMarkers: [], competitorList: [],
                        activeIndex: -1, ownName: '', ownBrand: '', ownStreet: '', ownCity: '',
                        ownPriceSuper: null, ownPriceE10: null, ownPriceDiesel: null, wire: wire,

                        cleanName: function(name, brand) {
                            if (!brand || !name) return name || '';
                            var lower = name.toLowerCase();
                            var brandLower = brand.toLowerCase();
                            while (lower.indexOf(brandLower) === 0) {
                                name = name.substring(brand.length).trim();
                                lower = name.toLowerCase();
                            }
                            if (lower.indexOf('tankstelle') === 0) {
                                name = name.substring(10).trim();
                            }
                            return name || brand;
                        },

                        init: function() {
                            var self = this;
                            this.ownName = wire.get('data.name') || '';
                            this.ownBrand = '';
                            this.ownStreet = ((wire.get('data.street') || '') + ' ' + (wire.get('data.house_number') || '')).trim();
                            this.ownCity = ((wire.get('data.zip') || '') + ' ' + (wire.get('data.city') || '')).trim();
                            this.ownPriceSuper = wire.get('data.price_super') || null;
                            this.ownPriceE10 = wire.get('data.price_e10') || null;
                            this.ownPriceDiesel = wire.get('data.price_diesel') || null;

                            this.$nextTick(function() {
                                var mapEl = self.$refs.map;
                                var startLat = self.lat || 51.1657;
                                var startLng = self.lng || 10.4515;
                                var startZoom = self.lat ? 14 : 6;
                                self.map = L.map(mapEl).setView([startLat, startLng], startZoom);
                                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                                    attribution: '&copy; OpenStreetMap', maxZoom: 19
                                }).addTo(self.map);

                                if (self.lat && self.lng) {
                                    var ownLabel = self.ownName || 'Eigene Tankstelle';
                                    var ownDiesel = self.ownPriceDiesel;
                                    self.marker = L.marker([self.lat, self.lng], {
                                        icon: self.createPriceTag(ownLabel, ownDiesel, true),
                                        zIndexOffset: 1000
                                    }).addTo(self.map).bindPopup(self.buildOwnPopup());
                                }

                                self.map.on('click', function(e) { self.setCoordinates(e.latlng.lat, e.latlng.lng); });
                                self.loadCompetitors();
                                wire.on('updateMapMarker', function(data) {
                                    if (data.lat && data.lng) self.setCoordinates(parseFloat(data.lat), parseFloat(data.lng));
                                });
                                setTimeout(function() { self.map.invalidateSize(); }, 100);
                                setTimeout(function() { self.map.invalidateSize(); }, 300);
                                setTimeout(function() { self.map.invalidateSize(); }, 600);
                                var ro = new ResizeObserver(function() { self.map.invalidateSize(); });
                                ro.observe(mapEl);
                            });
                        },

                        // Preis-Tag erstellen (wie Benzinpreis-Seite)
                        createPriceTag: function(brandName, dieselPrice, isOwn) {
                            var hasPrice = dieselPrice && parseFloat(dieselPrice) > 0;
                            var tagClass = isOwn
                                ? (hasPrice ? 'own-tag' : 'own-tag-noprice')
                                : (hasPrice ? 'comp-tag' : 'comp-tag-noprice');

                            var priceText = hasPrice
                                ? parseFloat(dieselPrice).toFixed(3).replace('.', ',') + ' \u20ac'
                                : 'kein Preis';

                            var shortBrand = brandName;
                            if (shortBrand.length > 14) shortBrand = shortBrand.substring(0, 14);

                            var html = '<div class="map-price-tag ' + tagClass + '">'
                                + '<div class="tag-brand">' + shortBrand + '</div>'
                                + '<div class="tag-price">' + (hasPrice ? 'DK ' : '') + priceText + '</div>'
                                + '</div>';

                            var textLen = Math.max(shortBrand.length, priceText.length + 3);
                            var w = Math.max(70, textLen * 7 + 24);
                            var h = hasPrice ? 42 : 36;
                            return L.divIcon({
                                html: html,
                                className: '',
                                iconSize: [w, h],
                                iconAnchor: [w / 2, h + 8],
                                popupAnchor: [0, -(h + 8)]
                            });
                        },

                        buildOwnPopup: function() {
                            var priceHtml = '';
                            if (this.ownPriceSuper || this.ownPriceE10 || this.ownPriceDiesel) {
                                priceHtml = '<div style="margin-top:4px;padding-top:4px;border-top:1px solid #eee;font-size:12px;display:flex;gap:8px">';
                                if (this.ownPriceSuper) priceHtml += '<span><span style="color:#888">Super</span><br><strong>' + this.ownPriceSuper + ' \u20ac</strong></span>';
                                if (this.ownPriceE10) priceHtml += '<span><span style="color:#888">E10</span><br><strong style="color:#16a34a">' + this.ownPriceE10 + ' \u20ac</strong></span>';
                                if (this.ownPriceDiesel) priceHtml += '<span><span style="color:#888">Diesel</span><br><strong style="color:#2563eb">' + this.ownPriceDiesel + ' \u20ac</strong></span>';
                                priceHtml += '</div>';
                            }
                            return '<div style="min-width:170px">'
                                + '<div style="font-size:11px;color:#4F46E5;font-weight:600;margin-bottom:2px">Eigene Tankstelle</div>'
                                + '<strong>' + (this.ownName || 'Eigene Tankstelle') + '</strong><br>'
                                + '<span style="color:#666">' + this.ownStreet + ', ' + this.ownCity + '</span>'
                                + priceHtml + '</div>';
                        },

                        displayName: function(brand, name) {
                            var clean = this.cleanName(name, brand);
                            return brand ? brand + ' ' + clean : clean;
                        },

                        loadCompetitors: function() {
                            var self = this;
                            this.competitorMarkers.forEach(function(m) { self.map.removeLayer(m); });
                            this.competitorMarkers = [];
                            this.competitorList = [];

                            // Eigene Preise neu lesen
                            this.ownPriceSuper = wire.get('data.price_super') || null;
                            this.ownPriceE10 = wire.get('data.price_e10') || null;
                            this.ownPriceDiesel = wire.get('data.price_diesel') || null;

                            // Eigenen Marker-Icon aktualisieren
                            if (this.marker) {
                                var ownLabel = this.ownName || 'Eigene Tankstelle';
                                this.marker.setIcon(this.createPriceTag(ownLabel, this.ownPriceDiesel, true));
                                this.marker.setPopupContent(this.buildOwnPopup());
                            }

                            var competitors = wire.get('data.competitors') || {};
                            var bounds = [];
                            if (this.lat && this.lng) bounds.push([parseFloat(this.lat), parseFloat(this.lng)]);

                            var entries = (typeof competitors === 'object' && competitors !== null) ? Object.values(competitors) : [];

                            if (entries.length > 0) {
                                entries.forEach(function(comp, idx) {
                                    var cLat = parseFloat(comp.lat);
                                    var cLng = parseFloat(comp.lng);
                                    var brand = comp.brand || '';
                                    var name = comp.name || 'Unbenannt';
                                    var displayName = self.displayName(brand, name);

                                    self.competitorList.push({
                                        name: name, brand: brand, displayName: displayName,
                                        street: comp.street || '', city: comp.city || '',
                                        distance: comp.distance || null,
                                        lat: cLat || null, lng: cLng || null, priority: idx + 1,
                                        price_e10: comp.price_e10 || null,
                                        price_diesel: comp.price_diesel || null,
                                        price_super: comp.price_super || null
                                    });

                                    if (!cLat || !cLng) return;

                                    var flagLabel = brand || name;

                                    // Popup
                                    var distText = comp.distance ? ' (' + comp.distance + ' km)' : '';
                                    var priceHtml = '';
                                    if (comp.price_e10 || comp.price_diesel || comp.price_super) {
                                        priceHtml = '<div style="margin-top:4px;padding-top:4px;border-top:1px solid #eee;font-size:12px;display:flex;gap:8px">';
                                        if (comp.price_super) priceHtml += '<span><span style="color:#888">Super</span><br><strong>' + comp.price_super + ' \u20ac</strong></span>';
                                        if (comp.price_e10) priceHtml += '<span><span style="color:#888">E10</span><br><strong style="color:#16a34a">' + comp.price_e10 + ' \u20ac</strong></span>';
                                        if (comp.price_diesel) priceHtml += '<span><span style="color:#888">Diesel</span><br><strong style="color:#2563eb">' + comp.price_diesel + ' \u20ac</strong></span>';
                                        priceHtml += '</div>';
                                    }

                                    var popup = '<div style="min-width:170px">'
                                        + '<div style="font-size:11px;color:#DC2626;font-weight:600;margin-bottom:2px">Wettbewerber #' + (idx + 1) + '</div>'
                                        + '<strong>' + displayName + '</strong><br>'
                                        + '<span style="color:#666">' + (comp.street || '') + (comp.city ? ', ' + comp.city : '') + '</span>'
                                        + (distText ? '<br><span style="font-weight:600;color:#DC2626">' + distText + '</span>' : '')
                                        + priceHtml + '</div>';

                                    var marker = L.marker([cLat, cLng], {
                                        icon: self.createPriceTag(flagLabel, comp.price_diesel, false)
                                    }).addTo(self.map).bindPopup(popup);

                                    self.competitorMarkers.push(marker);
                                    bounds.push([cLat, cLng]);
                                });
                            }

                            if (bounds.length > 1) {
                                this.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 13 });
                            }
                        },

                        zoomToOwn: function() {
                            this.activeIndex = -1;
                            if (this.lat && this.lng) {
                                this.map.setView([parseFloat(this.lat), parseFloat(this.lng)], 16);
                                if (this.marker) this.marker.openPopup();
                            }
                        },
                        zoomToCompetitor: function(idx) {
                            this.activeIndex = idx;
                            var comp = this.competitorList[idx];
                            if (comp && comp.lat && comp.lng) {
                                this.map.setView([comp.lat, comp.lng], 16);
                                if (this.competitorMarkers[idx]) this.competitorMarkers[idx].openPopup();
                            }
                        },
                        zoomToAll: function() {
                            this.activeIndex = -2;
                            var bounds = [];
                            if (this.lat && this.lng) bounds.push([parseFloat(this.lat), parseFloat(this.lng)]);
                            this.competitorList.forEach(function(comp) {
                                if (comp.lat && comp.lng) bounds.push([comp.lat, comp.lng]);
                            });
                            if (bounds.length > 1) this.map.fitBounds(bounds, { padding: [50, 50], maxZoom: 13 });
                            else if (bounds.length === 1) this.map.setView(bounds[0], 14);
                        },
                        setCoordinates: function(lat, lng) {
                            this.lat = lat; this.lng = lng;
                            wire.set('data.latitude', lat.toFixed(8));
                            wire.set('data.longitude', lng.toFixed(8));
                            if (this.marker) { this.marker.setLatLng([lat, lng]); }
                            else {
                                this.marker = L.marker([lat, lng], {
                                    icon: this.createPriceTag(this.ownName || 'Eigene', this.ownPriceDiesel, true), zIndexOffset: 1000
                                }).addTo(this.map).bindPopup(this.buildOwnPopup());
                            }
                            this.map.setView([lat, lng], Math.max(this.map.getZoom(), 15));
                        },
                        geocodeAddress: function() {
                            var self = this;
                            var street = wire.get('data.street') || '';
                            var houseNumber = wire.get('data.house_number') || '';
                            var zip = wire.get('data.zip') || '';
                            var city = wire.get('data.city') || '';
                            if (!street && !city) return;
                            var query = encodeURIComponent(street + ' ' + houseNumber + ', ' + zip + ' ' + city + ', Deutschland');
                            fetch('https://nominatim.openstreetmap.org/search?q=' + query + '&format=json&limit=1', {
                                headers: { 'Accept-Language': 'de' }
                            }).then(function(r) { return r.json(); }).then(function(data) {
                                if (data.length > 0) self.setCoordinates(parseFloat(data[0].lat), parseFloat(data[0].lon));
                                else alert('Adresse konnte nicht gefunden werden.');
                            }).catch(function() { alert('Geocoding fehlgeschlagen.'); });
                        },
                        refreshCompetitors: function() { this.loadCompetitors(); },

                        // Preis-Highlighting: niedrigsten/hoechsten pro Spalte finden (inkl. eigene Preise)
                        allPrices: function(field) {
                            var prices = [];
                            // Eigene Preise mit einbeziehen
                            var ownField = field === 'price_super' ? this.ownPriceSuper : (field === 'price_e10' ? this.ownPriceE10 : this.ownPriceDiesel);
                            if (ownField) prices.push(parseFloat(ownField));
                            this.competitorList.forEach(function(c) {
                                if (c[field]) prices.push(parseFloat(c[field]));
                            });
                            return prices.filter(function(p) { return p !== null && !isNaN(p); });
                        },
                        isLowest: function(val, field) {
                            if (!val) return false;
                            var v = parseFloat(val);
                            var prices = this.allPrices(field);
                            return prices.length > 1 && v <= Math.min.apply(null, prices);
                        },
                        isHighest: function(val, field) {
                            if (!val) return false;
                            var v = parseFloat(val);
                            var prices = this.allPrices(field);
                            return prices.length > 1 && v >= Math.max.apply(null, prices);
                        }
                    };
                });
            });
        </script>
    @endonce

    {{-- Karte + Sidebar --}}
    <div class="map-sidebar-layout">
        <div class="map-container">
            <div x-ref="map" style="height: 100%; width: 100%;"></div>
        </div>

        <div class="station-sidebar">
            <div style="padding: 10px 14px; border-bottom: 1px solid #e5e7eb; background: #f9fafb;">
                <div style="display: flex; align-items: center; justify-content: space-between;">
                    <span style="font-weight: 600; font-size: 13px; color: #111827;">Stationen</span>
                    <button type="button" x-on:click="zoomToAll()" style="font-size: 11px; color: #4F46E5; font-weight: 500; cursor: pointer; background: none; border: none; padding: 0;">Alle anzeigen</button>
                </div>
            </div>

            <div style="flex: 1; overflow-y: auto;">
                {{-- Eigene --}}
                <div x-on:click="zoomToOwn()" class="station-list-item" x-bind:class="{ 'active-own': activeIndex === -1 }" style="padding: 10px 14px; border-bottom: 1px solid #f3f4f6;">
                    <div style="display: flex; align-items: start; gap: 10px;">
                        <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: #EEF2FF; color: #4F46E5; flex-shrink: 0; margin-top: 1px;">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="currentColor" width="14" height="14"><path fill-rule="evenodd" d="m11.54 22.351.07.04.028.016a.76.76 0 0 0 .723 0l.028-.015.071-.041a16.975 16.975 0 0 0 1.144-.742 19.58 19.58 0 0 0 2.683-2.282c1.944-1.99 3.963-4.98 3.963-8.827a8.25 8.25 0 0 0-16.5 0c0 3.846 2.02 6.837 3.963 8.827a19.58 19.58 0 0 0 3.823 3.02ZM12 13.5a3 3 0 1 0 0-6 3 3 0 0 0 0 6Z" clip-rule="evenodd" /></svg>
                        </span>
                        <div style="min-width: 0; flex: 1; overflow: hidden;">
                            <div style="font-weight: 600; font-size: 12px; color: #111827; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="ownName || 'Eigene Tankstelle'"></div>
                            <div style="font-size: 11px; color: #6B7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="ownStreet"></div>
                            <div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-top: 2px;">
                                <template x-if="ownPriceDiesel">
                                    <span style="display: inline-block; font-size: 10px; font-weight: 600; color: #2563eb; background: #eff6ff; padding: 1px 6px; border-radius: 9999px;" x-text="'DK ' + ownPriceDiesel + '\u20ac'"></span>
                                </template>
                                <template x-if="ownPriceE10">
                                    <span style="display: inline-block; font-size: 10px; font-weight: 600; color: #16a34a; background: #f0fdf4; padding: 1px 6px; border-radius: 9999px;" x-text="'E10 ' + ownPriceE10 + '\u20ac'"></span>
                                </template>
                                <template x-if="ownPriceSuper">
                                    <span style="display: inline-block; font-size: 10px; font-weight: 600; color: #7c3aed; background: #f5f3ff; padding: 1px 6px; border-radius: 9999px;" x-text="'S ' + ownPriceSuper + '\u20ac'"></span>
                                </template>
                            </div>
                        </div>
                    </div>
                </div>

                {{-- Wettbewerber Header --}}
                <template x-if="competitorList.length > 0">
                    <div style="padding: 6px 14px; background: #FEF2F2; border-bottom: 1px solid #FEE2E2;">
                        <span style="font-size: 10px; font-weight: 600; color: #DC2626; text-transform: uppercase; letter-spacing: 0.05em;"
                              x-text="competitorList.length + ' Wettbewerber'"></span>
                    </div>
                </template>
                <template x-if="competitorList.length === 0">
                    <div style="padding: 20px 14px; text-align: center;">
                        <div style="color: #9CA3AF; font-size: 12px;">Keine Wettbewerber</div>
                        <div style="color: #D1D5DB; font-size: 11px; margin-top: 2px;">Im Wettbewerb-Tab hinzufuegen</div>
                    </div>
                </template>

                {{-- Wettbewerber --}}
                <template x-for="(comp, idx) in competitorList" x-bind:key="idx">
                    <div x-on:click="zoomToCompetitor(idx)" class="station-list-item competitor" x-bind:class="{ 'active-comp': activeIndex === idx }" style="padding: 8px 14px; border-bottom: 1px solid #f3f4f6;">
                        <div style="display: flex; align-items: start; gap: 10px;">
                            <span style="display: inline-flex; align-items: center; justify-content: center; width: 26px; height: 26px; border-radius: 50%; background: #FEF2F2; color: #DC2626; font-weight: 700; font-size: 12px; flex-shrink: 0; margin-top: 1px;" x-text="comp.priority"></span>
                            <div style="min-width: 0; flex: 1; overflow: hidden;">
                                <div style="font-weight: 600; font-size: 12px; color: #111827; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="comp.displayName"></div>
                                <div style="font-size: 11px; color: #6B7280; overflow: hidden; text-overflow: ellipsis; white-space: nowrap;" x-text="comp.street + (comp.city ? ', ' + comp.city : '')"></div>
                                <div style="display: flex; align-items: center; gap: 4px; flex-wrap: wrap; margin-top: 2px;">
                                    <template x-if="comp.distance">
                                        <span style="display: inline-block; font-size: 10px; font-weight: 600; color: #DC2626; background: #FEF2F2; padding: 1px 6px; border-radius: 9999px;" x-text="comp.distance + ' km'"></span>
                                    </template>
                                    <template x-if="comp.price_diesel">
                                        <span style="display: inline-block; font-size: 10px; font-weight: 600; color: #15803d; background: #f0fdf4; padding: 1px 6px; border-radius: 9999px;" x-text="'DK ' + comp.price_diesel + '\u20ac'"></span>
                                    </template>
                                    <template x-if="comp.price_e10">
                                        <span style="display: inline-block; font-size: 10px; font-weight: 600; color: #16a34a; background: #f0fdf4; padding: 1px 6px; border-radius: 9999px;" x-text="'E10 ' + comp.price_e10 + '\u20ac'"></span>
                                    </template>
                                </div>
                            </div>
                        </div>
                    </div>
                </template>
            </div>

            <div style="padding: 8px 14px; border-top: 1px solid #e5e7eb; background: #f9fafb; flex-shrink: 0;">
                <div style="display: flex; align-items: center; gap: 12px; font-size: 10px; color: #9CA3AF;">
                    <span style="display:flex;align-items:center;gap:4px;"><span style="display:inline-block;width:8px;height:8px;background:#4F46E5;border-radius:50%"></span> Eigene</span>
                    <span style="display:flex;align-items:center;gap:4px;"><span style="display:inline-block;width:8px;height:8px;background:#15803d;border-radius:50%"></span> Wettbewerber</span>
                </div>
            </div>
        </div>
    </div>

    {{-- Buttons --}}
    <div style="display: flex; align-items: center; gap: 12px; flex-wrap: wrap;">
        <button type="button" x-on:click="geocodeAddress()" class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm" style="background-color: #4F46E5; color: white;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16" style="width:16px;height:16px;min-width:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M15 10.5a3 3 0 1 1-6 0 3 3 0 0 1 6 0Z" /><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 10.5c0 7.142-7.5 11.25-7.5 11.25S4.5 17.642 4.5 10.5a7.5 7.5 0 1 1 15 0Z" /></svg>
            Von Adresse uebernehmen
        </button>
        <button type="button" x-on:click="loadCompetitors()" class="fi-btn fi-btn-size-sm inline-flex items-center gap-1.5 rounded-lg px-3 py-2 text-sm font-semibold shadow-sm" style="background-color: #15803d; color: white;">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke-width="1.5" stroke="currentColor" width="16" height="16" style="width:16px;height:16px;min-width:16px;"><path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182M2.985 19.644l3.181-3.182" /></svg>
            Aktualisieren
        </button>
        <span style="font-size: 12px; color: #6B7280;" x-show="lat && lng" x-text="'Koordinaten: ' + (lat ? parseFloat(lat).toFixed(6) : '') + ', ' + (lng ? parseFloat(lng).toFixed(6) : '')"></span>
    </div>

    {{-- Preisvergleichstabelle --}}
    <template x-if="competitorList.length > 0 && (ownPriceDiesel || ownPriceE10 || ownPriceSuper || competitorList.some(function(c) { return c.price_e10 || c.price_diesel || c.price_super; }))">
        <div style="border: 1px solid #e5e7eb; border-radius: 0.75rem; overflow: hidden;">
            <div style="padding: 10px 14px; background: #f9fafb; border-bottom: 1px solid #e5e7eb;">
                <span style="font-weight: 600; font-size: 13px; color: #111827;">Preisvergleich</span>
                <span style="font-size: 11px; color: #9CA3AF; margin-left: 8px;">Aktuelle Kraftstoffpreise</span>
            </div>
            <div style="overflow-x: auto;">
                <table class="price-compare-table">
                    <thead>
                        <tr>
                            <th style="min-width:40px">#</th>
                            <th style="min-width:140px">Station</th>
                            <th style="min-width:60px">km</th>
                            <th style="min-width:80px;text-align:right">Super</th>
                            <th style="min-width:80px;text-align:right">E10</th>
                            <th style="min-width:80px;text-align:right">Diesel</th>
                        </tr>
                    </thead>
                    <tbody>
                        {{-- Eigene Tankstelle --}}
                        <tr class="own-row">
                            <td><span style="display:inline-block;width:8px;height:8px;background:#4F46E5;border-radius:50%"></span></td>
                            <td style="font-weight:600;color:#4F46E5;" x-text="ownName || 'Eigene Tankstelle'"></td>
                            <td style="color:#9CA3AF;">&mdash;</td>
                            <td class="price-cell" x-text="ownPriceSuper ? ownPriceSuper + ' \u20ac' : '\u2014'"
                                x-bind:class="{ 'price-lowest': isLowest(ownPriceSuper, 'price_super'), 'price-highest': isHighest(ownPriceSuper, 'price_super') }"
                                x-bind:style="!ownPriceSuper ? 'color:#9CA3AF' : ''"></td>
                            <td class="price-cell" x-text="ownPriceE10 ? ownPriceE10 + ' \u20ac' : '\u2014'"
                                x-bind:class="{ 'price-lowest': isLowest(ownPriceE10, 'price_e10'), 'price-highest': isHighest(ownPriceE10, 'price_e10') }"
                                x-bind:style="!ownPriceE10 ? 'color:#9CA3AF' : ''"></td>
                            <td class="price-cell" x-text="ownPriceDiesel ? ownPriceDiesel + ' \u20ac' : '\u2014'"
                                x-bind:class="{ 'price-lowest': isLowest(ownPriceDiesel, 'price_diesel'), 'price-highest': isHighest(ownPriceDiesel, 'price_diesel') }"
                                x-bind:style="!ownPriceDiesel ? 'color:#9CA3AF' : ''"></td>
                        </tr>
                        {{-- Wettbewerber --}}
                        <template x-for="(comp, idx) in competitorList" x-bind:key="'price-' + idx">
                            <tr x-on:click="zoomToCompetitor(idx)" style="cursor:pointer">
                                <td><span style="display:inline-flex;align-items:center;justify-content:center;width:20px;height:20px;border-radius:50%;background:#f0fdf4;color:#15803d;font-weight:700;font-size:11px;" x-text="comp.priority"></span></td>
                                <td>
                                    <div style="font-weight:600;font-size:12px;" x-text="comp.displayName"></div>
                                    <div style="font-size:10px;color:#9CA3AF;" x-text="comp.city"></div>
                                </td>
                                <td x-text="comp.distance ? comp.distance : '\u2014'" style="color:#6B7280;"></td>
                                <td class="price-cell" x-text="comp.price_super ? comp.price_super + ' \u20ac' : '\u2014'"
                                    x-bind:class="{ 'price-lowest': isLowest(comp.price_super, 'price_super'), 'price-highest': isHighest(comp.price_super, 'price_super') }"
                                    x-bind:style="!comp.price_super ? 'color:#9CA3AF' : ''"></td>
                                <td class="price-cell" x-text="comp.price_e10 ? comp.price_e10 + ' \u20ac' : '\u2014'"
                                    x-bind:class="{ 'price-lowest': isLowest(comp.price_e10, 'price_e10'), 'price-highest': isHighest(comp.price_e10, 'price_e10') }"
                                    x-bind:style="!comp.price_e10 ? 'color:#9CA3AF' : ''"></td>
                                <td class="price-cell" x-text="comp.price_diesel ? comp.price_diesel + ' \u20ac' : '\u2014'"
                                    x-bind:class="{ 'price-lowest': isLowest(comp.price_diesel, 'price_diesel'), 'price-highest': isHighest(comp.price_diesel, 'price_diesel') }"
                                    x-bind:style="!comp.price_diesel ? 'color:#9CA3AF' : ''"></td>
                            </tr>
                        </template>
                    </tbody>
                </table>
            </div>
        </div>
    </template>

    <p style="font-size: 12px; color: #9CA3AF;">Klicken Sie auf die Karte, um die Position zu setzen. Daten: &copy; OpenStreetMap</p>
</div>
