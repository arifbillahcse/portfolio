(() => {
  'use strict';
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  const whatsapp = message => 'https://wa.me/' + (document.body.dataset.whatsapp || '8801779440297') + '?text=' + encodeURIComponent(message);
  const text = element => element?.textContent.trim() || '';
  const setList = (element, values) => element.replaceChildren(...values.map(value => {
    const item = document.createElement('li');
    item.textContent = value;
    return item;
  }));

  function initHeader() {
    const header = $('.site-header');
    if (!header) return;
    const menu = $('#menu'), nav = $('#nav'), theme = $('#theme');
    function setMenu(open) {
      nav.classList.toggle('open', open);
      menu.setAttribute('aria-expanded', String(open));
      menu.setAttribute('aria-label', open ? 'Close navigation menu' : 'Open navigation menu');
      menu.textContent = open ? '✕' : '☰';
    }
    menu.addEventListener('click', () => setMenu(!nav.classList.contains('open')));
    nav.addEventListener('click', event => { if (event.target.closest('a')) setMenu(false); });
    document.addEventListener('keydown', event => {
      if (event.key === 'Escape' && nav.classList.contains('open')) {
        setMenu(false);
        menu.focus();
      }
    });
    document.addEventListener('click', event => { if (!header.contains(event.target)) setMenu(false); });
    window.matchMedia('(max-width:900px)').addEventListener('change', () => setMenu(false));
    function applyTheme(dark) {
      document.body.classList.toggle('dark', dark);
      theme.setAttribute('aria-pressed', String(dark));
      theme.setAttribute('aria-label', dark ? 'Switch to light theme' : 'Switch to dark theme');
    }
    try { applyTheme(localStorage.getItem('arif-theme') === 'dark'); }
    catch { applyTheme(false); }
    theme.addEventListener('click', () => {
      const dark = !document.body.classList.contains('dark');
      applyTheme(dark);
      try { localStorage.setItem('arif-theme', dark ? 'dark' : 'light'); } catch {}
    });
    const year = $('#year');
    if (year) year.textContent = new Date().getFullYear();
  }

  function bindDialog(dialog, closeSelector, bodyClass) {
    $(closeSelector).addEventListener('click', () => dialog.close());
    dialog.addEventListener('click', event => {
      if (event.target !== dialog) return;
      const rect = dialog.getBoundingClientRect();
      if (event.clientX < rect.left || event.clientX > rect.right ||
          event.clientY < rect.top || event.clientY > rect.bottom) dialog.close();
    });
    dialog.addEventListener('close', () => document.body.classList.remove(bodyClass));
    return () => {
      dialog.showModal();
      document.body.classList.add(bodyClass);
    };
  }

  function bindServerInquiry(form, status) {
    const button = $('button[type="submit"]', form);
    form.addEventListener('submit', async event => {
      event.preventDefault();
      if (button.disabled || !form.reportValidity()) return;
      button.disabled = true;
      form.setAttribute('aria-busy', 'true');
      status.textContent = 'Sending your inquiry…';
      $('[aria-invalid]', form).forEach(field => field.removeAttribute('aria-invalid'));
      try {
        const response = await fetch(form.action, {
          method: 'POST', body: new FormData(form), credentials: 'same-origin',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json().catch(() => ({}));
        if (response.ok) {
          form.reset();
          status.textContent = data.message || 'Your inquiry has been received.';
        } else if (response.status === 422 && data.errors) {
          status.textContent = Object.values(data.errors).flat().join(' ');
          const names = Object.keys(data.errors);
          names.forEach(name => form.elements.namedItem(name)?.setAttribute('aria-invalid', 'true'));
          form.elements.namedItem(names[0])?.focus();
          return;
        } else if (response.status === 429) {
          status.textContent = 'Please wait a minute before sending another inquiry.';
        } else if (response.status === 419) {
          status.textContent = 'This page has expired. Please reload it before sending your inquiry.';
        } else {
          status.textContent = 'Your inquiry could not be confirmed. Please try again or contact me on WhatsApp.';
        }
        status.focus();
      } catch {
        status.textContent = 'Your inquiry could not be confirmed. Check your connection, or contact me on WhatsApp.';
        status.focus();
      } finally {
        button.disabled = false;
        form.removeAttribute('aria-busy');
      }
    });
  }

  function bindInquiry(form, status, onComplete) {
    if (form.hasAttribute('data-server-inquiry')) {
      bindServerInquiry(form, status || $('.form-status', form));
      return;
    }
    form.addEventListener('submit', event => {
      event.preventDefault();
      if (!form.reportValidity()) return;
      const data = new FormData(form);
      const [name, email, phone, message] = ['name', 'email', 'phone', 'message']
        .map(key => String(data.get(key) || '').trim());
      if (!name || !message) {
        if (status) status.textContent = 'Please enter your name and project details.';
        $(!name ? '#clientName' : '#clientMessage', form).focus();
        return;
      }
      const draft = ['Hello Arif, I would like to discuss a project.', '',
        'Name: ' + name, 'Email: ' + email, 'Phone: ' + (phone || 'Not provided'),
        '', 'Project details:', message].join('\n');
      if (status) status.textContent = 'Opening your WhatsApp draft. Review it and press Send in WhatsApp.';
      onComplete(whatsapp(draft));
    });
  }

  function initHome() {
    const dialog = $('#hireModal');
    const open = bindDialog(dialog, '#closeHire', 'modal-open');
    $('#openHire').addEventListener('click', open);
    bindInquiry($('#hireForm'), null, url => {
      window.open(url, '_blank', 'noopener');
      dialog.close();
    });
    const elements = $$('.reveal');
    if (!('IntersectionObserver' in window)) {
      elements.forEach(element => element.classList.add('visible'));
      return;
    }
    const observer = new IntersectionObserver(entries => entries.forEach(entry => {
      if (entry.isIntersecting) {
        entry.target.classList.add('visible');
        observer.unobserve(entry.target);
      }
    }), { threshold: 0.12 });
    elements.forEach(element => observer.observe(element));
  }

  function initResume() {
    const tabs = $$('.resume-tabs [role="tab"]');
    const panels = tabs.map(tab => document.getElementById(tab.getAttribute('aria-controls')));
    function select(index) {
      tabs.forEach((tab, i) => {
        tab.setAttribute('aria-selected', String(i === index));
        tab.tabIndex = i === index ? 0 : -1;
        panels[i].hidden = i !== index;
      });
    }
    tabs.forEach((tab, index) => {
      tab.addEventListener('click', () => select(index));
      tab.addEventListener('keydown', event => {
        const next = { ArrowRight: (index + 1) % tabs.length,
          ArrowLeft: (index + tabs.length - 1) % tabs.length, Home: 0, End: tabs.length - 1 }[event.key];
        if (next !== undefined) {
          event.preventDefault();
          select(next);
          tabs[next].focus();
        }
      });
    });
  }

  // One filtering and pagination implementation for projects and articles.
  function initCollection({ cardSelector, loadSelector, statusSelector, countSelector, searchSelector,
    emptySelector, resetSelector, itemName, openSelector }) {
    const cards = $$(cardSelector);
    const entries = cards.map(card => ({ card, category: card.dataset.category,
      search: (card.dataset.search || text(card)).toLowerCase() }));
    const buttons = $$('[data-filter]');
    const load = $(loadSelector), status = $(statusSelector);
    const count = countSelector ? $(countSelector) : null;
    const search = searchSelector ? $(searchSelector) : null;
    const empty = emptySelector ? $(emptySelector) : null;
    let category = 'all', limit = 6, matching = entries;
    function render() {
      const visible = new Set(matching.slice(0, limit).map(entry => entry.card));
      cards.forEach(card => { if (card.hidden === visible.has(card)) card.hidden = !visible.has(card); });
      load.hidden = visible.size >= matching.length;
      status.textContent = 'Showing ' + visible.size + ' of ' + matching.length + ' ' + itemName + 's';
      if (count) count.textContent = matching.length + ' ' + itemName + (matching.length === 1 ? '' : 's');
      if (empty) empty.hidden = matching.length > 0;
    }
    function refresh() {
      const query = search?.value.trim().toLowerCase() || '';
      matching = entries.filter(entry => (category === 'all' || entry.category === category) && entry.search.includes(query));
      limit = 6;
      buttons.forEach(button => {
        const selected = button.dataset.filter === category;
        button.setAttribute('aria-pressed', String(selected));
        button.classList.toggle('selected', selected);
      });
      render();
    }
    buttons.forEach(button => button.addEventListener('click', () => { category = button.dataset.filter; refresh(); }));
    search?.addEventListener('input', refresh);
    if (resetSelector) $(resetSelector).addEventListener('click', () => {
      category = 'all';
      search.value = '';
      refresh();
      search.focus();
    });
    load.addEventListener('click', () => {
      const next = matching[limit]?.card;
      limit += 6;
      render();
      if (next) $(openSelector, next)?.focus({ preventScroll: true });
    });
    const total = $('[data-filter="all"] span');
    if (total) total.textContent = String(cards.length).padStart(2, '0');
    refresh();
    return cards;
  }

  // Only details absent from the rendered cards are stored here.
  const projectNotes = () => [
    ['A storefront concept for a product-based business.', ['Product categories and search', 'Responsive product pages', 'Cart and checkout flow']],
    ['An application concept for teams that need a clearer view of their operations.', ['Role-based workspaces', 'Business records and reporting', 'Third-party integrations']],
    ['An infrastructure concept for hosting and administering a web application.', ['Linux and web server configuration', 'SSL and access controls', 'Backups and monitoring']],
    ['A website concept for a service business that manages its own content.', ['Flexible content layouts', 'Service and contact pages', 'Mobile-friendly navigation']],
    ['An integration concept connecting a website to an AI-assisted support workflow.', ['Conversational help interface', 'Knowledge-based responses', 'Human support handoff']],
    ['An operations concept for moving a website and maintaining its hosting environment.', ['Migration planning', 'DNS and SSL configuration', 'Security and maintenance']]
  ];
  const articleOutlines = () => [
    ['Understanding the business goal', 'Connecting the application and its hosting environment', 'Planning for maintenance after launch'],
    ['Mapping everyday business processes', 'Defining application features', 'Planning integrations'],
    ['Organizing your product catalog', 'Considering the customer journey', 'Identifying payment and delivery needs'],
    ['Understanding the server environment', 'Planning access and administration', 'Organizing ongoing maintenance'],
    ['Reviewing application requirements', 'Considering hosting options', 'Planning operational responsibilities'],
    ['Defining the information to exchange', 'Planning integration behavior', 'Considering errors and support'],
    ['Looking at the visitor experience', 'Identifying areas for investigation', 'Planning improvements and measurement'],
    ['Defining maintenance responsibilities', 'Organizing recurring tasks', 'Keeping a record of changes']
  ];

  function initGallery(isBlog) {
    if ($('[data-server-collection]') || $('.content-detail')) return;
    const notes = isBlog ? articleOutlines() : projectNotes();
    const cards = initCollection(isBlog ? {
      cardSelector: '.blog-card', loadSelector: '#loadArticles', statusSelector: '#articleStatus',
      searchSelector: '#articleSearch', emptySelector: '#emptyState', resetSelector: '#resetSearch',
      itemName: 'article', openSelector: '[data-article]'
    } : {
      cardSelector: '.work-card', loadSelector: '#loadMoreProjects', statusSelector: '#paginationSummary',
      countSelector: '#resultCount', itemName: 'project', openSelector: '[data-open]'
    });
    const dialog = $(isBlog ? '#articleDialog' : '#projectDialog');
    const open = bindDialog(dialog, isBlog ? '#closeArticle' : '#closeDialog', isBlog ? 'article-open' : 'modal-open');
    const title = $(isBlog ? '#articleTitle' : '#dialogTitle');
    const type = $(isBlog ? '#articleCategory' : '#dialogType');
    const summary = $(isBlog ? '#articleSummary' : '#dialogOverview');
    const list = $(isBlog ? '#articleOutline' : '#dialogFeatures');
    const stack = !isBlog && $('#dialogStack');
    const inquiry = !isBlog && $('#dialogInquiry');
    const selector = isBlog ? '[data-article]' : '[data-open]';
    document.addEventListener('click', event => {
      const trigger = event.target.closest(selector);
      if (!trigger) return;
      const index = Number(isBlog ? trigger.dataset.article : trigger.dataset.open);
      const card = cards[index];
      if (!card) return;
      title.textContent = text($('h2', card));
      type.textContent = text($(isBlog ? '.article-meta span' : '.work-meta span', card));
      summary.textContent = isBlog ? text($('.article-copy > p', card)) : notes[index]?.[0] || text($('.work-copy > p', card));
      setList(list, isBlog ? notes[index] || [] : notes[index]?.[1] || []);
      if (!isBlog) {
        setList(stack, $$('.tech-tags li', card).map(text));
        inquiry.href = whatsapp("Hi Arif, I'd like to discuss a project similar to " + title.textContent + '.');
      }
      open();
    });
  }

  initHeader();
  const page = document.body.dataset.page;
  if (page === 'home') initHome();
  else if (page === 'about') initResume();
  else if (page === 'projects' || page === 'blog') initGallery(page === 'blog');
  else if (page === 'contact') bindInquiry($('#contactForm'), $('#formStatus'), url => window.location.assign(url));
})();
