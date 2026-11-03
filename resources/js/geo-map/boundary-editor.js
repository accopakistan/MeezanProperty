import { maplibregl, createMap, boundsOfCoordinates, BRAND } from './common.js';

/**
 * Admin boundary editor: draw and edit one outline (Polygon) on a MapLibre
 * map. Vertices are draggable DOM markers; the white midpoints add a vertex
 * when dragged. The outline is kept in the form's GeoJSON textarea (the
 * field that is submitted), so pasting or importing GeoJSON works too. The
 * server validates everything again before saving.
 */
const el = document.querySelector('[data-boundary-editor]');
const config = JSON.parse(el.dataset.boundaryEditor);
const form = document.querySelector('[data-boundary-form]');
const textarea = form.querySelector('[data-be-geojson]');
const status = document.querySelector('[data-be-status]');
const button = (name) => form.querySelector(`[data-be="${name}"]`);

let ring = []; // open ring of [lng, lat]; closed only when written out
let history = [];
let drawing = false;
let selected = null;
let complex = false; // MultiPolygon / holes: shown, edited as GeoJSON only
let vertexMarkers = [];
let midMarkers = [];

const say = (text) => {
    if (status) status.textContent = text;
};

const map = createMap(el, config, {
    ...(config.center ? { center: config.center, zoom: 14 } : { bounds: config.pakistan }),
    onNotice: say,
    onStyle: () => {
        addLayers();
        render();
        say(config.editable ? 'Ready.' : 'Read-only: this outline is not a draft.');
    },
});
map.addControl(new maplibregl.NavigationControl({ showCompass: false }), 'top-right');

/* Reading and writing the GeoJSON field ------------------------------------------------ */
const parseField = () => {
    const text = textarea.value.trim();
    if (!text) return { ring: [], complex: false, geometry: null };
    try {
        let g = JSON.parse(text);
        if (g.type === 'FeatureCollection') g = g.features?.[0];
        if (g?.type === 'Feature') g = g.geometry;
        if (g?.type === 'Polygon' && g.coordinates.length === 1) {
            return { ring: g.coordinates[0].slice(0, -1).map(([x, y]) => [Number(x), Number(y)]), complex: false, geometry: g };
        }
        if (g?.type === 'Polygon' || g?.type === 'MultiPolygon') return { ring: [], complex: true, geometry: g };
    } catch {
        // Fall through: the server explains what is wrong.
    }
    return null;
};

const writeField = () => {
    if (complex) return;
    textarea.value = ring.length >= 3 ? JSON.stringify({ type: 'Polygon', coordinates: [[...ring, ring[0]].map(([x, y]) => [Number(x.toFixed(6)), Number(y.toFixed(6))])] }) : '';
};

let fieldGeometry = null;
const loadField = () => {
    const parsed = parseField();
    if (!parsed) {
        say('The GeoJSON could not be read. It must be a Polygon or MultiPolygon.');
        return;
    }
    ring = parsed.ring;
    complex = parsed.complex;
    fieldGeometry = parsed.geometry;
    history = [];
    selected = null;
    render();
    if (complex) say('This shape has holes or several parts: it is shown on the map and can be changed in the GeoJSON field only.');
};

/* Map layers --------------------------------------------------------------------------- */
const addLayers = () => {
    if (map.getSource('outline')) return;
    map.addSource('neighbours', { type: 'geojson', data: config.neighbours });
    map.addLayer({ id: 'neighbours-line', type: 'line', source: 'neighbours', paint: { 'line-color': '#64748b', 'line-width': 1.5, 'line-dasharray': [2, 2] } });
    map.addSource('outline', { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
    map.addLayer({ id: 'outline-fill', type: 'fill', source: 'outline', filter: ['==', ['geometry-type'], 'Polygon'], paint: { 'fill-color': BRAND.purpleLight, 'fill-opacity': 0.15 } });
    map.addLayer({ id: 'outline-line', type: 'line', source: 'outline', paint: { 'line-color': BRAND.purple, 'line-width': 2.5 } });
    if (config.areaCentre) {
        new maplibregl.Marker({ color: BRAND.gold, scale: 0.7 }).setLngLat(config.areaCentre).setPopup(new maplibregl.Popup().setText('Recorded centre of this location')).addTo(map);
    }
};

const outlineData = () => {
    if (complex && fieldGeometry) return { type: 'FeatureCollection', features: [{ type: 'Feature', geometry: fieldGeometry, properties: {} }] };
    if (ring.length >= 3) return { type: 'FeatureCollection', features: [{ type: 'Feature', geometry: { type: 'Polygon', coordinates: [[...ring, ring[0]]] }, properties: {} }] };
    if (ring.length === 2) return { type: 'FeatureCollection', features: [{ type: 'Feature', geometry: { type: 'LineString', coordinates: ring }, properties: {} }] };
    return { type: 'FeatureCollection', features: [] };
};

const markerElement = (className, label) => {
    const node = document.createElement('button');
    node.type = 'button';
    node.className = className;
    node.setAttribute('aria-label', label);
    return node;
};

const render = () => {
    map.getSource('outline')?.setData(outlineData());
    vertexMarkers.forEach((m) => m.remove());
    midMarkers.forEach((m) => m.remove());
    vertexMarkers = [];
    midMarkers = [];
    const del = button('delete');
    if (del) del.disabled = selected === null;
    if (complex || !config.editable) return;

    ring.forEach((point, i) => {
        const node = markerElement(`be-vertex${i === selected ? ' is-selected' : ''}`, `Point ${i + 1}`);
        node.addEventListener('click', (e) => {
            e.stopPropagation();
            selected = i;
            render();
        });
        const marker = new maplibregl.Marker({ element: node, draggable: true }).setLngLat(point).addTo(map);
        marker.on('dragstart', () => remember());
        marker.on('drag', () => {
            const ll = marker.getLngLat();
            ring[i] = [ll.lng, ll.lat];
            map.getSource('outline')?.setData(outlineData());
        });
        marker.on('dragend', () => {
            writeField();
            render();
        });
        vertexMarkers.push(marker);
    });

    if (ring.length >= 2) {
        const segments = ring.length >= 3 ? ring.length : 1;
        for (let i = 0; i < segments; i++) {
            const a = ring[i];
            const b = ring[(i + 1) % ring.length];
            const node = markerElement('be-midpoint', `Add a point between points ${i + 1} and ${((i + 1) % ring.length) + 1}`);
            const marker = new maplibregl.Marker({ element: node, draggable: true }).setLngLat([(a[0] + b[0]) / 2, (a[1] + b[1]) / 2]).addTo(map);
            marker.on('dragstart', () => {
                remember();
                ring.splice(i + 1, 0, [marker.getLngLat().lng, marker.getLngLat().lat]);
            });
            marker.on('drag', () => {
                const ll = marker.getLngLat();
                ring[i + 1] = [ll.lng, ll.lat];
                map.getSource('outline')?.setData(outlineData());
            });
            marker.on('dragend', () => {
                writeField();
                render();
            });
            midMarkers.push(marker);
        }
    }
};

const remember = () => {
    history.push(ring.map((p) => [...p]));
    if (history.length > 100) history.shift();
};

/* Tools -------------------------------------------------------------------------------- */
const setDrawing = (on) => {
    drawing = on && config.editable && !complex;
    const b = button('draw');
    b?.setAttribute('aria-pressed', String(drawing));
    const label = form.querySelector('[data-be-draw-label]');
    if (label) label.textContent = drawing ? 'Stop adding' : 'Add points';
    map.getCanvas().style.cursor = drawing ? 'crosshair' : '';
    say(drawing ? 'Click the map to add points around the edge. Press Stop adding when done.' : `${ring.length} points.`);
};

map.on('click', (e) => {
    if (!drawing) {
        if (selected !== null) {
            selected = null;
            render();
        }
        return;
    }
    remember();
    ring.push([e.lngLat.lng, e.lngLat.lat]);
    writeField();
    render();
    say(`${ring.length} points${ring.length < 3 ? ' (at least 3 needed)' : ''}.`);
});

button('draw')?.addEventListener('click', () => setDrawing(!drawing));
button('undo')?.addEventListener('click', () => {
    if (!history.length) return;
    ring = history.pop();
    selected = null;
    writeField();
    render();
});
const deleteSelected = () => {
    if (selected === null) return;
    remember();
    ring.splice(selected, 1);
    selected = null;
    writeField();
    render();
};
button('delete')?.addEventListener('click', deleteSelected);
document.addEventListener('keydown', (e) => {
    if ((e.key === 'Delete' || e.key === 'Backspace') && selected !== null && !e.target.closest('input, textarea, select')) {
        e.preventDefault();
        deleteSelected();
    }
});
button('clear')?.addEventListener('click', () => {
    if (!ring.length && !complex) return;
    remember();
    ring = [];
    complex = false;
    fieldGeometry = null;
    selected = null;
    writeField();
    render();
    setDrawing(true);
});
const fit = () => {
    const coordinates = complex && fieldGeometry
        ? (fieldGeometry.type === 'Polygon' ? fieldGeometry.coordinates.flat() : fieldGeometry.coordinates.flat(2))
        : ring;
    if (coordinates.length) map.fitBounds(boundsOfCoordinates(coordinates), { padding: 50, maxZoom: 17 });
};
button('fit')?.addEventListener('click', fit);

// Pasted or imported GeoJSON replaces the outline on the map.
textarea.addEventListener('change', loadField);
form.querySelector('[data-be-file]')?.addEventListener('change', async (e) => {
    const file = e.target.files?.[0];
    if (!file) return;
    if (file.size > 2_000_000) {
        say('That file is larger than 2 MB. Simplify the shape first.');
        return;
    }
    textarea.value = await file.text();
    loadField();
    fit();
});

// Warn before leaving with unsaved changes.
let dirty = false;
const initial = textarea.value;
form.addEventListener('input', () => (dirty = true));
map.on('click', () => (dirty = textarea.value !== initial));
form.addEventListener('submit', (e) => {
    if (!complex && ring.length && ring.length < 3) {
        e.preventDefault();
        say('An outline needs at least 3 points.');
        return;
    }
    dirty = false;
});
window.addEventListener('beforeunload', (e) => {
    if (dirty || textarea.value !== initial) {
        if (!form.dataset.submitting) e.preventDefault();
    }
});
form.addEventListener('submit', () => (form.dataset.submitting = '1'));

loadField();
map.once('load', () => {
    if (ring.length || complex) fit();
    setDrawing(config.editable && !ring.length && !complex);
});
