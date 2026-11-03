import { maplibregl, createMap, ringFromLatLng, boundsOfCoordinates, BRAND } from './common.js';
import { ListingMarkers } from './markers.js';
import { BoundaryLayer } from './boundaries.js';
import { startDrawing } from './draw.js';

/**
 * Map search (/map): MapLibre map, result list and filters kept in sync.
 *
 * - Filters, sort, category chips and pagination fetch /map/data and swap the
 *   list and markers in place; the URL follows (history), so searches can be
 *   shared and the back button works. Without JavaScript the same links and
 *   forms load full pages.
 * - Moving the map shows "Search this area" (or searches by itself when
 *   "Search as I move the map" is ticked, debounced).
 * - Marker click: preview pop-up, matching card highlighted, detail panel.
 *   Card "Show on map": fly to its marker and open the preview.
 */
const el = document.querySelector('[data-geo-map]');

const $ = (selector) => document.querySelector(selector);
const layout = $('[data-map-layout]');
const results = $('[data-map-results]');
const summary = $('[data-map-summary]');
const status = $('[data-map-status]');
const loading = $('[data-map-loading]');
const detail = $('[data-map-detail]');
const searchAreaButton = $('[data-map-search-area]');
const follow = $('[data-map-follow]');
const citySelect = $('[data-map-city]');
const drawStart = $('[data-draw-start]');
const drawClear = $('[data-draw-clear]');
const drawLabel = $('[data-draw-label]');

const config = JSON.parse(el.dataset.geoMap);
let items = JSON.parse(el.dataset.markers || '[]');

const showStatus = (message, ms = 6000) => {
    if (!status) return;
    status.textContent = message;
    status.hidden = false;
    clearTimeout(showStatus.timer);
    showStatus.timer = setTimeout(() => (status.hidden = true), ms);
};

/* Initial camera ------------------------------------------------------ */
const searchRing = config.polygon ? ringFromLatLng(config.polygon) : null;
const initialBounds = () => {
    if (searchRing) return boundsOfCoordinates(searchRing);
    if (config.bounds) return new maplibregl.LngLatBounds(config.bounds);
    if (items.length > 1) return boundsOfCoordinates(items.map((m) => [Number(m.lng), Number(m.lat)]));
    if (!config.center && !items.length) return new maplibregl.LngLatBounds(config.pakistan);
    return null;
};
const startBounds = initialBounds();

let markers;
let boundaries;
// isStyleLoaded() is false while tiles load, so track the style ourselves.
let styleReady = false;

const map = createMap(el, config, {
    ...(startBounds
        ? { bounds: startBounds, fitBoundsOptions: { padding: config.bounds ? 0 : 60, maxZoom: 14 } }
        : { center: items.length === 1 ? [Number(items[0].lng), Number(items[0].lat)] : config.center, zoom: items.length === 1 ? 14 : config.zoom }),
    onNotice: (message) => showStatus(message),
    onStyle: () => {
        styleReady = true;
        markers.attach();
        boundaries.attach();
        drawSearchArea(searchAreaRing);
    },
});

map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
map.addControl(new maplibregl.FullscreenControl(), 'top-right');
const geolocate = new maplibregl.GeolocateControl({ positionOptions: { enableHighAccuracy: false, timeout: 10000 }, trackUserLocation: false, fitBoundsOptions: { maxZoom: 14 } });
map.addControl(geolocate, 'top-right');
// The browser asks for location only when the user presses the button; it is never stored.
geolocate.on('error', () => showStatus('Your location is not available (permission denied or not found). The map still works as normal.'));
map.addControl(new maplibregl.ScaleControl({ unit: 'metric' }), 'bottom-right');

/* Listing markers ------------------------------------------------------- */
const cardFor = (id) => results.querySelector(`[data-map-card="${CSS.escape(String(id))}"]`);

const markCard = (ids) => {
    results.querySelectorAll('[data-map-card].is-selected').forEach((c) => c.classList.remove('is-selected'));
    const first = ids.map(cardFor).find(Boolean);
    ids.forEach((id) => cardFor(id)?.classList.add('is-selected'));
    first?.scrollIntoView({ block: 'nearest', behavior: 'smooth' });
};

const bindGallery = (root) => root.querySelectorAll('[data-gallery]').forEach((gallery) => {
    const main = gallery.querySelector('[data-gallery-main]');
    const counter = gallery.querySelector('[data-gallery-counter]');
    const thumbs = [...gallery.querySelectorAll('[data-gallery-thumb]')];
    let index = 0;
    const show = (i) => {
        index = (i + thumbs.length) % thumbs.length;
        main.src = thumbs[index].dataset.galleryThumb;
        if (counter) counter.textContent = `${index + 1} / ${thumbs.length}`;
    };
    gallery.querySelector('[data-gallery-prev]')?.addEventListener('click', () => show(index - 1));
    gallery.querySelector('[data-gallery-next]')?.addEventListener('click', () => show(index + 1));
});

const loadDetail = async (slug) => {
    // The panel is only on wide screens; skip the request when it is hidden.
    if (!detail || !slug || detail.offsetParent === null) return;
    try {
        const response = await fetch(config.urls.preview.replace('__SLUG__', encodeURIComponent(slug)), { headers: { Accept: 'text/html' } });
        if (!response.ok) return;
        detail.innerHTML = await response.text();
        bindGallery(detail);
    } catch {
        // Keep the previous panel.
    }
};

markers = new ListingMarkers(map, {
    onSelect: (m) => {
        markCard((m.ids ?? [m.id]).map(String));
        if (m.kind === 'pin' || m.kind === 'approx') loadDetail(m.url?.split('/').pop());
    },
    onGridClick: () => searchArea(),
});
markers.items = items;

boundaries = new BoundaryLayer(map, {
    url: config.urls.boundaries,
    detailUrl: config.urls.boundary,
    minZoom: config.boundaryMinZoom,
});

/* Drawn search area outline ------------------------------------------------ */
let searchAreaRing = searchRing;
const drawSearchArea = (ring) => {
    searchAreaRing = ring;
    const data = { type: 'FeatureCollection', features: ring ? [{ type: 'Feature', geometry: { type: 'Polygon', coordinates: [ring] }, properties: {} }] : [] };
    if (map.getSource('search-area')) {
        map.getSource('search-area').setData(data);
        return;
    }
    if (!styleReady) return;
    map.addSource('search-area', { type: 'geojson', data });
    map.addLayer({ id: 'search-area-fill', type: 'fill', source: 'search-area', paint: { 'fill-color': BRAND.purpleLight, 'fill-opacity': 0.1 } });
    map.addLayer({ id: 'search-area-line', type: 'line', source: 'search-area', paint: { 'line-color': BRAND.purple, 'line-width': 3 } });
};

/* Loading results ------------------------------------------------------------ */
let request;
let programmaticMove = false;

const clean = (params) => {
    [...params.keys()].forEach((key) => {
        if (params.getAll(key).every((v) => v === '')) params.delete(key);
    });
    params.delete('selected');
    return params;
};

const fitTo = (list) => {
    const coordinates = list.map((m) => [Number(m.lng), Number(m.lat)]);
    programmaticMove = true;
    if (coordinates.length > 1) map.fitBounds(boundsOfCoordinates(coordinates), { padding: 60, maxZoom: 14 });
    else if (coordinates.length === 1) map.flyTo({ center: coordinates[0], zoom: 14 });
    else programmaticMove = false;
};

const load = async (params, { fit = false, push = true } = {}) => {
    clean(params);
    request?.abort();
    request = new AbortController();
    results.setAttribute('aria-busy', 'true');
    if (loading) loading.hidden = false;

    try {
        const response = await fetch(`${config.urls.data}?${params}`, { headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }, signal: request.signal });
        if (!response.ok) throw new Error(String(response.status));
        const data = await response.json();

        results.innerHTML = data.list;
        if (summary) summary.innerHTML = data.summary;
        items = data.markers;
        markers.setData(items);
        drawSearchArea(data.polygon ? ringFromLatLng(data.polygon) : null);
        if (drawClear) drawClear.hidden = !data.polygon;
        if (drawLabel) drawLabel.textContent = data.polygon ? 'Redraw' : 'Draw Area';
        searchAreaButton.hidden = true;
        updateAreaOptions(data.areaOptions);

        const query = params.toString();
        if (push) history.pushState({ map: true }, '', query ? `${config.urls.page}?${query}` : config.urls.page);
        if (fit) fitTo(items);
        if (!items.length && data.total) showStatus('None of these listings have a map location yet. They are listed beside the map.');
    } catch (error) {
        if (error.name === 'AbortError') return;
        showStatus('Results could not be updated. Please try again.');
    } finally {
        results.setAttribute('aria-busy', 'false');
        if (loading) loading.hidden = true;
    }
};

const updateAreaOptions = (options) => {
    const select = $('[data-map-form] select[name="area"]');
    if (!select || !Array.isArray(options)) return;
    const first = select.querySelector('option[value=""]');
    select.replaceChildren(...[first, ...options.map((o) => new Option(o.label, o.value, false, o.selected))].filter(Boolean));
};

const currentParams = () => new URLSearchParams(window.location.search);

const searchArea = () => {
    const b = map.getBounds();
    const params = currentParams();
    ['poly', 'page', 'area', 'areas[]', 'areas'].forEach((k) => params.delete(k));
    params.set('north', b.getNorth().toFixed(5));
    params.set('south', b.getSouth().toFixed(5));
    params.set('east', b.getEast().toFixed(5));
    params.set('west', b.getWest().toFixed(5));
    // Include listings drawn at their area's centre, as the map shows them.
    params.set('centres', '1');
    if (follow?.checked) params.set('follow', '1');
    load(params);
};

/* Map movement: "Search this area" / follow mode ------------------------------ */
let followTimer;
map.on('movestart', (e) => {
    if (e.originalEvent) programmaticMove = false;
});
map.on('moveend', (e) => {
    if (programmaticMove && !e.originalEvent) {
        programmaticMove = false;
        return;
    }
    if (!e.originalEvent) return;
    if (follow?.checked) {
        clearTimeout(followTimer);
        followTimer = setTimeout(searchArea, 600);
    } else {
        searchAreaButton.hidden = false;
    }
});
searchAreaButton?.addEventListener('click', searchArea);
follow?.addEventListener('change', () => {
    if (follow.checked) {
        searchArea();
    } else {
        const params = currentParams();
        ['north', 'south', 'east', 'west', 'follow', 'centres', 'page'].forEach((k) => params.delete(k));
        load(params);
    }
});

/* City selector ------------------------------------------------------------------ */
citySelect?.addEventListener('change', () => {
    const city = citySelect.value;
    const params = currentParams();
    ['area', 'areas', 'areas[]', 'north', 'south', 'east', 'west', 'poly', 'page', 'follow', 'centres', 'q'].forEach((k) => params.delete(k));
    city ? params.set('city', city) : params.delete('city');
    const formCity = $('[data-map-city-field]');
    if (formCity) formCity.value = city;
    const area = $('[data-map-form] select[name="area"]');
    if (area) area.value = '';

    programmaticMove = true;
    if (city && config.cities[city]) map.flyTo({ center: config.cities[city], zoom: 11, essential: true });
    else map.fitBounds(config.pakistan, { padding: 20 });
    load(params);
});

/* Forms, chips and pagination: update in place ---------------------------------------- */
document.addEventListener('submit', (e) => {
    const form = e.target.closest('form[data-map-form]');
    if (!form || form.method.toLowerCase() !== 'get') return;
    e.preventDefault();
    const params = new URLSearchParams(new FormData(form));
    const keepsArea = params.has('north') || params.has('poly');
    if (form.matches('[data-map-filters]') && citySelect) citySelect.value = params.get('city') ?? '';
    load(params, { fit: !keepsArea });
    if (form.matches('[data-map-filters]') && window.innerWidth < 1024) setView('list');
});

results.addEventListener('click', (e) => {
    const show = e.target.closest('[data-show-on-map]');
    if (show) {
        e.preventDefault();
        const id = show.dataset.showOnMap;
        if (window.innerWidth < 1024) setView('map');
        if (!markers.show(id)) showStatus('This listing has no map location yet.', 4000);
        markCard([id]);
        loadDetail(show.dataset.slug);
        return;
    }

    const link = e.target.closest('a[href]');
    if (!link || link.hasAttribute('data-map-external') || e.metaKey || e.ctrlKey || e.shiftKey) return;
    const url = new URL(link.href, window.location.href);
    if (url.origin !== window.location.origin || url.pathname !== new URL(config.urls.page).pathname) return;
    e.preventDefault();
    const paging = Boolean(link.closest('[data-map-pagination]'));
    load(url.searchParams, { fit: !paging && !url.searchParams.has('north') && !url.searchParams.has('poly') });
    if (paging) results.closest('[data-map-list]')?.scrollIntoView({ block: 'start', behavior: 'smooth' });
});

// Area links inside map pop-ups ("Show all 12 in Gulberg") filter in place too.
el.addEventListener('click', (e) => {
    const link = e.target.closest('a[data-map-area-link]');
    if (!link) return;
    e.preventDefault();
    load(new URL(link.href, window.location.href).searchParams, { fit: true });
});

// Hovering a card raises its marker.
results.addEventListener('mouseover', (e) => {
    const card = e.target.closest('[data-map-card]');
    if (card) markers.highlight(card.dataset.mapCard, true);
});
results.addEventListener('mouseout', (e) => {
    const card = e.target.closest('[data-map-card]');
    if (card && !card.contains(e.relatedTarget)) markers.highlight(card.dataset.mapCard, false);
});
results.addEventListener('focusin', (e) => {
    const card = e.target.closest('[data-map-card]');
    if (card) markers.highlight(card.dataset.mapCard, true);
});
results.addEventListener('focusout', (e) => {
    const card = e.target.closest('[data-map-card]');
    if (card && !card.contains(e.relatedTarget)) markers.highlight(card.dataset.mapCard, false);
});

window.addEventListener('popstate', () => {
    const params = currentParams();
    const box = ['west', 'south', 'east', 'north'].map((k) => Number(params.get(k)));
    if (params.has('north') && box.every(Number.isFinite)) {
        programmaticMove = true;
        map.fitBounds([[box[0], box[1]], [box[2], box[3]]], { padding: 0, animate: false });
    }
    if (citySelect) citySelect.value = params.get('city') ?? '';
    load(params, { fit: !params.has('north') && !params.has('poly'), push: false });
});

/* Drawn-area search ------------------------------------------------------------------ */
drawStart?.addEventListener('click', () => startDrawing(map, {
    hint: $('[data-draw-hint]'),
    cancel: $('[data-draw-cancel]'),
    onDone: (points) => {
        const params = currentParams();
        ['north', 'south', 'east', 'west', 'follow', 'centres', 'page'].forEach((k) => params.delete(k));
        params.set('poly', points.map(([lat, lng]) => `${lat},${lng}`).join(';'));
        load(params);
    },
}));
drawClear?.addEventListener('click', () => {
    const params = currentParams();
    params.delete('poly');
    params.delete('page');
    load(params, { fit: true });
});

/* Phones: map / list / filters switch ---------------------------------------------------- */
const switcher = $('[data-map-switch]');
const setView = (view) => {
    if (view === 'filters') {
        $('[data-map-filters]')?.scrollIntoView({ block: 'start', behavior: 'smooth' });
        $('[data-map-filters] select')?.focus({ preventScroll: true });
        return;
    }
    layout.dataset.view = view;
    switcher?.querySelectorAll('[data-map-view]').forEach((b) => b.setAttribute('aria-pressed', String(b.dataset.mapView === view)));
    if (view === 'map') requestAnimationFrame(() => map.resize());
};
if (switcher) {
    switcher.hidden = false;
    switcher.addEventListener('click', (e) => {
        const button = e.target.closest('[data-map-view]');
        if (button) setView(button.dataset.mapView);
    });
    setView('map');
}

window.addEventListener('error', () => showStatus('Something went wrong with the map. Listings are still shown in the list.'), { once: true });
