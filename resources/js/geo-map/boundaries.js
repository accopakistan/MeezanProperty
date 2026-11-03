import { maplibregl, esc, BRAND } from './common.js';

/**
 * Published society / area outlines (admin-verified GeoJSON) for the
 * visible part of the map, loaded only from `minZoom` and only for the
 * current viewport. Hover highlights an outline; clicking it shows its real
 * listing counts with links. Drafts never reach this layer.
 */
const SOURCE = 'boundaries';

export class BoundaryLayer {
    constructor(map, { url, detailUrl, minZoom, onSearchArea }) {
        this.map = map;
        this.url = url;
        this.detailUrl = detailUrl;
        this.minZoom = minZoom;
        this.onSearchArea = onSearchArea;
        this.data = { type: 'FeatureCollection', features: [] };
        this.lastKey = null;
        this.hovered = null;
        this.selected = null;
        // Closed by hand (below): the built-in close-on-click would also close a pop-up reopened by the same click.
        this.popup = new maplibregl.Popup({ className: 'mp-popup', maxWidth: '280px', focusAfterOpen: false, closeOnClick: false });
        this.popup.on('close', () => this.select(null));

        let timer;
        map.on('moveend', () => {
            clearTimeout(timer);
            timer = setTimeout(() => this.load(), 300);
        });
    }

    attach() {
        if (this.map.getSource(SOURCE)) return;
        this.map.addSource(SOURCE, { type: 'geojson', data: this.data, promoteId: 'id' });
        this.map.addLayer({
            id: 'boundaries-fill',
            type: 'fill',
            source: SOURCE,
            paint: {
                'fill-color': ['case', ['boolean', ['feature-state', 'selected'], false], BRAND.gold, BRAND.purpleLight],
                'fill-opacity': ['case', ['boolean', ['feature-state', 'selected'], false], 0.22, ['boolean', ['feature-state', 'hover'], false], 0.18, 0.07],
            },
        });
        this.map.addLayer({
            id: 'boundaries-line',
            type: 'line',
            source: SOURCE,
            paint: {
                'line-color': ['case', ['boolean', ['feature-state', 'selected'], false], BRAND.gold, BRAND.purple],
                'line-width': ['case', ['any', ['boolean', ['feature-state', 'selected'], false], ['boolean', ['feature-state', 'hover'], false]], 3, 1.5],
                'line-opacity': 0.85,
            },
        });

        if (!this.bound) {
            this.bound = true;
            this.map.on('mousemove', 'boundaries-fill', (e) => this.hover(e.features?.[0]?.id ?? null));
            this.map.on('mouseleave', 'boundaries-fill', () => this.hover(null));
            this.map.on('click', (e) => {
                if (!this.map.getLayer('boundaries-fill')) return;
                if (!this.map.queryRenderedFeatures(e.point, { layers: ['boundaries-fill'] }).length) this.popup.remove();
            });
            this.map.on('click', 'boundaries-fill', (e) => {
                // Listing markers sit above and handle their own clicks.
                const feature = e.features?.[0];
                if (feature) this.open(feature, e.lngLat);
            });
        }
        this.lastKey = null;
        this.load();
    }

    async load() {
        if (!this.map.getSource(SOURCE)) return;
        if (this.map.getZoom() < this.minZoom) {
            if (this.data.features.length) this.set({ type: 'FeatureCollection', features: [] });
            this.lastKey = null;
            return;
        }

        const b = this.map.getBounds();
        const params = new URLSearchParams({
            north: b.getNorth().toFixed(4), south: b.getSouth().toFixed(4), east: b.getEast().toFixed(4), west: b.getWest().toFixed(4),
        });
        const key = params.toString();
        if (key === this.lastKey) return;
        this.lastKey = key;

        this.request?.abort();
        this.request = new AbortController();
        try {
            const response = await fetch(`${this.url}?${params}`, { headers: { Accept: 'application/json' }, signal: this.request.signal });
            if (!response.ok) return;
            this.set(await response.json());
        } catch (error) {
            // A failed outline request never affects listings; try again on the next move.
            if (error.name !== 'AbortError') this.lastKey = null;
        }
    }

    set(data) {
        this.data = data?.type === 'FeatureCollection' ? data : { type: 'FeatureCollection', features: [] };
        this.hovered = null;
        this.map.getSource(SOURCE)?.setData(this.data);
    }

    state(id, state) {
        if (id !== null && id !== undefined) this.map.setFeatureState({ source: SOURCE, id }, state);
    }

    hover(id) {
        if (id === this.hovered) return;
        this.state(this.hovered, { hover: false });
        this.hovered = id;
        this.state(id, { hover: true });
        this.map.getCanvas().style.cursor = id === null ? '' : 'pointer';
    }

    select(id) {
        this.state(this.selected, { selected: false });
        this.selected = id;
        this.state(id, { selected: true });
    }

    async open(feature, lngLat) {
        const p = feature.properties;
        this.select(feature.id);
        this.popup.setLngLat(lngLat).setHTML(`<div class="mp-popup__area">
            <p class="mp-popup__head">${esc(p.name)}</p>
            <p class="mp-popup__note">${esc(p.city)} · ${Number(p.listings).toLocaleString()} live ${Number(p.listings) === 1 ? 'listing' : 'listings'}</p>
            <div data-boundary-detail><p class="mp-popup__note">Loading…</p></div>
        </div>`).addTo(this.map);

        try {
            const response = await fetch(this.detailUrl.replace(/\/0$/, `/${p.id}`), { headers: { Accept: 'application/json' } });
            if (!response.ok) throw new Error(String(response.status));
            const d = await response.json();
            const slot = this.popup.getElement()?.querySelector('[data-boundary-detail]');
            if (!slot) return;
            slot.innerHTML = `${d.categories.length ? `<ul class="mp-popup__cats">${d.categories.map((c) => `<li><a href="${esc(c.url)}">${esc(c.label)}</a><span>${Number(c.count).toLocaleString()}</span></li>`).join('')}</ul>` : '<p class="mp-popup__note">No live listings here yet.</p>'}
                ${d.listings ? `<a href="${esc(d.mapUrl)}" class="mp-popup__all" data-map-area-link>Show ${Number(d.listings).toLocaleString()} on the map →</a>` : ''}
                <a href="${esc(d.guideUrl)}" class="mp-popup__link">${esc(d.name)} area guide</a>`;
        } catch {
            const slot = this.popup.getElement()?.querySelector('[data-boundary-detail]');
            if (slot) slot.innerHTML = '<p class="mp-popup__note">Details could not be loaded.</p>';
        }
    }
}
