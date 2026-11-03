import { maplibregl, esc } from './common.js';

/**
 * Listing markers for map search.
 *
 * The server sends marker data (App\Support\MapMarkers / MapController):
 *   pin    a listing's own public pin (price label)
 *   approx a listing the advertiser shows only approximately (dashed)
 *   area   listings without a pin, grouped at their area's centre (dashed)
 *   grid   counted cell of many located listings (large result sets)
 * They go into a clustered GeoJSON source; clusters and markers on screen are
 * drawn as HTML buttons, so they are keyboard-focusable and labelled.
 */
const SOURCE = 'listings';

const popupHtml = (m) => {
    if (m.kind === 'pin' || m.kind === 'approx') {
        const note = m.kind === 'approx' ? '<p class="mp-popup__note px-3.5 pb-3">Approximate location: the advertiser shows it to within about 500 m.</p>' : '';
        return `<div class="mp-popup__single">${m.photo ? `<img src="${esc(m.photo)}" alt="" loading="lazy">` : ''}
            <a href="${esc(m.url)}"><strong>${esc(m.price)}</strong><span>${esc(m.title)}</span><small>${esc(m.meta)}</small><span class="mp-popup__cta">View property →</span></a>${note}</div>`;
    }

    const items = (m.items ?? []).map((item) => `<a href="${esc(item.url)}" class="mp-popup__item">
        ${item.photo ? `<img src="${esc(item.photo)}" alt="" loading="lazy">` : '<span class="mp-popup__noimg"></span>'}
        <span><strong>${esc(item.price)}</strong><span>${esc(item.title)}</span><small>${esc(item.meta)}</small></span></a>`).join('');
    const more = m.count - (m.items ?? []).length;

    return `<div class="mp-popup__area">
        <p class="mp-popup__head">${esc(m.title)} · ${m.count} ${m.count === 1 ? 'listing' : 'listings'}</p>
        <p class="mp-popup__note">Approximate: shown at the area centre because the exact location was not provided.</p>
        ${items}
        ${m.url ? `<a href="${esc(m.url)}" class="mp-popup__all" data-map-area-link>${more > 0 ? `Show all ${m.count} in ${esc(m.title)}` : `Search ${esc(m.title)}`} →</a>` : ''}
    </div>`;
};

const markerLabel = (m) => {
    if (m.kind === 'grid') return `${m.label} in this part of the map. Zoom in.`;
    if (m.kind === 'area') return `${m.title}: ${m.count} ${m.count === 1 ? 'listing' : 'listings'} (approximate, area centre)`;
    return `${m.title}, ${m.price}${m.kind === 'approx' ? ' (approximate location)' : ''}`;
};

export class ListingMarkers {
    constructor(map, { onSelect, onGridClick } = {}) {
        this.map = map;
        this.onSelect = onSelect;
        this.onGridClick = onGridClick;
        this.items = [];
        this.cache = new Map();
        this.onScreen = new Map();
        this.popup = new maplibregl.Popup({ className: 'mp-popup', maxWidth: '280px', offset: [0, -30], focusAfterOpen: false });
        this.lastUpdate = 0;

        map.on('render', () => {
            const now = performance.now();
            if (now - this.lastUpdate < 120 || !map.getSource(SOURCE) || !map.isSourceLoaded(SOURCE)) return;
            this.lastUpdate = now;
            this.update();
        });
        map.on('moveend', () => map.getSource(SOURCE) && this.update());
    }

    /** (Re)create the source after a style load. */
    attach() {
        if (this.map.getSource(SOURCE)) return;
        this.map.addSource(SOURCE, {
            type: 'geojson',
            data: this.geojson(),
            cluster: true,
            clusterRadius: 48,
            clusterMaxZoom: 14,
            clusterProperties: { total: ['+', ['get', 'n']] },
        });
        // Invisible layer: the source must be used by a layer for its features to be queried.
        this.map.addLayer({ id: 'listings-hit', type: 'circle', source: SOURCE, paint: { 'circle-radius': 0, 'circle-opacity': 0 } });
        this.clear();
    }

    setData(items) {
        this.items = items ?? [];
        this.popup.remove();
        this.clear();
        this.map.getSource(SOURCE)?.setData(this.geojson());
    }

    geojson() {
        return {
            type: 'FeatureCollection',
            features: this.items.map((m, i) => ({
                type: 'Feature',
                geometry: { type: 'Point', coordinates: [Number(m.lng), Number(m.lat)] },
                properties: { i, n: m.count ?? 1 },
            })),
        };
    }

    clear() {
        this.onScreen.forEach((marker) => marker.remove());
        this.onScreen.clear();
        this.cache.clear();
    }

    update() {
        const next = new Map();
        for (const feature of this.map.querySourceFeatures(SOURCE)) {
            const p = feature.properties;
            const key = p.cluster ? `c${p.cluster_id}` : `m${p.i}`;
            if (next.has(key)) continue;
            let marker = this.cache.get(key);
            if (!marker) {
                marker = p.cluster ? this.clusterMarker(feature) : this.itemMarker(this.items[p.i]);
                if (!marker) continue;
                this.cache.set(key, marker);
            }
            next.set(key, marker);
            if (!this.onScreen.has(key)) marker.addTo(this.map);
        }
        this.onScreen.forEach((marker, key) => !next.has(key) && marker.remove());
        this.onScreen = next;
    }

    element(html, label) {
        const wrap = document.createElement('div');
        wrap.className = 'mp-ml';
        wrap.innerHTML = html;
        const button = wrap.firstElementChild;
        button.setAttribute('aria-label', label);
        return { wrap, button };
    }

    clusterMarker(feature) {
        const total = feature.properties.total;
        const { wrap, button } = this.element(`<button type="button" class="mp-cluster">${total.toLocaleString()} <span>listings</span></button>`, `${total} listings here. Zoom in.`);
        const [lng, lat] = feature.geometry.coordinates;
        button.addEventListener('click', async (event) => {
            event.stopPropagation();
            const zoom = await this.map.getSource(SOURCE).getClusterExpansionZoom(feature.properties.cluster_id);
            this.map.easeTo({ center: [lng, lat], zoom: Math.min(zoom + 0.5, 16) });
        });

        return new maplibregl.Marker({ element: wrap, anchor: 'top-left' }).setLngLat([lng, lat]);
    }

    itemMarker(m) {
        if (!m) return null;
        let html;
        if (m.kind === 'grid') {
            html = `<button type="button" class="mp-cluster mp-cluster--grid">${esc(m.label)}${m.from ? `<span>from ${esc(m.from)}</span>` : ''}</button>`;
        } else {
            const ids = esc((m.ids ?? [m.id]).join(' '));
            const approx = m.kind === 'area' || m.kind === 'approx';
            html = `<button type="button" class="mp-pin${approx ? ' mp-pin--area' : ''}" data-marker-ids="${ids}">
                ${m.kind !== 'area' && m.photo ? `<img src="${esc(m.photo)}" alt="" loading="lazy">` : ''}
                ${m.isNew ? '<span class="mp-pin__new">New</span>' : ''}
                <span class="mp-pin__label">${esc(m.label)}</span>
                ${m.from ? `<span class="mp-pin__from">${esc(m.from)}</span>` : ''}
            </button>`;
        }

        const { wrap, button } = this.element(html, markerLabel(m));
        button.addEventListener('click', (event) => {
            event.stopPropagation();
            if (m.kind === 'grid') {
                this.map.easeTo({ center: [m.lng, m.lat], zoom: Math.min(this.map.getZoom() + 2.5, 16) });
                this.map.once('moveend', () => this.onGridClick?.());
                return;
            }
            this.open(m);
        });

        return new maplibregl.Marker({ element: wrap, anchor: 'top-left' }).setLngLat([m.lng, m.lat]);
    }

    /** Open a marker's preview and tell the page which listing(s) it is. */
    open(m) {
        this.popup.setLngLat([m.lng, m.lat]).setHTML(popupHtml(m)).addTo(this.map);
        this.onSelect?.(m);
    }

    /** Marker data covering a listing id, if it is on the map. */
    find(id) {
        const key = String(id);
        return this.items.find((m) => (m.ids ?? [m.id]).map(String).includes(key));
    }

    highlight(id, on) {
        document.querySelectorAll(`.mp-ml [data-marker-ids~="${CSS.escape(String(id))}"]`).forEach((node) => {
            node.classList.toggle('is-active', on);
            node.closest('.maplibregl-marker')?.classList.toggle('is-raised', on);
        });
    }

    /** Fly to a listing and open its preview; false when it has no map location. */
    show(id) {
        const m = this.find(id);
        if (!m) return false;
        const zoom = Math.max(this.map.getZoom(), m.kind === 'area' ? 13 : 15);
        this.map.flyTo({ center: [m.lng, m.lat], zoom, essential: true });
        this.map.once('moveend', () => {
            this.open(m);
            this.highlight(id, true);
        });
        return true;
    }

    coordinates() {
        return this.items.map((m) => [Number(m.lng), Number(m.lat)]);
    }
}
