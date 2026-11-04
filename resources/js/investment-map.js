import { maplibregl, createMap, boundsOfCoordinates, BRAND, esc } from './geo-map/common.js';

/**
 * Investment map: ranked and tracked entries as clustered GeoJSON circles on
 * the shared MapLibre set-up (style with raster fallback). Colour = status;
 * a white ring marks an approximate (area-centre) position.
 */
const SOURCE = 'investments';
const COLOURS = { ranked: '#059669', provisional: '#f59e0b', review: '#0284c7', insufficient: '#8c66c8' };

export class InvestmentMap {
    constructor(el, config, markers, { onSelect } = {}) {
        this.markers = markers;
        this.onSelect = onSelect;
        this.popup = new maplibregl.Popup({ className: 'mp-popup', maxWidth: '260px', offset: 12, focusAfterOpen: false });
        this.map = createMap(el, config, {
            onStyle: (map) => this.addLayers(map),
            center: config.center ?? [73.0, 30.4],
            zoom: config.center ? 10.5 : 4.6,
            maxBounds: [[config.pakistan[0][0] - 6, config.pakistan[0][1] - 4], [config.pakistan[1][0] + 6, config.pakistan[1][1] + 4]],
        });
        this.map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');
        this.map.once('load', () => {
            if (markers.length > 1) this.map.fitBounds(boundsOfCoordinates(markers.map((m) => [m.lng, m.lat])), { padding: 48, maxZoom: 14, duration: 0 });
            else if (markers.length === 1) this.map.jumpTo({ center: [markers[0].lng, markers[0].lat], zoom: 13 });
        });
    }

    geojson() {
        return {
            type: 'FeatureCollection',
            features: this.markers.map((m) => ({ type: 'Feature', geometry: { type: 'Point', coordinates: [m.lng, m.lat] }, properties: { key: m.key, status: m.status, approximate: m.approximate ? 1 : 0 } })),
        };
    }

    addLayers(map) {
        if (map.getSource(SOURCE)) return;
        map.addSource(SOURCE, { type: 'geojson', data: this.geojson(), cluster: true, clusterRadius: 44, clusterMaxZoom: 13 });
        map.addLayer({ id: 'inv-clusters', type: 'circle', source: SOURCE, filter: ['has', 'point_count'],
            paint: { 'circle-color': BRAND.purple, 'circle-radius': ['step', ['get', 'point_count'], 16, 10, 20, 50, 26], 'circle-stroke-width': 3, 'circle-stroke-color': '#ffffff' } });
        map.addLayer({ id: 'inv-cluster-count', type: 'symbol', source: SOURCE, filter: ['has', 'point_count'],
            layout: { 'text-field': ['get', 'point_count_abbreviated'], 'text-size': 12 }, paint: { 'text-color': '#ffffff' } });
        map.addLayer({ id: 'inv-points', type: 'circle', source: SOURCE, filter: ['!', ['has', 'point_count']],
            paint: {
                'circle-color': ['match', ['get', 'status'], 'ranked', COLOURS.ranked, 'provisional', COLOURS.provisional, 'review', COLOURS.review, COLOURS.insufficient],
                'circle-radius': 9,
                'circle-stroke-width': ['case', ['==', ['get', 'approximate'], 1], 3, 2],
                'circle-stroke-color': ['case', ['==', ['get', 'approximate'], 1], BRAND.purple, '#ffffff'],
                'circle-stroke-opacity': ['case', ['==', ['get', 'approximate'], 1], 0.55, 1],
            } });

        map.on('click', 'inv-clusters', async (e) => {
            const feature = e.features[0];
            const zoom = await map.getSource(SOURCE).getClusterExpansionZoom(feature.properties.cluster_id);
            map.easeTo({ center: feature.geometry.coordinates, zoom });
        });
        map.on('click', 'inv-points', (e) => this.open(e.features[0].properties.key));
        ['inv-clusters', 'inv-points'].forEach((id) => {
            map.on('mouseenter', id, () => (map.getCanvas().style.cursor = 'pointer'));
            map.on('mouseleave', id, () => (map.getCanvas().style.cursor = ''));
        });
    }

    open(key) {
        const m = this.markers.find((x) => x.key === key);
        if (!m) return;
        const demo = m.demo ? '<span class="mb-1 inline-block rounded bg-[#d4af37] px-1.5 text-[10px] font-extrabold text-[#1e0f40]">Illustrative data</span>' : '';
        const html = `<div class="p-3 text-[13px]">${demo}<strong class="block">${esc(m.name)}</strong><span class="block text-[12px] text-muted">${esc(m.location)}</span>
            <span class="mt-1 block">${m.score !== null ? `MPIS <strong>${esc(m.score)}</strong>` : esc(m.status_label)}${m.price ? ` · ${esc(m.price)}` : ''}${m.yield !== null ? ` · yield ${esc(m.yield)}%` : ''}</span>
            ${m.approximate ? '<span class="block text-[11px] text-muted">Approximate: area centre</span>' : ''}
            <button type="button" class="link mt-1 text-[12px]" data-map-open>View performance →</button></div>`;
        this.popup.setLngLat([m.lng, m.lat]).setHTML(html).addTo(this.map);
        this.popup.getElement()?.querySelector('[data-map-open]')?.addEventListener('click', () => this.onSelect?.(m));
    }

    /** Centre on an entry chosen in the list (no pop-up if it has no position). */
    highlight(key) {
        const m = this.markers.find((x) => x.key === key);
        if (!m) return;
        const go = () => {
            this.map.easeTo({ center: [m.lng, m.lat], zoom: Math.max(this.map.getZoom(), 13) });
            this.open(key);
        };
        this.map.loaded() ? go() : this.map.once('load', go);
    }
}
