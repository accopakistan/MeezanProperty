/**
 * Freehand "draw an area" search on a MapLibre map. The user presses, draws
 * around the area and releases; the outline is simplified to at most 100
 * points (Douglas-Peucker in screen pixels) and passed to `onDone` as
 * [[lat, lng], ...], the format the search's ?poly= parameter uses.
 */
const SOURCE = 'draw-line';

const perpendicular = (p, a, b) => {
    const dx = b.x - a.x;
    const dy = b.y - a.y;
    if (dx === 0 && dy === 0) return Math.hypot(p.x - a.x, p.y - a.y);
    const t = Math.max(0, Math.min(1, ((p.x - a.x) * dx + (p.y - a.y) * dy) / (dx * dx + dy * dy)));
    return Math.hypot(p.x - (a.x + t * dx), p.y - (a.y + t * dy));
};

const douglasPeucker = (points, tolerance) => {
    if (points.length < 3) return points;
    let index = 0;
    let max = 0;
    for (let i = 1; i < points.length - 1; i++) {
        const d = perpendicular(points[i], points[0], points.at(-1));
        if (d > max) [index, max] = [i, d];
    }
    if (max <= tolerance) return [points[0], points.at(-1)];
    return [...douglasPeucker(points.slice(0, index + 1), tolerance).slice(0, -1), ...douglasPeucker(points.slice(index), tolerance)];
};

export const startDrawing = (map, { hint, cancel, onDone }) => {
    const canvas = map.getCanvasContainer();
    let pixels = [];
    let down = false;

    map.dragPan.disable();
    map.doubleClickZoom.disable();
    canvas.style.cursor = 'crosshair';
    canvas.style.touchAction = 'none';
    if (hint) hint.hidden = false;

    if (!map.getSource(SOURCE)) {
        map.addSource(SOURCE, { type: 'geojson', data: { type: 'FeatureCollection', features: [] } });
        map.addLayer({ id: SOURCE, type: 'line', source: SOURCE, paint: { 'line-color': '#4b2e83', 'line-width': 3 } });
    }
    const setLine = (coordinates) => map.getSource(SOURCE)?.setData({
        type: 'FeatureCollection',
        features: coordinates.length > 1 ? [{ type: 'Feature', geometry: { type: 'LineString', coordinates }, properties: {} }] : [],
    });

    const point = (e) => {
        const rect = canvas.getBoundingClientRect();
        return { x: e.clientX - rect.left, y: e.clientY - rect.top };
    };

    const stop = () => {
        canvas.removeEventListener('pointerdown', onDown);
        canvas.removeEventListener('pointermove', onMove);
        window.removeEventListener('pointerup', onUp);
        document.removeEventListener('keydown', onKey);
        map.dragPan.enable();
        map.doubleClickZoom.enable();
        canvas.style.cursor = '';
        canvas.style.touchAction = '';
        if (hint) hint.hidden = true;
        setLine([]);
    };

    const onDown = (e) => {
        if (e.button !== undefined && e.button !== 0) return;
        e.preventDefault();
        down = true;
        pixels = [point(e)];
    };

    const onMove = (e) => {
        if (!down) return;
        const p = point(e);
        const last = pixels.at(-1);
        if (Math.hypot(p.x - last.x, p.y - last.y) < 4) return;
        pixels.push(p);
        setLine(pixels.map((px) => map.unproject([px.x, px.y]).toArray()));
    };

    const onUp = () => {
        if (!down) return;
        down = false;
        let tolerance = 2;
        let shape = douglasPeucker(pixels, tolerance);
        while (shape.length > 100) {
            tolerance *= 1.5;
            shape = douglasPeucker(pixels, tolerance);
        }
        stop();
        if (shape.length < 3) return;
        onDone(shape.map((px) => {
            const ll = map.unproject([px.x, px.y]);
            return [Number(ll.lat.toFixed(5)), Number(ll.lng.toFixed(5))];
        }));
    };

    const onKey = (e) => e.key === 'Escape' && stop();

    canvas.addEventListener('pointerdown', onDown);
    canvas.addEventListener('pointermove', onMove);
    window.addEventListener('pointerup', onUp);
    document.addEventListener('keydown', onKey);
    cancel?.addEventListener('click', stop, { once: true });
};
