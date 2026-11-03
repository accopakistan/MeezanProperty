import * as maplibregl from 'maplibre-gl';
import workerUrl from 'maplibre-gl/dist/maplibre-gl-worker.mjs?url';
import 'maplibre-gl/dist/maplibre-gl.css';

/**
 * Shared MapLibre set-up for map search and the admin boundary editor.
 *
 * The map style is configurable (config/meezan.php "map"): a vector style
 * URL, with the raster tile server as the fallback when the style cannot
 * be loaded (provider down, blocked, or no style configured). Nothing here
 * is tied to one provider.
 */

// Vite serves the worker as its own file; MapLibre loads it from there.
maplibregl.setWorkerUrl(workerUrl);

export { maplibregl };

export const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

/** Brand colours (resources/css/app.css @theme). */
export const BRAND = { purple: '#4b2e83', purpleLight: '#6a3fb0', gold: '#d4af37', dark: '#1e0f40' };

export const rasterStyle = (raster) => {
    const url = String(raster.tiles).replace('{r}', '');
    const tiles = url.includes('{s}') ? ['a', 'b', 'c'].map((s) => url.replace('{s}', s)) : [url];

    return {
        version: 8,
        sources: { base: { type: 'raster', tiles, tileSize: 256, maxzoom: 19, attribution: raster.attribution } },
        layers: [{ id: 'base', type: 'raster', source: 'base' }],
    };
};

/**
 * Create a map with the configured style, falling back to raster tiles if the
 * style has not loaded within 8 seconds or fails outright. `onStyle` runs after
 * every (re)load of a style so callers can add their sources and layers again.
 */
export const createMap = (container, config, { onStyle, onNotice, ...options } = {}) => {
    const fallback = rasterStyle(config.raster);
    let loaded = false;
    let fellBack = !config.style;

    const map = new maplibregl.Map({
        container,
        style: config.style || fallback,
        attributionControl: { compact: true },
        dragRotate: false,
        pitchWithRotate: false,
        maxPitch: 0,
        ...options,
    });
    map.touchZoomRotate.disableRotation();
    map.keyboard.disableRotation();

    const useFallback = () => {
        if (fellBack) return;
        fellBack = true;
        onNotice?.('The detailed map style could not be loaded, so a simpler map is shown.');
        map.setStyle(fallback);
    };

    // Phones: keep the attribution behind its (i) button so it does not cover the map.
    map.once('load', () => {
        if (container.clientWidth < 640) container.querySelector('.maplibregl-ctrl-attrib')?.classList.remove('maplibregl-compact-show');
    });

    const timer = setTimeout(() => !loaded && useFallback(), 8000);
    map.on('style.load', () => {
        loaded = true;
        clearTimeout(timer);
        onStyle?.(map);
    });

    let tileErrors = 0;
    map.on('error', (event) => {
        if (!loaded && !fellBack) return useFallback();
        // Tile failures: tell the user once, keep the map and list usable.
        if (event?.tile || event?.sourceId) {
            tileErrors += 1;
            if (tileErrors === 5) onNotice?.('Some map tiles could not be loaded. Listings are still shown in the list.');
        }
    });

    return map;
};

/** [lat, lng] pairs (server format) to a closed GeoJSON ring of [lng, lat]. */
export const ringFromLatLng = (points) => {
    const ring = points.map(([lat, lng]) => [lng, lat]);
    if (ring.length && (ring[0][0] !== ring.at(-1)[0] || ring[0][1] !== ring.at(-1)[1])) ring.push(ring[0]);
    return ring;
};

export const boundsOfCoordinates = (coordinates) => {
    const bounds = new maplibregl.LngLatBounds();
    coordinates.forEach((c) => bounds.extend(c));
    return bounds;
};
