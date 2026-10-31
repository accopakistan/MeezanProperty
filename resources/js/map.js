import L from 'leaflet';
import 'leaflet/dist/leaflet.css';

/**
 * <div data-map='{"tiles":..., "attribution":..., "center":[lat,lng], "zoom":11}'
 *      data-markers='[{"kind":"pin"|"area","id":1,"lat":..,"lng":..,"label":"2.5 Cr",...}]'>
 *
 * Pins use real listing coordinates. Listings without them appear only inside
 * an "area" marker at the area's approximate centre, labelled as approximate.
 */
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

/**
 * "Search as I move the map": when <input data-map-follow> is ticked, panning or
 * zooming reloads the results for the visible area (north/south/east/west).
 * Unticking removes the area filter again.
 */
const followMap = (map) => {
    const toggle = document.querySelector('[data-map-follow]');
    if (!toggle) return;

    let ready = false;
    let timer;
    // Ignore the moves caused by the initial fitBounds.
    setTimeout(() => (ready = true), 800);

    const go = (withBounds) => {
        const url = new URL(window.location.href);
        ['north', 'south', 'east', 'west', 'page', 'selected'].forEach((k) => url.searchParams.delete(k));
        if (withBounds) {
            // Viewport search replaces a drawn area.
            url.searchParams.delete('poly');
            const b = map.getBounds();
            url.searchParams.set('north', b.getNorth().toFixed(5));
            url.searchParams.set('south', b.getSouth().toFixed(5));
            url.searchParams.set('east', b.getEast().toFixed(5));
            url.searchParams.set('west', b.getWest().toFixed(5));
            url.searchParams.set('follow', '1');
        } else {
            url.searchParams.delete('follow');
        }
        window.location.assign(url.toString());
    };

    map.on('moveend', () => {
        if (!ready || !toggle.checked) return;
        clearTimeout(timer);
        timer = setTimeout(() => go(true), 700);
    });

    toggle.addEventListener('change', () => go(toggle.checked));
};

/**
 * Freehand "draw an area" search. [data-draw-start] enters drawing mode; the user
 * presses, draws around the area and releases. The outline is simplified to at
 * most 100 points and sent as ?poly=lat,lng;lat,lng;... [data-draw-clear] removes it.
 */
let activeMap = null;
let drawControlsBound = false;

const navigateWith = (mutate) => {
    const url = new URL(window.location.href);
    ['page', 'selected', 'north', 'south', 'east', 'west', 'follow'].forEach((k) => url.searchParams.delete(k));
    mutate(url.searchParams);
    window.location.assign(url.toString());
};

const simplify = (map, latlngs) => {
    let points = latlngs.map((ll) => map.latLngToLayerPoint(ll));
    let tolerance = 2;
    let simplified = L.LineUtil.simplify(points, tolerance);
    while (simplified.length > 100) {
        tolerance *= 1.5;
        simplified = L.LineUtil.simplify(points, tolerance);
    }
    return simplified.map((p) => map.layerPointToLatLng(p));
};

const startDrawing = (map) => {
    const container = map.getContainer();
    const hint = document.querySelector('[data-draw-hint]');
    const style = { color: '#4b2e83', weight: 3, fillColor: '#6a3fb0', fillOpacity: 0.12 };
    let points = [];
    let line = null;
    let pointerDown = false;

    map.dragging.disable();
    map.doubleClickZoom.disable();
    container.style.cursor = 'crosshair';
    container.style.touchAction = 'none';
    if (hint) hint.hidden = false;

    const stop = () => {
        container.removeEventListener('pointerdown', down);
        container.removeEventListener('pointermove', move);
        window.removeEventListener('pointerup', up);
        document.removeEventListener('keydown', onKey);
        map.dragging.enable();
        map.doubleClickZoom.enable();
        container.style.cursor = '';
        container.style.touchAction = '';
        if (hint) hint.hidden = true;
        if (line) line.remove();
    };

    const down = (e) => {
        if (e.button !== undefined && e.button !== 0) return;
        e.preventDefault();
        pointerDown = true;
        points = [map.mouseEventToLatLng(e)];
        line = L.polyline(points, style).addTo(map);
    };

    const move = (e) => {
        if (!pointerDown) return;
        const ll = map.mouseEventToLatLng(e);
        const last = map.latLngToContainerPoint(points[points.length - 1]);
        if (map.latLngToContainerPoint(ll).distanceTo(last) < 4) return;
        points.push(ll);
        line.setLatLngs(points);
    };

    const up = () => {
        if (!pointerDown) return;
        pointerDown = false;
        const shape = simplify(map, points);
        stop();
        if (shape.length < 3) return;
        L.polygon(shape, style).addTo(map);
        navigateWith((params) => params.set('poly', shape.map((p) => `${p.lat.toFixed(5)},${p.lng.toFixed(5)}`).join(';')));
    };

    const onKey = (e) => e.key === 'Escape' && stop();

    container.addEventListener('pointerdown', down);
    container.addEventListener('pointermove', move);
    window.addEventListener('pointerup', up);
    document.addEventListener('keydown', onKey);
    document.querySelector('[data-draw-cancel]')?.addEventListener('click', stop, { once: true });
};

const bindDrawControls = () => {
    if (drawControlsBound) return;
    drawControlsBound = true;
    document.querySelectorAll('[data-draw-start]').forEach((btn) => btn.addEventListener('click', () => activeMap && startDrawing(activeMap)));
    document.querySelectorAll('[data-draw-clear]').forEach((btn) => btn.addEventListener('click', () => navigateWith((params) => params.delete('poly'))));
};

/**
 * Price pin (exact location) or area group (listings without an exact pin,
 * shown at the area's approximate centre). Each carries the listing ids it
 * covers so hovering a result card can highlight it.
 */
const markerIcon = (m) => {
    const ids = esc((m.ids ?? [m.id]).join(' '));
    const photo = m.kind !== 'area' && m.photo ? `<img src="${esc(m.photo)}" alt="" loading="lazy">` : '';
    // Area groups and privacy-blurred listings share the dashed "approximate" style.
    const isApprox = m.kind === 'area' || m.kind === 'approx';
    const html = `<div class="mp-pin${isApprox ? ' mp-pin--area' : ''}" data-marker-ids="${ids}">
        ${photo}
        ${m.isNew ? '<span class="mp-pin__new">New</span>' : ''}
        <span class="mp-pin__label">${isApprox ? '<span class="mp-pin__icon">Approx.: </span>' : ''}${esc(m.label)}</span>
        ${m.from ? `<span class="mp-pin__from">${esc(m.from)}</span>` : ''}
    </div>`;

    return L.divIcon({ className: 'mp-pin-wrap', html, iconSize: null });
};

const itemHtml = (item) => `<a href="${esc(item.url)}" class="mp-popup__item">
    ${item.photo ? `<img src="${esc(item.photo)}" alt="" loading="lazy">` : '<span class="mp-popup__noimg"></span>'}
    <span><strong>${esc(item.price)}</strong><span>${esc(item.title)}</span><small>${esc(item.meta)}</small></span>
</a>`;

const popupHtml = (m) => {
    if (m.kind !== 'area') {
        const note = m.kind === 'approx' ? '<p class="mp-popup__note">Approximate location: the advertiser shows it to within about 500 m.</p>' : '';
        return `<div class="mp-popup__single">${m.photo ? `<img src="${esc(m.photo)}" alt="">` : ''}
            <a href="${esc(m.url)}"><strong>${esc(m.price)}</strong><span>${esc(m.title)}</span><small>${esc(m.meta)}</small></a>${note}</div>`;
    }

    const more = m.count - m.items.length;
    return `<div class="mp-popup__area">
        <p class="mp-popup__head">${esc(m.title)} · ${m.count} ${m.count === 1 ? 'listing' : 'listings'}</p>
        <p class="mp-popup__note">Approximate: shown at the area centre because the exact location was not provided.</p>
        ${m.items.map(itemHtml).join('')}
        ${m.url ? `<a href="${esc(m.url)}" class="mp-popup__all">${more > 0 ? `See all ${m.count} in ${esc(m.title)}` : `Search ${esc(m.title)}`} →</a>` : ''}
    </div>`;
};

const init = (el) => {
    el.dataset.mapReady = '1';
    const status = el.parentElement.querySelector('[data-map-status]');

    try {
        const config = JSON.parse(el.dataset.map);
        const markers = JSON.parse(el.dataset.markers || '[]');

        const map = L.map(el, { scrollWheelZoom: false }).setView(config.center, config.zoom);

        const tiles = L.tileLayer(config.tiles, { attribution: config.attribution, maxZoom: 19 }).addTo(map);
        tiles.on('tileerror', () => {
            if (!status) return;
            status.hidden = false;
            status.textContent = 'Map tiles could not be loaded. Listings are still shown in the list.';
        });

        markers.forEach((m) => {
            const marker = L.marker([m.lat, m.lng], { icon: markerIcon(m), title: m.kind === 'pin' ? m.title : `${m.title} (approximate)`, riseOnHover: true, riseOffset: 1000 }).addTo(map);
            if (m.kind === 'approx') {
                L.circle([m.lat, m.lng], { radius: 400, color: '#b8860b', weight: 1, dashArray: '4 4', fillColor: '#d4af37', fillOpacity: 0.12, interactive: false }).addTo(map);
            }
            marker.bindPopup(popupHtml(m), { className: 'mp-popup', maxWidth: 280, minWidth: 240, offset: [0, -34], autoPanPaddingTopLeft: [16, 64], autoPanPaddingBottomRight: [16, 48] });
        });

        if (config.polygon) {
            const area = L.polygon(config.polygon, { color: '#4b2e83', weight: 3, fillColor: '#6a3fb0', fillOpacity: 0.1 }).addTo(map);
            map.fitBounds(area.getBounds(), { padding: [24, 24] });
        } else if (config.bounds) {
            map.fitBounds(config.bounds);
        } else if (markers.length > 1) {
            map.fitBounds(L.latLngBounds(markers.map((m) => [m.lat, m.lng])), { paddingTopLeft: [40, 80], paddingBottomRight: [40, 40], maxZoom: 15 });
        } else if (markers.length === 1) {
            map.setView([markers[0].lat, markers[0].lng], markers[0].kind === 'pin' ? 15 : 14);
        }

        followMap(map);
        activeMap = map;
        bindDrawControls();

        el.addEventListener('click', () => map.scrollWheelZoom.enable());
        el.addEventListener('mouseleave', () => map.scrollWheelZoom.disable());
    } catch (error) {
        console.error(error);
        if (status) {
            status.hidden = false;
            status.textContent = 'The map is unavailable right now. Listings are still shown in the list.';
        }
    }
};

const initVisible = () => {
    document.querySelectorAll('[data-map]:not([data-map-ready])').forEach((el) => {
        if (el.offsetParent !== null && el.clientHeight > 0) init(el);
    });
};

initVisible();
window.addEventListener('resize', initVisible);

// Highlight a listing's marker while its card is hovered.
document.querySelectorAll('[data-result-id]').forEach((card) => {
    const toggle = (on) => document
        .querySelectorAll(`[data-marker-ids~="${CSS.escape(card.dataset.resultId)}"]`)
        .forEach((node) => {
            node.classList.toggle('is-active', on);
            node.closest('.leaflet-marker-icon')?.style.setProperty('z-index', on ? '10000' : '');
        });
    card.addEventListener('mouseenter', () => toggle(true));
    card.addEventListener('mouseleave', () => toggle(false));
});
