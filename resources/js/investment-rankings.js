/**
 * Investment Rankings pages (city, group and property-type dashboards).
 *
 * Everything here is an enhancement: every link and form also works as a
 * normal page load. With JavaScript:
 * - tabs, sorting, filters and pages swap the results block in place
 *   (X-Rankings-Partial), and the URL follows so links can be shared;
 * - choosing an entry swaps the performance panel; its sections become tabs;
 * - ticked entries collect in the compare tray (up to four);
 * - the AI advisor asks the JSON endpoint and answers in place;
 * - the map (shared MapLibre set-up) shows entries, clustered, in sync with the list.
 */
const PARTIAL = { 'X-Rankings-Partial': '1', Accept: 'text/html' };
const page = document.querySelector('[data-rankings-page]');

const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

const fetchPartial = async (url) => {
    const response = await fetch(url, { headers: PARTIAL, credentials: 'same-origin' });
    if (!response.ok) throw new Error(`HTTP ${response.status}`);
    const template = document.createElement('template');
    template.innerHTML = (await response.text()).trim();
    return template.content.firstElementChild;
};

const busy = (el, on) => {
    el?.classList.toggle('opacity-60', on);
    el?.setAttribute('aria-busy', on ? 'true' : 'false');
};

/* Filters fold on phones ------------------------------------------------ */
if (window.matchMedia('(max-width: 639px)').matches) {
    document.querySelectorAll('details[data-fold-on-mobile]').forEach((d) => d.removeAttribute('open'));
}

/* Results: tabs, sorting, pages, filters --------------------------------- */
const swapResults = async (url, { push = true } = {}) => {
    const current = document.querySelector('[data-rankings-results]');
    if (!current) return (window.location.href = url);
    busy(current, true);
    try {
        const fresh = await fetchPartial(url);
        current.replaceWith(fresh);
        if (push) history.pushState({ rankings: true }, '', url);
        syncTabs(url);
        restoreCompare();
        fresh.querySelector('h2')?.setAttribute('tabindex', '-1');
    } catch {
        window.location.href = url;
    }
};

const syncTabs = (url) => {
    const tab = new URL(url, window.location.href).searchParams.get('tab') ?? 'all';
    document.querySelectorAll('[data-rankings-tabs] a').forEach((a) => {
        const on = (new URL(a.href).searchParams.get('tab') ?? 'all') === tab;
        a.toggleAttribute('aria-current', on);
        a.classList.toggle('bg-brand-600', on);
        a.classList.toggle('text-white', on);
        a.classList.toggle('ring-brand-600', on);
        a.classList.toggle('bg-white', !on);
        a.classList.toggle('text-ink', !on);
    });
};

/* Performance panel -------------------------------------------------------- */
const swapPerformance = async (url, { push = true } = {}) => {
    const panel = document.querySelector('[data-rankings-performance]');
    if (!panel) return (window.location.href = url);
    const partUrl = new URL(url, window.location.href);
    partUrl.searchParams.set('part', 'performance');
    partUrl.hash = '';
    busy(panel, true);
    try {
        const fresh = await fetchPartial(partUrl);
        panel.replaceWith(fresh);
        initTabs(fresh);
        const clean = new URL(url, window.location.href);
        clean.searchParams.delete('part');
        if (push) history.pushState({ rankings: true }, '', clean);
        const key = clean.searchParams.get('selected');
        document.querySelectorAll('[data-item-key]').forEach((row) => row.classList.toggle('bg-brand-50/60', row.dataset.itemKey === key));
        map?.highlight(key);
        if (fresh.getBoundingClientRect().top > window.innerHeight || fresh.getBoundingClientRect().top < 0) fresh.scrollIntoView({ behavior: 'smooth', block: 'start' });
    } catch {
        window.location.href = url;
    }
};

const initTabs = (root) => {
    root.querySelectorAll('[data-tabs]').forEach((tabs) => {
        const list = tabs.querySelector('[data-tab-list]');
        if (!list) return;
        const buttons = [...list.querySelectorAll('[data-tab]')];
        const panels = [...tabs.querySelectorAll('[data-panel]')];
        list.hidden = false;
        const show = (name, focus = false) => {
            buttons.forEach((b) => {
                const on = b.dataset.tab === name;
                b.setAttribute('aria-selected', on ? 'true' : 'false');
                b.tabIndex = on ? 0 : -1;
                if (on && focus) b.focus();
            });
            panels.forEach((p) => (p.hidden = p.dataset.panel !== name));
        };
        // Coming back from a chart-period link: keep the Price Trend tab open.
        show(new URL(window.location.href).searchParams.has('period') ? 'trend' : buttons[0].dataset.tab);
        list.addEventListener('click', (e) => {
            const b = e.target.closest('[data-tab]');
            if (b) show(b.dataset.tab);
        });
        list.addEventListener('keydown', (e) => {
            const i = buttons.findIndex((b) => b.getAttribute('aria-selected') === 'true');
            const next = e.key === 'ArrowRight' ? i + 1 : e.key === 'ArrowLeft' ? i - 1 : null;
            if (next === null) return;
            e.preventDefault();
            show(buttons[(next + buttons.length) % buttons.length].dataset.tab, true);
        });
    });
};
initTabs(document);

/* Delegated clicks and submits -------------------------------------------- */
document.addEventListener('click', (e) => {
    if (e.defaultPrevented || e.button !== 0 || e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;
    const link = e.target.closest('a');
    if (!link || !page?.contains(link) && !document.querySelector('[data-rankings-tabs]')?.contains(link)) return;

    if (link.matches('[data-select-item]') || link.matches('[data-performance-link]')) {
        if (!document.querySelector('[data-rankings-performance]')) return;
        e.preventDefault();
        swapPerformance(link.href);
    } else if (link.matches('[data-rankings-link]') || link.closest('[data-rankings-pages]')) {
        e.preventDefault();
        swapResults(link.href);
    }
});

document.addEventListener('submit', (e) => {
    const form = e.target;
    if (form.matches('[data-rankings-filter]')) {
        e.preventDefault();
        const url = new URL(window.location.href);
        url.search = new URLSearchParams(new FormData(form)).toString();
        [...url.searchParams.keys()].forEach((k) => !url.searchParams.get(k) && url.searchParams.delete(k));
        swapResults(url.toString());
    } else if (form.matches('[data-advisor-form]')) {
        e.preventDefault();
        ask(form, e.submitter?.name === 'example' ? e.submitter.value : form.elements.ask.value);
    }
});

window.addEventListener('popstate', () => {
    if (document.querySelector('[data-rankings-results]')) window.location.reload();
});

/* Compare tray -------------------------------------------------------------- */
const MAX_COMPARE = 4;
const storeKey = `rankings-compare:${window.location.pathname}`;
const readCompare = () => {
    try {
        return JSON.parse(sessionStorage.getItem(storeKey) || '[]');
    } catch {
        return [];
    }
};
const writeCompare = (items) => {
    try {
        sessionStorage.setItem(storeKey, JSON.stringify(items));
    } catch {
        /* private mode: the tray still works for this page view */
    }
};
let compare = readCompare();

const renderTray = () => {
    const tray = document.querySelector('[data-compare-tray]');
    if (!tray) return;
    const list = tray.querySelector('[data-compare-list]');
    list.innerHTML = compare.map((c) => `<li class="flex items-center gap-1.5 rounded-full bg-brand-50 py-1 pr-1 pl-3 text-[12px] font-semibold text-brand-800">${esc(c.name)}
        <button type="button" class="grid size-5 place-items-center rounded-full hover:bg-brand-100" data-compare-remove="${esc(c.key)}" aria-label="Remove ${esc(c.name)}">×</button></li>`).join('');
    tray.querySelector('[data-compare-empty]').hidden = compare.length > 0;
    const now = tray.querySelector('[data-compare-now]');
    const url = new URL(now.href);
    url.search = new URLSearchParams(compare.map((c) => ['items[]', c.key])).toString();
    now.href = url.toString();
    now.classList.toggle('pointer-events-none', compare.length < 2);
    now.classList.toggle('opacity-50', compare.length < 2);
    now.setAttribute('aria-disabled', compare.length < 2 ? 'true' : 'false');
};

const restoreCompare = () => {
    document.querySelectorAll('[data-compare-check]').forEach((box) => (box.checked = compare.some((c) => c.key === box.value)));
    renderTray();
};

document.addEventListener('change', (e) => {
    const box = e.target.closest('[data-compare-check]');
    if (!box) return;
    const name = box.closest('[data-item-key]')?.querySelector('[data-select-item]')?.textContent.trim() ?? box.value;
    compare = compare.filter((c) => c.key !== box.value);
    if (box.checked) {
        if (compare.length >= MAX_COMPARE) {
            box.checked = false;
            window.alert(`You can compare up to ${MAX_COMPARE} entries.`);
            return;
        }
        compare.push({ key: box.value, name });
    }
    writeCompare(compare);
    restoreCompare();
});
document.addEventListener('click', (e) => {
    const remove = e.target.closest('[data-compare-remove]');
    if (!remove) return;
    compare = compare.filter((c) => c.key !== remove.dataset.compareRemove);
    writeCompare(compare);
    restoreCompare();
});
restoreCompare();

/* AI Investment Advisor ------------------------------------------------------ */
const ask = async (form, question) => {
    const box = form.closest('[data-advisor]');
    const answer = box.querySelector('[data-advisor-answer]');
    question = String(question || '').trim();
    if (question.length < 3) return form.elements.ask.focus();
    const params = new URLSearchParams({ question, focus: form.elements.focus?.value || 'best' });
    if (box.dataset.city) params.set('city', box.dataset.city);
    if (box.dataset.segment) params.set('segment', box.dataset.segment);
    answer.innerHTML = `<p class="rounded-lg bg-brand-50 px-3 py-2 text-[13px] font-semibold text-brand-800">${esc(question)}</p><p class="text-[13px] text-muted">Checking the published rankings…</p>`;
    try {
        const response = await fetch(`${box.dataset.endpoint}?${params}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
        if (response.status === 429) throw new Error('Too many questions in a minute. Please wait a moment.');
        if (!response.ok) throw new Error('The advisor could not answer just now.');
        const data = await response.json();
        const items = data.items.map((i) => `<li class="flex gap-2.5 rounded-lg p-1.5 ring-1 ring-line">
            ${i.image ? `<img src="${esc(i.image)}" alt="" class="size-12 shrink-0 rounded object-cover">` : ''}
            <a href="${esc(i.url)}" class="min-w-0 text-[12px]"><strong class="block text-brand-700">${esc(i.name)}${i.group ? ` · ${esc(i.group)}` : ''} — MPIS ${esc(i.score)}</strong>
            <span class="text-muted">${esc(i.location)} · ${esc(i.confidence)}${i.yield !== null ? ` · yield ${esc(i.yield)}%` : ''}</span></a></li>`).join('');
        answer.innerHTML = `<p class="rounded-lg bg-brand-50 px-3 py-2 text-[13px] font-semibold text-brand-800">${esc(question)}</p>
            <div class="space-y-1 text-[13px]">${data.answer.map((l) => `<p>${esc(l)}</p>`).join('')}</div>
            ${items ? `<ul class="space-y-1.5">${items}</ul>` : ''}
            ${data.missing.length ? `<p class="text-[12px] text-muted">Missing: ${esc(data.missing.join(' '))}</p>` : ''}
            <p class="text-[11px] text-muted">Source: Meezan Property published rankings${data.methodology ? ` (methodology v${esc(data.methodology)})` : ''}${data.sources.length ? `; data from ${esc(data.sources.join(', '))}` : ''}. ${{ 'llm-agent': 'Answered by the Investment Analyst agent (Central AI Brain); worded by AI and checked against the data.', 'rules-agent': 'Answered by the Investment Analyst agent (Central AI Brain) from the data.', llm: 'Worded by AI, checked against the data.' }[data.mode] ?? 'Answered directly from the data.'}
            <a href="${esc(data.link)}" class="link">Open the full ranking</a></p>`;
    } catch (error) {
        answer.innerHTML = `<p class="text-[13px] text-red-700">${esc(error.message || 'The advisor could not answer just now.')}</p>`;
    }
};

/* Investment map ---------------------------------------------------------------- */
let map = null;
const mapEl = document.querySelector('[data-investment-map]');
if (mapEl) {
    import('./investment-map.js').then(({ InvestmentMap }) => {
        map = new InvestmentMap(mapEl, JSON.parse(mapEl.dataset.investmentMap), JSON.parse(mapEl.dataset.markers || '[]'), {
            onSelect: (marker) => (document.querySelector('[data-rankings-performance]') ? swapPerformance(marker.url) : (window.location.href = marker.url)),
        });
        const selected = new URL(window.location.href).searchParams.get('selected');
        if (selected) map.highlight(selected);
    });
}
