import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import test from 'node:test';
import vm from 'node:vm';

const script = readFileSync(new URL('../../public/assets/js/script.js', import.meta.url), 'utf8');

function contactPage(fetch, valid = true) {
  const handlers = new Map();
  const attributes = new Map();
  const button = { disabled: false };
  const fields = new Map(['name', 'email', 'phone', 'message'].map(name => [name, {
    invalid: false, focused: false,
    setAttribute() { this.invalid = true; },
    removeAttribute() { this.invalid = false; },
    focus() { this.focused = true; }
  }]));
  const form = {
    action: 'https://portfolio.company/inquiries', resets: 0,
    reportValidity: () => valid,
    addEventListener: (name, handler) => handlers.set(name, handler),
    querySelector: () => button,
    querySelectorAll: () => [...fields.values()].filter(field => field.invalid),
    setAttribute: (name, value) => attributes.set(name, value),
    removeAttribute: name => attributes.delete(name),
    elements: { namedItem: name => fields.get(name) },
    reset() { this.resets++; }
  };
  const status = { textContent: '', focused: false, focus() { this.focused = true; } };
  const document = {
    body: { dataset: { page: 'contact' } },
    querySelector: selector => ({ '#contactForm': form, '#formStatus': status })[selector] ?? null
  };
  vm.runInNewContext(script, {
    document, window: { matchMedia: () => ({ matches: true }) }, fetch,
    FormData: class { constructor(element) { this.element = element; } }
  });
  return { form, status, button, attributes, fields, submit: () => handlers.get('submit')({ preventDefault() {} }) };
}

const response = (status, data) => ({ status, json: async () => data });

test('a confirmed inquiry clears the form and announces the server receipt', async () => {
  let request;
  const page = contactPage(async (url, options) => {
    request = { url, options };
    return response(201, { message: 'Thanks! Your inquiry has been received.' });
  });

  await page.submit();

  assert.equal(request.url, 'https://portfolio.company/inquiries');
  assert.equal(request.options.method, 'POST');
  assert.equal(request.options.credentials, 'same-origin');
  assert.equal(request.options.body.element, page.form);
  assert.equal(page.form.resets, 1);
  assert.equal(page.status.textContent, 'Thanks! Your inquiry has been received.');
  assert.equal(page.status.focused, true);
  assert.equal(page.button.disabled, false);
  assert.equal(page.attributes.has('aria-busy'), false);
});

for (const [label, result] of [
  ['an HTML error page with status 200', { status: 200, json: async () => { throw new SyntaxError('Not JSON'); } }],
  ['a redirect ending in status 200', response(200, { message: 'A different page' })],
  ['a malformed success response', response(201, {})]
]) {
  test(`${label} preserves the inquiry and does not claim delivery`, async () => {
    const page = contactPage(async () => result);

    await page.submit();

    assert.equal(page.form.resets, 0);
    assert.match(page.status.textContent, /could not be confirmed/);
    assert.equal(page.button.disabled, false);
  });
}

for (const [code, expected] of [[419, /expired/], [429, /wait a minute/], [500, /could not be confirmed/]]) {
  test(`HTTP ${code} explains the failure and keeps the client's input`, async () => {
    const page = contactPage(async () => response(code, {}));

    await page.submit();

    assert.equal(page.form.resets, 0);
    assert.match(page.status.textContent, expected);
    assert.equal(page.button.disabled, false);
  });
}

test('validation errors identify and focus the invalid field', async () => {
  const page = contactPage(async () => response(422, { errors: { email: ['Enter a valid email address.'] } }));

  await page.submit();

  assert.equal(page.form.resets, 0);
  assert.equal(page.status.textContent, 'Enter a valid email address.');
  assert.equal(page.fields.get('email').invalid, true);
  assert.equal(page.fields.get('email').focused, true);
  assert.equal(page.button.disabled, false);
});

test('a network failure preserves input and allows retrying', async () => {
  const page = contactPage(async () => { throw new TypeError('Connection failed'); });

  await page.submit();

  assert.equal(page.form.resets, 0);
  assert.match(page.status.textContent, /Check your connection/);
  assert.equal(page.button.disabled, false);
});

test('repeated clicks do not submit a second lead while the first request is pending', async () => {
  let release;
  let requests = 0;
  const page = contactPage(() => {
    requests++;
    return new Promise(resolve => { release = resolve; });
  });

  const first = page.submit();
  await page.submit();
  assert.equal(page.button.disabled, true);
  assert.equal(page.attributes.get('aria-busy'), 'true');
  release(response(201, { message: 'Received.' }));
  await first;

  assert.equal(requests, 1);
  assert.equal(page.form.resets, 1);
  assert.equal(page.button.disabled, false);
});

test('native form validation prevents an invalid inquiry from being sent', async () => {
  let requests = 0;
  const page = contactPage(async () => { requests++; }, false);

  await page.submit();

  assert.equal(requests, 0);
  assert.equal(page.form.resets, 0);
});

test('resume tabs select one panel and support keyboard navigation', () => {
  const panels = Array.from({ length: 3 }, () => ({ hidden: false }));
  const classes = new Set();
  const tabs = panels.map((panel, index) => ({
    attributes: new Map([['aria-controls', `panel-${index}`]]), handlers: new Map(), focused: false,
    getAttribute(name) { return this.attributes.get(name); },
    setAttribute(name, value) { this.attributes.set(name, value); },
    addEventListener(name, handler) { this.handlers.set(name, handler); },
    focus() { this.focused = true; }
  }));
  vm.runInNewContext(script, {
    document: {
      body: { dataset: { page: 'about' }, classList: { add: name => classes.add(name) } },
      querySelector: () => null,
      querySelectorAll: () => tabs,
      getElementById: id => panels[Number(id.split('-')[1])]
    }, window: { matchMedia: () => ({ matches: true }) }
  });

  assert.equal(classes.has('resume-ready'), true);
  assert.deepEqual(panels.map(panel => panel.hidden), [false, true, true]);
  tabs[0].handlers.get('keydown')({ key: 'ArrowRight', preventDefault() {} });
  assert.deepEqual(panels.map(panel => panel.hidden), [true, false, true]);
  assert.equal(tabs[1].focused, true);
  assert.equal(tabs[1].attributes.get('aria-selected'), 'true');
  tabs[1].handlers.get('keydown')({ key: 'End', preventDefault() {} });
  assert.deepEqual(panels.map(panel => panel.hidden), [true, true, false]);
  tabs[0].handlers.get('click')();
  assert.deepEqual(panels.map(panel => panel.hidden), [false, true, true]);
});

test('unsupported dialogs leave the Hire link available for normal navigation', () => {
  let intercepted = false;
  vm.runInNewContext(script, {
    document: {
      body: { dataset: { page: 'home' } },
      querySelector: selector => selector === '#hireModal' ? {} : null,
      addEventListener() { intercepted = true; }
    }, window: { matchMedia: () => ({ matches: true }) }
  });

  assert.equal(intercepted, false);
});
