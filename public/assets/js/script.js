(() => {
  'use strict';
  const $ = (selector, root = document) => root.querySelector(selector);
  const $$ = (selector, root = document) => [...root.querySelectorAll(selector)];
  function initHeader() {
    const header = $('.site-header');
    if (!header) return;
    const menu = $('#menu'), nav = $('#nav'), theme = $('#theme');
    header.classList.add('js-enabled');
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
      $$('[aria-invalid]', form).forEach(field => field.removeAttribute('aria-invalid'));
      try {
        const response = await fetch(form.action, {
          method: 'POST', body: new FormData(form), credentials: 'same-origin',
          headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
        });
        const data = await response.json().catch(() => ({}));
        if (response.status === 201 && typeof data.message === 'string' && data.message.trim()) {
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

  function initHome() {
    const dialog = $('#hireModal');
    if (!dialog || typeof dialog.showModal !== 'function') return;
    const open = bindDialog(dialog, '#closeHire', 'modal-open');
    $('#openHire').addEventListener('click', event => {
      if (event.ctrlKey || event.metaKey || event.shiftKey || event.altKey) return;
      event.preventDefault();
      open();
    });
    bindServerInquiry($('#hireForm'), $('.form-status', dialog));
  }

  function initMotion() {
    const preference = window.matchMedia('(prefers-reduced-motion: reduce)');
    if (preference.matches || !('IntersectionObserver' in window) ||
        typeof Element === 'undefined' || !Element.prototype.animate) return;

    const selector = [
      '.reveal', '.intro-copy', '.portrait-panel', '.experience-strip',
      '.section-heading', '.journey > article', '.expertise', '.businesses',
      '.work-note', '.resume-heading', '.resume-tabs', '.resume-entry', '.vision',
      '.service-intro', '.category-nav', '.group-heading', '.service-card',
      '.projects-intro', '.gallery-toolbar', '.work-card', '.blog-intro',
      '.featured', '.browse-heading', '.blog-filters', '.blog-card',
      '.contact-intro', '.contact-details', '.form-panel', '.next-heading',
      '.next-grid > li', '.detail-heading', '.detail-cover', '.detail-gallery > img',
      '.about-contact > .wrap', '.service-cta > .wrap', '.project-cta > .wrap',
      '.journal-cta > .wrap'
    ].join(',');
    const active = new Map();
    const observer = new IntersectionObserver(entries => {
      entries.forEach(entry => {
        if (!entry.isIntersecting) return;
        const element = entry.target;
        observer.unobserve(element);
        if (preference.matches || element.contains(document.activeElement)) return;
        const siblings = [...element.parentElement.children];
        const delay = Math.max(0, siblings.indexOf(element) % 3) * 60;
        const animation = element.animate([
          { opacity: 0, transform: 'translateY(18px)' },
          { opacity: 1, transform: 'translateY(0)' }
        ], { duration: 520, delay, easing: 'cubic-bezier(.2,.7,.2,1)', fill: 'backwards' });
        active.set(element, animation);
        animation.finished.then(() => active.delete(element), () => active.delete(element));
      });
    }, { threshold: 0, rootMargin: '0px 0px -24px 0px' });

    $$(selector).filter(element => {
      if (element.closest('.home-hero, dialog') || element.parentElement?.closest(selector)) return false;
      const rect = element.getBoundingClientRect();
      return !(rect.top < window.innerHeight && rect.bottom > 0);
    }).slice(0, 180).forEach(element => observer.observe(element));

    preference.addEventListener('change', () => {
      if (!preference.matches) return;
      observer.disconnect();
      active.forEach(animation => animation.cancel());
      active.clear();
    });
    document.addEventListener('focusin', event => {
      active.forEach((animation, element) => {
        if (element.contains(event.target)) animation.cancel();
      });
    });
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
    if (!tabs.length || panels.some(panel => !panel)) return;
    select(0);
    document.body.classList.add('resume-ready');
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

  initHeader();
  const page = document.body.dataset.page;
  if (page === 'home') initHome();
  else if (page === 'about') initResume();
  else if (page === 'contact') bindServerInquiry($('#contactForm'), $('#formStatus'));
  initMotion();
})();
