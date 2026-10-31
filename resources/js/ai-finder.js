/**
 * AI Property Finder conversation. Messages go to the server, which keeps
 * the conversation (and the requirement built so far) and returns real
 * matches rendered as HTML. Quick replies, follow-up questions and the
 * save / contact-dealers / new-search actions come from the same response.
 */
const root = document.querySelector('[data-ai-finder]');
const thread = root.querySelector('[data-thread]');
const form = root.querySelector('[data-composer]');
const textarea = form.querySelector('textarea');
const results = document.querySelector('[data-results]');
const understood = document.querySelector('[data-understood]');
const tools = root.querySelector('[data-assistant-tools]');
const choicesEl = tools.querySelector('[data-choices]');
const consentEl = tools.querySelector('[data-consent]');
const csrf = document.querySelector('meta[name="csrf-token"]')?.content;
const signedIn = tools.dataset.signedIn === '1';

const bubble = (text, who) => {
    const wrap = document.createElement('div');
    wrap.className = who === 'user' ? 'flex justify-end' : 'flex gap-3';
    const inner = document.createElement('div');
    inner.className = who === 'user'
        ? 'max-w-[85%] rounded-2xl rounded-br-md bg-brand-600 px-4 py-3 text-sm text-white whitespace-pre-line'
        : 'max-w-[85%] rounded-2xl rounded-bl-md border border-line bg-white px-4 py-3 text-sm text-ink whitespace-pre-line';
    inner.dir = 'auto';
    inner.textContent = text;
    if (who !== 'user') {
        const avatar = root.querySelector('[data-assistant-avatar]').cloneNode(true);
        avatar.hidden = false;
        wrap.append(avatar);
    }
    wrap.append(inner);
    thread.append(wrap);
    thread.scrollTop = thread.scrollHeight;
    return inner;
};

const typing = () => {
    const el = bubble('', 'assistant');
    el.setAttribute('aria-label', 'Assistant is typing');
    el.innerHTML = '<span class="inline-flex gap-1"><span class="size-1.5 animate-bounce rounded-full bg-brand-400"></span><span class="size-1.5 animate-bounce rounded-full bg-brand-400 [animation-delay:150ms]"></span><span class="size-1.5 animate-bounce rounded-full bg-brand-400 [animation-delay:300ms]"></span></span>';
    return el;
};

const setActions = (actions = []) => {
    tools.querySelectorAll('[data-ai-action]').forEach((button) => {
        const action = button.dataset.aiAction;
        button.hidden = action === 'reset' ? false : !(actions.includes(action === 'contact' ? 'contact_dealers' : action) || actions.includes('login'));
    });
    results.querySelectorAll('[data-ai-action]').forEach((button) => (button.hidden = false));
};

const setChoices = (choices = []) => {
    choicesEl.innerHTML = '';
    choices.forEach((choice) => {
        const chip = document.createElement('button');
        chip.type = 'button';
        chip.className = 'rounded-full border border-brand-200 bg-brand-50 px-3 py-1 text-[13px] font-medium text-brand-700 hover:border-brand-400';
        chip.textContent = choice.label;
        chip.addEventListener('click', () => send(choice.value));
        choicesEl.append(chip);
    });
};

const post = async (url, body) => {
    const response = await fetch(url, {
        method: 'POST',
        headers: { Accept: 'application/json', 'X-CSRF-TOKEN': csrf },
        body,
    });
    if (response.status === 401 || response.status === 403) {
        window.location.href = `${tools.dataset.loginUrl}`;
        throw new Error('Please sign in to continue.');
    }
    if (response.status === 429) throw new Error('You are sending messages too quickly. Please wait a moment.');
    if (response.status === 422) {
        const data = await response.json();
        throw new Error(Object.values(data.errors ?? {})[0]?.[0] ?? 'Please check and try again.');
    }
    if (!response.ok) throw new Error('Something went wrong. Please try again.');
    return response.json();
};

const show = (data, pending) => {
    pending.textContent = data.reply;
    if (data.follow_up) bubble(data.follow_up, 'assistant');
    if (data.html) results.innerHTML = data.html;
    if (data.understood !== undefined) understood.innerHTML = data.understood;
    setChoices(data.choices);
    setActions(data.actions);
    // Below the two-column layout the cards are under the chat: offer a jump instead of moving the page.
    if (data.result_count && window.innerWidth < 1280) {
        const jump = document.createElement('button');
        jump.type = 'button';
        jump.className = 'ml-11 inline-flex items-center gap-1.5 rounded-full bg-gold-500 px-3.5 py-1.5 text-[13px] font-semibold text-ink shadow-card';
        jump.textContent = `View ${data.result_count} ${data.result_count === 1 ? 'match' : 'matches'} ↓`;
        jump.addEventListener('click', () => results.closest('section').scrollIntoView({ behavior: 'smooth', block: 'start' }));
        thread.append(jump);
        thread.scrollTop = thread.scrollHeight;
    }
};

const fail = (pending, error) => {
    pending.textContent = error.message;
    pending.classList.add('border-red-200', 'bg-red-50', 'text-red-700');
};

const send = async (message) => {
    if (!message.trim()) return;
    bubble(message, 'user');
    textarea.value = '';
    setChoices([]);
    const pending = typing();
    form.querySelector('button[type="submit"]').disabled = true;

    try {
        const body = new FormData(form);
        body.set('message', message);
        show(await post(form.dataset.postUrl, body), pending);
    } catch (error) {
        fail(pending, error);
    } finally {
        form.querySelector('button[type="submit"]').disabled = false;
        textarea.focus();
    }
};

const runAction = async (action) => {
    if (action === 'reset') {
        await post(tools.dataset.resetUrl, new FormData()).catch(() => null);
        window.location.reload();
        return;
    }
    if (!signedIn) {
        bubble('Please sign in or create a free buyer account first — your search is kept.', 'assistant');
        window.setTimeout(() => (window.location.href = tools.dataset.loginUrl), 1200);
        return;
    }
    if (action === 'contact') {
        consentEl.hidden = false;
        consentEl.querySelector('[data-consent-box]').focus();
        return;
    }
    const pending = typing();
    try {
        show(await post(tools.dataset.saveUrl, new FormData()), pending);
    } catch (error) {
        fail(pending, error);
    }
};

root.addEventListener('click', (event) => {
    const button = event.target.closest('[data-ai-action]');
    if (button) runAction(button.dataset.aiAction);
});

consentEl.querySelector('[data-consent-cancel]').addEventListener('click', () => (consentEl.hidden = true));
consentEl.querySelector('[data-consent-confirm]').addEventListener('click', async () => {
    const box = consentEl.querySelector('[data-consent-box]');
    if (!box.checked) {
        box.focus();
        return;
    }
    consentEl.hidden = true;
    bubble('Yes, share my requirement with matching dealers.', 'user');
    const pending = typing();
    const body = new FormData();
    body.set('consent', '1');
    try {
        show(await post(tools.dataset.contactUrl, body), pending);
    } catch (error) {
        fail(pending, error);
    }
});

form.addEventListener('submit', (event) => {
    event.preventDefault();
    send(textarea.value);
});

textarea.addEventListener('keydown', (event) => {
    if (event.key === 'Enter' && !event.shiftKey) {
        event.preventDefault();
        send(textarea.value);
    }
});

root.querySelectorAll('[data-prompt]').forEach((chip) => {
    chip.addEventListener('click', () => send(chip.dataset.prompt));
});

// The large hero input feeds the same conversation.
root.querySelectorAll('[data-prompt-form]').forEach((heroForm) => {
    heroForm.addEventListener('submit', (event) => {
        event.preventDefault();
        const input = heroForm.querySelector('input[name="message"]');
        send(input.value);
        input.value = '';
        thread.closest('.card')?.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
    });
});

thread.scrollTop = thread.scrollHeight;
