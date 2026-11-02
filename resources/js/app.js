import './bootstrap';

const csrf = () => document.querySelector('meta[name="csrf-token"]')?.content;
const esc = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);

/* Disclosure toggles: <button data-toggle="panel-id"> ------------------- */
document.addEventListener('click', (event) => {
    const trigger = event.target.closest('[data-toggle]');
    if (!trigger) return;

    const panel = document.getElementById(trigger.dataset.toggle);
    if (!panel) return;

    // Panels hide with the `hidden` utility so responsive classes (lg:block) can override it.
    const open = panel.classList.contains('hidden');
    panel.classList.toggle('hidden', !open);
    trigger.setAttribute('aria-expanded', String(open));
    document.body.classList.toggle('overflow-hidden', open && trigger.hasAttribute('data-lock-scroll'));
});

/* Dialogs: <button data-dialog-open="id">, <button data-dialog-close> ----- */
document.addEventListener('click', (event) => {
    const opener = event.target.closest('[data-dialog-open]');
    if (opener) {
        document.getElementById(opener.dataset.dialogOpen)?.showModal();
        return;
    }

    const closer = event.target.closest('[data-dialog-close]');
    if (closer) {
        closer.closest('dialog')?.close();
        return;
    }

    // Click on the backdrop closes the dialog.
    if (event.target instanceof HTMLDialogElement) {
        event.target.close();
    }
});

/* Auto-submit form controls ------------------------------------------- */
document.addEventListener('change', (event) => {
    const control = event.target.closest('[data-autosubmit]');
    if (control?.form) {
        control.form.requestSubmit();
        return;
    }

    // Filter sidebars apply as soon as a choice changes (the free-text area search excluded).
    const form = event.target.closest('form[data-autosubmit-form]');
    if (form && !event.target.matches('[data-filter-input]')) form.requestSubmit();
});

/* Searchable checkbox lists with "Show more" -------------------------- */
document.querySelectorAll('[data-filter-list]').forEach((list) => {
    const input = list.querySelector('[data-filter-input]');
    const more = list.querySelector('[data-show-more]');
    const items = [...list.querySelectorAll('[data-filter-item]')];
    let expanded = false;

    const render = () => {
        const term = input.value.trim().toLowerCase();
        items.forEach((item) => {
            const matches = !term || item.dataset.filterItem.includes(term);
            item.hidden = !matches || (!term && !expanded && item.hasAttribute('data-extra'));
        });
        if (more) more.hidden = !!term || !items.some((i) => i.hasAttribute('data-extra'));
        if (more) more.firstChild.textContent = expanded ? 'Show Less ' : 'Show More ';
    };

    input?.addEventListener('input', render);
    input?.addEventListener('keydown', (e) => e.key === 'Enter' && e.preventDefault());
    more?.addEventListener('click', () => { expanded = !expanded; render(); });
    render();
});

/* Save / unsave a property without a page reload ----------------------- */
document.addEventListener('submit', async (event) => {
    const form = event.target.closest('form[data-save-form]');
    if (!form) return;

    event.preventDefault();
    const button = form.querySelector('button');
    button.disabled = true;

    try {
        const response = await fetch(form.action, {
            method: 'POST',
            headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf() },
        });

        if (response.status === 401) {
            window.location.href = form.dataset.loginUrl;
            return;
        }
        if (!response.ok) throw new Error('Save failed');

        const { saved } = await response.json();
        document.querySelectorAll(`form[data-save-form][action="${form.action}"] button`).forEach((b) => {
            b.setAttribute('aria-pressed', String(saved));
            b.setAttribute('aria-label', saved ? 'Remove from saved' : 'Save property');
        });
    } catch {
        form.submit();
    } finally {
        button.disabled = false;
    }
});

/* Share --------------------------------------------------------------- */
document.addEventListener('click', async (event) => {
    const button = event.target.closest('[data-share]');
    if (!button) return;

    const data = { title: document.title, url: button.dataset.share };
    if (navigator.share) {
        try { await navigator.share(data); } catch { /* cancelled */ }
        return;
    }

    await navigator.clipboard?.writeText(data.url);
    const label = button.querySelector('[data-share-label]');
    if (label) {
        const previous = label.textContent;
        label.textContent = 'Link copied';
        setTimeout(() => (label.textContent = previous), 2000);
    }
});

/* Property gallery ---------------------------------------------------- */
document.querySelectorAll('[data-gallery]').forEach((gallery) => {
    const main = gallery.querySelector('[data-gallery-main]');
    const counter = gallery.querySelector('[data-gallery-counter]');
    const thumbs = [...gallery.querySelectorAll('[data-gallery-thumb]')];
    let index = 0;

    const show = (i) => {
        index = (i + thumbs.length) % thumbs.length;
        main.src = thumbs[index].dataset.galleryThumb;
        thumbs.forEach((t, n) => t.setAttribute('aria-current', String(n === index)));
        if (counter) counter.textContent = `${index + 1} / ${thumbs.length}`;
    };

    thumbs.forEach((thumb, i) => thumb.addEventListener('click', () => show(i)));
    gallery.querySelector('[data-gallery-prev]')?.addEventListener('click', () => show(index - 1));
    gallery.querySelector('[data-gallery-next]')?.addEventListener('click', () => show(index + 1));
});

/* Area combobox: free text with server suggestions mapped to an area slug.
   Scoped to a city when the root has data-city or a [name=city] field in the
   same form. Typing a city name offers "Anywhere in <city>". ------------- */
document.querySelectorAll('[data-area-combobox]').forEach((root) => {
    const input = root.querySelector('input[type="text"]');
    const hidden = root.querySelector('input[type="hidden"]');
    const list = root.querySelector('[role="listbox"]');
    const cityField = root.closest('form')?.querySelector('[name="city"]');
    let active = -1;
    let timer = null;
    let controller = null;

    const option = (slug, label, hint) =>
        `<li role="option" data-slug="${esc(slug)}" data-label="${esc(label)}" class="cursor-pointer px-4 py-2.5 text-sm hover:bg-brand-50 aria-selected:bg-brand-50"><span class="font-medium">${esc(label)}</span><span class="ml-1 text-muted">${esc(hint)}</span></li>`;

    const render = async () => {
        const term = input.value.trim();
        const city = root.dataset.city || cityField?.value || '';
        controller?.abort();
        controller = new AbortController();
        try {
            const url = `${root.dataset.areaCombobox}?${new URLSearchParams({ q: term, city })}`;
            const response = await fetch(url, { headers: { Accept: 'application/json' }, signal: controller.signal });
            if (!response.ok) return;
            const data = await response.json();
            active = -1;
            list.innerHTML = [
                ...data.cities.map((c) => option('', c, 'Anywhere in the city')),
                ...data.areas.map((a) => option(a.slug, a.name, [a.parent, a.city].filter(Boolean).join(', '))),
            ].join('');
            list.hidden = list.children.length === 0;
            input.setAttribute('aria-expanded', String(!list.hidden));
        } catch (e) {
            if (e.name !== 'AbortError') list.hidden = true;
        }
    };

    const choose = (opt) => {
        input.value = opt.dataset.label;
        hidden.value = opt.dataset.slug;
        list.hidden = true;
        input.setAttribute('aria-expanded', 'false');
    };

    input.addEventListener('focus', render);
    input.addEventListener('input', () => {
        hidden.value = '';
        clearTimeout(timer);
        timer = setTimeout(render, 180);
    });
    input.addEventListener('blur', () => setTimeout(() => (list.hidden = true), 150));
    input.addEventListener('keydown', (e) => {
        const options = [...list.querySelectorAll('[role="option"]')];
        if (!options.length || list.hidden) return;
        if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
            e.preventDefault();
            active = (active + (e.key === 'ArrowDown' ? 1 : -1) + options.length) % options.length;
            options.forEach((o, i) => o.setAttribute('aria-selected', String(i === active)));
        } else if (e.key === 'Enter' && active >= 0) {
            e.preventDefault();
            choose(options[active]);
        } else if (e.key === 'Escape') {
            list.hidden = true;
        }
    });
    list.addEventListener('mousedown', (e) => {
        const opt = e.target.closest('[role="option"]');
        if (opt) choose(opt);
    });
});

/* Image upload previews ----------------------------------------------- */
document.querySelectorAll('[data-image-input]').forEach((input) => {
    const preview = document.getElementById(input.dataset.imageInput);
    input.addEventListener('change', () => {
        preview.innerHTML = '';
        [...input.files].forEach((file) => {
            const img = document.createElement('img');
            img.src = URL.createObjectURL(file);
            img.alt = file.name;
            img.className = 'aspect-[4/3] w-full rounded-lg object-cover ring-1 ring-line';
            preview.append(img);
        });
    });
});

/* Listing photo order: arrows move a photo; its hidden image_order[] input
   moves with it, so the submitted order is the order on screen. ---------- */
document.querySelectorAll('[data-photo-order]').forEach((list) => {
    list.querySelectorAll('[data-photo-controls]').forEach((el) => (el.hidden = false));
    list.addEventListener('click', (e) => {
        const button = e.target.closest('[data-photo-move]');
        if (!button) return;
        const item = button.closest('[data-photo]');
        const sibling = button.dataset.photoMove === '-1' ? item.previousElementSibling : item.nextElementSibling;
        if (!sibling) return;
        if (button.dataset.photoMove === '-1') sibling.before(item);
        else sibling.after(item);
        button.focus();
    });
});

/* Nearby places on the listing page: fetched once the section is close to
   view, so the external lookup never delays the listing itself. ------- */
document.querySelectorAll('[data-nearby]').forEach((section) => {
    const list = section.querySelector('[data-nearby-list]');
    const escape = (value) => String(value ?? '').replace(/[&<>"']/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' })[c]);
    const distance = (m) => (m < 1000 ? `${Math.round(m / 10) * 10} m` : `${(m / 1000).toFixed(1)} km`);
    const fail = () => {
        list.innerHTML = '<p class="rounded-lg bg-page p-4 text-sm text-muted sm:col-span-2 lg:col-span-5">Nearby places are not available right now. Please try again later.</p>';
    };

    const load = async () => {
        try {
            const response = await fetch(section.dataset.nearby, { headers: { Accept: 'application/json' } });
            if (!response.ok) return fail();
            const data = await response.json();
            const found = data.categories.some((c) => c.places.length);
            if (!found) {
                list.innerHTML = '<p class="rounded-lg bg-page p-4 text-sm text-muted sm:col-span-2 lg:col-span-5">No named schools, hospitals, mosques, shops or parks were found nearby in OpenStreetMap.</p>';
                return;
            }
            list.innerHTML = data.categories.map((c) => `
                <div class="rounded-lg border border-line p-3">
                    <p class="text-sm font-semibold text-ink">${escape(c.label)}</p>
                    ${c.places.length
                        ? `<ul class="mt-2 space-y-1.5 text-[13px]">${c.places.map((p) => `<li class="flex justify-between gap-2"><span class="min-w-0 truncate" title="${escape(p.name)}">${escape(p.name)}</span><span class="shrink-0 text-muted tabular-nums">${distance(p.distance)}</span></li>`).join('')}</ul>`
                        : '<p class="mt-2 text-[13px] text-muted">None found nearby</p>'}
                </div>`).join('');
        } catch (error) {
            fail();
        }
    };

    if ('IntersectionObserver' in window) {
        const observer = new IntersectionObserver((entries) => {
            if (entries.some((e) => e.isIntersecting)) {
                observer.disconnect();
                load();
            }
        }, { rootMargin: '400px' });
        observer.observe(section);
    } else {
        load();
    }
});

/* Confirmation prompts and file-name previews. Kept out of inline HTML
   attributes so the Content-Security-Policy can forbid inline script. -- */
document.addEventListener('submit', (e) => {
    const message = e.target.closest?.('form[data-confirm]')?.dataset.confirm;
    if (message && !window.confirm(message)) e.preventDefault();
}, true);

document.addEventListener('click', (e) => {
    if (e.target.closest?.('[data-print]')) window.print();
});

/* CRM board: drag a card to another column. The server checks ownership and
   allowed moves; stages that need a reason open the lead page instead.
   Every card also has a "Move to…" menu, so dragging is never required. */
document.querySelectorAll('[data-crm-board]').forEach((board) => {
    const error = document.querySelector('[data-crm-error]');
    const say = (message) => { if (error) { error.textContent = message; error.classList.toggle('hidden', !message); } };
    let dragged = null;

    board.addEventListener('dragstart', (e) => {
        dragged = e.target.closest('[data-crm-card]');
        if (dragged) { e.dataTransfer.effectAllowed = 'move'; dragged.classList.add('opacity-50'); }
    });
    board.addEventListener('dragend', () => { dragged?.classList.remove('opacity-50'); dragged = null; });
    board.addEventListener('dragover', (e) => { if (dragged && e.target.closest('[data-crm-column]')) e.preventDefault(); });
    board.addEventListener('drop', async (e) => {
        const column = e.target.closest('[data-crm-column]');
        if (!dragged || !column) return;
        e.preventDefault();
        const card = dragged;
        const stage = column.dataset.dropStage;
        if (stage === card.dataset.stage) return;
        if (!card.dataset.allowed.split(',').includes(stage)) {
            say('That move is not allowed from this stage. Open the lead to see the options.');
            return;
        }
        if (column.dataset.needsReason === '1') {
            window.location.href = card.querySelector('a').href + '#stage-title';
            return;
        }
        say('');
        const response = await fetch(card.dataset.stageUrl, {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json', Accept: 'application/json', 'X-CSRF-TOKEN': csrf(), 'X-Requested-With': 'XMLHttpRequest' },
            body: JSON.stringify({ stage }),
        }).catch(() => null);
        if (response?.ok) {
            window.location.reload();
        } else {
            say((await response?.json().catch(() => null))?.message ?? 'Could not move the lead. Please try again.');
        }
    });
});

document.querySelectorAll('input[type=file][data-file-list]').forEach((input) => {
    input.addEventListener('change', () => {
        const target = input.closest('label')?.querySelector('[data-file-names]');
        if (target) target.textContent = [...input.files].map((f) => f.name).join(', ') || 'No file chosen';
    });
});

/* Show/hide bedroom fields by property type --------------------------- */
document.querySelectorAll('[data-type-select]').forEach((select) => {
    const update = () => {
        const hasRooms = select.selectedOptions[0]?.dataset.rooms === '1';
        document.querySelectorAll('[data-rooms-field]').forEach((el) => el.toggleAttribute('hidden', !hasRooms));
    };
    select.addEventListener('change', update);
    update();
});

/* Home loan calculator (standard amortised monthly installment) -------- */
document.querySelectorAll('[data-loan-calc]').forEach((root) => {
    const field = (name) => root.querySelector(`[data-loan="${name}"]`);
    const result = field('result');
    const update = () => {
        const price = Number(field('price').value) || 0;
        const principal = price * (1 - (Number(field('down').value) || 0) / 100);
        const months = (Number(field('years').value) || 0) * 12;
        const rate = (Number(field('rate').value) || 0) / 100 / 12;
        if (principal <= 0 || months <= 0) { result.textContent = '—'; return; }
        const emi = rate === 0 ? principal / months : (principal * rate * (1 + rate) ** months) / ((1 + rate) ** months - 1);
        result.textContent = `PKR ${Math.round(emi).toLocaleString('en-PK')}`;
    };
    root.addEventListener('input', update);
    update();
});

/* Dealer registration: relabel the business name for agencies vs individuals */
document.querySelectorAll('[data-dealer-form]').forEach((form) => {
    const label = form.querySelector('[data-agency-label]');
    const input = form.querySelector('[data-agency-input]');
    form.addEventListener('change', (e) => {
        if (e.target.name !== 'business_type') return;
        const agency = e.target.value === 'agency';
        label.textContent = agency ? 'Agency / Company Name' : 'Business Name';
        input.placeholder = agency ? 'e.g. Ahmed Estate & Builders' : 'e.g. Imran Real Estate';
    });
});

/* Lazy modules -------------------------------------------------------- */
/* Listing form location: city → area/society → phase → sector/block.
   Changing a level clears the levels below it and loads the next one only
   when the chosen place has children. Emits "location:change". -------- */
document.querySelectorAll('[data-location-cascade]').forEach((root) => {
    const city = root.querySelector('[name="city"]');
    const levels = () => [...root.querySelectorAll('[data-location-level]')];
    const optionHtml = (o) =>
        `<option value="${esc(o.id)}" data-name="${esc(o.name)}" data-lat="${esc(o.lat ?? '')}" data-lng="${esc(o.lng ?? '')}" data-children="${esc(o.children)}">${esc(o.name)}</option>`;
    const notify = () => root.dispatchEvent(new CustomEvent('location:change', { bubbles: true }));

    const dropBelow = (index) => {
        root.querySelectorAll('[data-location-level-wrap]').forEach((wrap) => {
            if (Number(wrap.dataset.locationLevelWrap) > index) wrap.remove();
        });
    };

    const addLevel = (index, label, parentName, options) => {
        const wrap = document.createElement('div');
        wrap.dataset.locationLevelWrap = index;
        wrap.innerHTML = `<label for="area-level-${index}" class="label">${esc(label)}</label>
            <select id="area-level-${index}" name="area_path[]" class="input" data-location-level="${index}">
                <option value="">Whole ${esc(parentName)}</option>${options.map(optionHtml).join('')}
            </select>`;
        root.append(wrap);
    };

    const fetchJson = async (url) => {
        const response = await fetch(url, { headers: { Accept: 'application/json' } });
        return response.ok ? response.json() : null;
    };

    root.addEventListener('change', async (e) => {
        if (e.target === city) {
            dropBelow(0);
            const first = levels()[0];
            first.innerHTML = '<option value="">Loading…</option>';
            const data = await fetchJson(`${root.dataset.optionsUrl}?${new URLSearchParams({ city: city.value })}`);
            first.innerHTML = `<option value="">Choose an area</option>${(data ?? []).map(optionHtml).join('')}`;
            notify();
            return;
        }
        const select = e.target.closest('[data-location-level]');
        if (!select) return;
        const index = Number(select.dataset.locationLevel);
        dropBelow(index);
        const chosen = select.selectedOptions[0];
        if (select.value && Number(chosen.dataset.children) > 0) {
            const data = await fetchJson(root.dataset.childrenUrl.replace('__ID__', select.value));
            if (data?.options.length) addLevel(index + 1, data.label, chosen.dataset.name, data.options);
        }
        notify();
    });
});

/* Dealer coverage: show quick picks for ticked cities; search adds any
   other area of those cities as a ticked checkbox. ---------------------- */
document.querySelectorAll('[data-coverage-picker]').forEach((root) => {
    const search = root.querySelector('[data-coverage-search]');
    const results = root.querySelector('[data-coverage-results]');
    const chosen = root.querySelector('[data-coverage-chosen]');
    const cities = () => [...root.querySelectorAll('[data-coverage-city]:checked')].map((c) => c.value);
    let timer = null;

    root.addEventListener('change', (e) => {
        if (!e.target.matches('[data-coverage-city]')) return;
        root.querySelectorAll('[data-city-group]').forEach((g) => (g.hidden = !cities().includes(g.dataset.cityGroup)));
    });

    search.hidden = false;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const term = search.value.trim();
            if (term.length < 2) {
                results.hidden = true;
                return;
            }
            const response = await fetch(`${root.dataset.suggestUrl}?${new URLSearchParams({ q: term })}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            const items = data.areas.filter((a) => cities().includes(a.city));
            results.innerHTML = items.length
                ? items.map((a) => `<li role="option" data-id="${esc(a.id)}" data-name="${esc(a.name)}" data-city="${esc(a.city)}" class="cursor-pointer px-3 py-2 text-sm hover:bg-brand-50">${esc(a.name)} <span class="text-muted">${esc([a.parent, a.city].filter(Boolean).join(', '))}</span></li>`).join('')
                : '<li class="px-3 py-2 text-sm text-muted">No match in the cities you serve.</li>';
            results.hidden = false;
        }, 200);
    });

    results.addEventListener('mousedown', (e) => {
        const item = e.target.closest('[data-id]');
        if (!item) return;
        e.preventDefault();
        const existing = root.querySelector(`input[name="areas[]"][value="${CSS.escape(item.dataset.id)}"]`);
        if (existing) {
            existing.checked = true;
        } else {
            const label = document.createElement('label');
            label.className = 'flex items-center gap-2 rounded px-1.5 py-1 text-[13px] hover:bg-brand-50';
            label.innerHTML = `<input type="checkbox" name="areas[]" value="${esc(item.dataset.id)}" class="checkbox" checked> ${esc(item.dataset.name)} <span class="text-muted">· ${esc(item.dataset.city)}</span>`;
            chosen.append(label);
        }
        search.value = '';
        results.hidden = true;
    });
    search.addEventListener('blur', () => setTimeout(() => (results.hidden = true), 150));
});

/* Several areas in the form's chosen city: quick picks follow the city
   select (other cities' picks are disabled so they are not submitted);
   the search box adds any other area of that city. -------------------- */
document.querySelectorAll('[data-area-picker]').forEach((root) => {
    const city = root.closest('form')?.querySelector('[name="city"]');
    const search = root.querySelector('[data-area-picker-search]');
    const results = root.querySelector('[data-area-picker-results]');
    const chosen = root.querySelector('[data-area-picker-chosen]');
    let timer = null;

    city?.addEventListener('change', () => {
        chosen.innerHTML = '';
        root.querySelectorAll('[data-city-group]').forEach((g) => {
            g.hidden = g.dataset.cityGroup !== city.value;
            g.querySelectorAll('input').forEach((i) => {
                i.disabled = g.hidden;
                if (g.hidden) i.checked = false;
            });
        });
    });

    search.hidden = false;
    search.addEventListener('input', () => {
        clearTimeout(timer);
        timer = setTimeout(async () => {
            const term = search.value.trim();
            if (term.length < 2) {
                results.hidden = true;
                return;
            }
            const response = await fetch(`${root.dataset.suggestUrl}?${new URLSearchParams({ q: term, city: city?.value ?? '' })}`, { headers: { Accept: 'application/json' } });
            if (!response.ok) return;
            const data = await response.json();
            results.innerHTML = data.areas.length
                ? data.areas.map((a) => `<li role="option" data-id="${esc(a.id)}" data-name="${esc(a.name)}" class="cursor-pointer px-3 py-2 text-sm hover:bg-brand-50">${esc(a.name)} <span class="text-muted">${esc([a.parent, a.city].filter(Boolean).join(', '))}</span></li>`).join('')
                : '<li class="px-3 py-2 text-sm text-muted">No match in this city.</li>';
            results.hidden = false;
        }, 200);
    });

    results.addEventListener('mousedown', (e) => {
        const item = e.target.closest('[data-id]');
        if (!item) return;
        e.preventDefault();
        const existing = root.querySelector(`input[value="${CSS.escape(item.dataset.id)}"]:not(:disabled)`);
        if (existing) {
            existing.checked = true;
        } else {
            const label = document.createElement('label');
            label.className = 'flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm hover:bg-brand-50';
            label.innerHTML = `<input type="checkbox" name="${esc(root.dataset.name)}" value="${esc(item.dataset.id)}" class="checkbox" checked> ${esc(item.dataset.name)}`;
            chosen.append(label);
        }
        search.value = '';
        results.hidden = true;
    });
    search.addEventListener('blur', () => setTimeout(() => (results.hidden = true), 150));
});

if (document.querySelector('[data-map]')) import('./map.js');
if (document.querySelector('[data-location-picker]')) import('./location-picker.js');
if (document.querySelector('[data-ai-finder]')) import('./ai-finder.js');
