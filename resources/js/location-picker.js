import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * Listing location picker (dealer listing form).
 *
 * <div data-location-picker='{"tiles","attribution","center","cityCentres","zoom","bounds","warnKm","searchUrl"}'>
 *
 * One draggable pin, written to the existing [data-location-lat]/[data-location-lng]
 * inputs. The pin is only ever placed by the dealer: a click/tap, a drag, typed
 * coordinates or "Use my location". Area centres and search results move the map
 * but never set the pin, so an approximate point is never saved as exact.
 */
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const pinIcon = L.divIcon({
    className: 'mp-picker-pin',
    html: '<svg viewBox="0 0 36 46" width="36" height="46" aria-hidden="true"><path d="M18 1C8.6 1 1 8.4 1 17.6 1 30 18 45 18 45s17-15 17-27.4C35 8.4 27.4 1 18 1z" fill="#4b2e83" stroke="#fff" stroke-width="2"/><circle cx="18" cy="17.5" r="6.5" fill="#d4af37"/></svg>',
    iconSize: [36, 46],
    iconAnchor: [18, 45],
});

/** "" → null, a plain decimal → number, anything else → NaN. */
const parse = (value) => {
    const text = String(value ?? '').trim();
    if (text === '') return null;
    return /^-?\d{1,3}(\.\d+)?$/.test(text) ? Number(text) : NaN;
};

const km = (a, b) => {
    const rad = (d) => (d * Math.PI) / 180;
    const h = Math.sin(rad(b.lat - a.lat) / 2) ** 2 + Math.cos(rad(a.lat)) * Math.cos(rad(b.lat)) * Math.sin(rad(b.lng - a.lng) / 2) ** 2;
    return 6371 * 2 * Math.asin(Math.sqrt(h));
};

const init = (root) => {
    if (root.dataset.pickerReady) return;
    root.dataset.pickerReady = '1';

    const config = JSON.parse(root.dataset.locationPicker);
    const form = root.closest('form');
    const $ = (selector) => root.querySelector(selector);
    const latInput = $('[data-location-lat]');
    const lngInput = $('[data-location-lng]');
    const status = $('[data-location-status]');
    const message = $('[data-location-message]');
    const warning = $('[data-location-warning]');
    const clearButton = $('[data-location-clear]');
    const citySelect = form?.querySelector('[name="city"]');
    const mapEl = $('[data-location-map]');
    const b = config.bounds;

    const inPakistan = (lat, lng) => lat >= b.south && lat <= b.north && lng >= b.west && lng <= b.east;
    const say = (text) => message && (message.textContent = text);
    // Centre of the deepest chosen location that has one (area → phase → block).
    const areaCentre = () => {
        const chosen = [...(form?.querySelectorAll('[data-location-level]') ?? [])]
            .map((select) => select.selectedOptions[0]?.dataset)
            .filter((d) => d?.lat && d?.lng)
            .pop();
        return chosen ? { name: chosen.name, lat: Number(chosen.lat), lng: Number(chosen.lng) } : null;
    };

    let map = null;
    let marker = null;

    const refresh = () => {
        const set = marker !== null;
        status.textContent = set ? 'Exact location set' : 'Exact location not set';
        status.className = `badge shrink-0 ring-1 ${set ? 'bg-emerald-50 text-emerald-800 ring-emerald-200' : 'bg-amber-50 text-amber-800 ring-amber-200'}`;
        if (clearButton) clearButton.hidden = !set;

        const area = areaCentre();
        const distance = set && area ? km(marker.getLatLng(), area) : 0;
        warning.hidden = !(distance > config.warnKm);
        if (!warning.hidden) {
            warning.textContent = `This pin is ${distance.toFixed(1)} km from the centre of ${area.name}. Please check it is on the property and that the right area is selected.`;
        }
    };

    const writeInputs = (ll) => {
        latInput.value = ll.lat.toFixed(7);
        lngInput.value = ll.lng.toFixed(7);
        [latInput, lngInput].forEach((input) => {
            input.classList.remove('input-error');
            input.removeAttribute('aria-invalid');
        });
    };

    const placePin = (ll) => {
        if (!map) return;
        if (marker) {
            marker.setLatLng(ll);
            return;
        }
        marker = L.marker(ll, { icon: pinIcon, draggable: true, autoPan: true, title: 'Property location', alt: 'Property location pin' }).addTo(map);
        marker.on('dragend', () => {
            const p = marker.getLatLng();
            if (!inPakistan(p.lat, p.lng)) {
                say('That point is outside Pakistan. Move the pin back onto the property.');
                return;
            }
            writeInputs(p);
            say('Pin moved. The coordinates below have been updated.');
            refresh();
        });
    };

    const removePin = () => {
        marker?.remove();
        marker = null;
    };

    /** Typed coordinates move the pin when they form a valid pair. */
    const syncFromInputs = (final) => {
        const lat = parse(latInput.value);
        const lng = parse(lngInput.value);

        if (lat === null && lng === null) {
            removePin();
            refresh();
            return;
        }
        if (Number.isNaN(lat) || Number.isNaN(lng) || lat === null || lng === null) {
            if (final) say('Enter both latitude and longitude as decimal numbers, e.g. 31.4697 and 74.4106, or clear the pin.');
            return;
        }
        if (!inPakistan(lat, lng)) {
            if (final) say('Those coordinates are outside Pakistan. Check the numbers, or place the pin on the map.');
            return;
        }
        placePin({ lat, lng });
        map?.panTo([lat, lng]);
        if (final) say('Pin moved to the coordinates you entered.');
        refresh();
    };

    let typing;
    [latInput, lngInput].forEach((input) => {
        input.addEventListener('input', () => {
            clearTimeout(typing);
            typing = setTimeout(() => syncFromInputs(false), 500);
        });
        input.addEventListener('change', () => {
            clearTimeout(typing);
            syncFromInputs(true);
        });
    });

    form?.addEventListener('location:change', () => {
        const area = areaCentre();
        if (!marker && area && map) {
            map.setView([area.lat, area.lng], 15);
            say(`Showing the centre of ${area.name}. Click the exact property location to place the pin.`);
        } else if (!marker && map && config.cityCentres?.[citySelect?.value]) {
            map.setView(config.cityCentres[citySelect.value], config.zoom);
        }
        refresh();
    });

    if (!mapEl) {
        // Map disabled in config: the coordinate fields still work on their own.
        refresh();
        return;
    }

    try {
        map = L.map(mapEl, { scrollWheelZoom: false });
        L.tileLayer(config.tiles, { attribution: config.attribution, maxZoom: 19 })
            .on('tileerror', () => say('Some map tiles could not be loaded. You can still enter the coordinates below.'))
            .addTo(map);
    } catch (error) {
        console.error(error);
        $('[data-location-fallback]').hidden = false;
        return;
    }

    map.on('click', (e) => {
        if (!inPakistan(e.latlng.lat, e.latlng.lng)) {
            say('That point is outside Pakistan.');
            return;
        }
        placePin(e.latlng);
        writeInputs(e.latlng);
        say('Pin placed. Drag it to fine-tune the position.');
        refresh();
    });
    mapEl.addEventListener('click', () => map.scrollWheelZoom.enable());
    mapEl.addEventListener('mouseleave', () => map.scrollWheelZoom.disable());

    clearButton?.addEventListener('click', () => {
        removePin();
        latInput.value = '';
        lngInput.value = '';
        say('Pin cleared. The listing will be shown at its area, marked as approximate, until you place a pin.');
        refresh();
    });

    // Starting view: the saved pin, else the chosen area's centre, else the city.
    const lat = parse(latInput.value);
    const lng = parse(lngInput.value);
    const area = areaCentre();
    if (Number.isFinite(lat) && Number.isFinite(lng) && inPakistan(lat, lng)) {
        placePin({ lat, lng });
        map.setView([lat, lng], 16);
    } else if (area) {
        map.setView([area.lat, area.lng], 14);
    } else {
        map.setView(config.center, config.zoom);
    }
    refresh();

    // Cards can finish laying out after the map is created.
    setTimeout(() => map.invalidateSize(), 250);
    window.addEventListener('resize', () => map.invalidateSize());

    // "Use my location": an explicit choice, intended for dealers on site.
    const locate = $('[data-location-locate]');
    if (!('geolocation' in navigator) || !window.isSecureContext) {
        locate.hidden = true;
    }
    locate?.addEventListener('click', () => {
        say('Finding your location…');
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                const ll = { lat: pos.coords.latitude, lng: pos.coords.longitude };
                if (!inPakistan(ll.lat, ll.lng)) {
                    say('Your current location is outside Pakistan, so no pin was placed.');
                    return;
                }
                placePin(ll);
                writeInputs(ll);
                map.setView([ll.lat, ll.lng], 17);
                const accuracy = Math.round(pos.coords.accuracy);
                say(`Pin placed at your current location (accurate to about ${accuracy} m). Drag it if it is not exactly on the property.`);
                refresh();
            },
            () => say('Your location is not available. Allow location access in your browser, or place the pin on the map.'),
            { enableHighAccuracy: true, timeout: 15000 },
        );
    });

    // Place search: moves the map only. Runs on submit, not while typing.
    const query = $('[data-location-query]');
    const results = $('[data-location-results]');
    const search = async () => {
        const q = query.value.trim();
        if (q.length < 3) {
            say('Type at least 3 characters, e.g. "Block K Model Town".');
            return;
        }
        say('Searching…');
        results.hidden = true;
        try {
            const response = await fetch(`${config.searchUrl}?q=${encodeURIComponent(q)}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
            if (response.status === 429) {
                say('Too many searches. Please wait a minute and try again.');
                return;
            }
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            const { results: places } = await response.json();
            if (!places.length) {
                say('No places found. Try a nearby landmark or main road, or move the map yourself.');
                return;
            }
            results.innerHTML = places
                .map((p, i) => `<li><button type="button" class="block w-full px-3 py-2.5 text-left hover:bg-brand-50 focus-visible:bg-brand-50" data-index="${i}">${esc(p.label)}</button></li>`)
                .join('');
            results.hidden = false;
            say(`${places.length} ${places.length === 1 ? 'place' : 'places'} found. Choose one to move the map there.`);
            results.querySelectorAll('button').forEach((btn) => btn.addEventListener('click', () => {
                const place = places[Number(btn.dataset.index)];
                map.setView([place.lat, place.lng], 17);
                results.hidden = true;
                say('Map moved. Now click the exact property location to place the pin.');
            }));
        } catch (error) {
            console.error(error);
            say('Search is not available right now. Move the map and place the pin yourself.');
        }
    };
    $('[data-location-find]')?.addEventListener('click', search);
    query?.addEventListener('keydown', (e) => {
        // Enter searches instead of submitting the listing form.
        if (e.key === 'Enter') {
            e.preventDefault();
            search();
        }
    });
};

document.querySelectorAll('[data-location-picker]').forEach(init);
