(() => {
  const root = document.documentElement;
  const canvas = document.querySelector('#depth-field');
  const reduceMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;
  const pointer = {
    targetX: innerWidth * 0.5,
    targetY: innerHeight * 0.5,
    x: innerWidth * 0.5,
    y: innerHeight * 0.5,
  };
  const state = { w: 0, h: 0, ratio: 1, stars: [], frame: 0, paused: reduceMotion };
  const ctx = canvas ? canvas.getContext('2d') : null;

  function setPointerTarget(x, y) {
    pointer.targetX = x;
    pointer.targetY = y;
  }

  function syncCursor() {
    pointer.x += (pointer.targetX - pointer.x) * 0.18;
    pointer.y += (pointer.targetY - pointer.y) * 0.18;
    root.style.setProperty('--cursor-x', pointer.x.toFixed(2) + 'px');
    root.style.setProperty('--cursor-y', pointer.y.toFixed(2) + 'px');
  }

  function resize() {
    if (!canvas || !ctx) return;
    state.ratio = Math.min(devicePixelRatio || 1, 2);
    state.w = innerWidth;
    state.h = innerHeight;
    canvas.width = Math.floor(state.w * state.ratio);
    canvas.height = Math.floor(state.h * state.ratio);
    canvas.style.width = state.w + 'px';
    canvas.style.height = state.h + 'px';
    ctx.setTransform(state.ratio, 0, 0, state.ratio, 0, 0);
    const count = Math.min(240, Math.max(90, Math.floor((state.w * state.h) / 8000)));
    state.stars = Array.from({ length: count }, (_, index) => ({
      x: Math.random() * state.w,
      y: Math.random() * state.h,
      z: Math.random() * 1 + 0.18,
      vx: (Math.random() - 0.5) * 0.18,
      vy: 0.16 + Math.random() * 0.42,
      hue: index % 4,
    }));
  }

  function draw() {
    if (!ctx || state.paused) {
      state.frame = 0;
      return;
    }

    ctx.clearRect(0, 0, state.w, state.h);
    syncCursor();
    const cx = state.w * 0.5 + (pointer.x - state.w * 0.5) * 0.035;
    const cy = state.h * 0.5 + (pointer.y - state.h * 0.5) * 0.035;

    for (const star of state.stars) {
      star.y += star.vy * star.z;
      star.x += star.vx + (pointer.x - state.w * 0.5) * 0.00018 * star.z;
      if (star.y > state.h + 20) star.y = -20;
      if (star.x > state.w + 20) star.x = -20;
      if (star.x < -20) star.x = state.w + 20;

      const glow = Math.max(0, 1 - Math.hypot(star.x - cx, star.y - cy) / 280);
      const palette = [
        '85, 247, 255',
        '215, 255, 79',
        '255, 107, 61',
        '167, 125, 255',
      ][star.hue];

      ctx.beginPath();
      ctx.fillStyle = `rgba(${palette}, ${0.18 + star.z * 0.32 + glow * 0.26})`;
      ctx.arc(star.x, star.y, 0.65 + star.z * 1.6 + glow * 2.4, 0, Math.PI * 2);
      ctx.fill();

      if (glow > 0.2) {
        ctx.beginPath();
        ctx.strokeStyle = `rgba(${palette}, ${glow * 0.12})`;
        ctx.moveTo(star.x, star.y);
        ctx.lineTo(cx, cy);
        ctx.stroke();
      }
    }

    state.frame = requestAnimationFrame(draw);
  }

  function startCanvas() {
    if (!canvas || !ctx || state.paused || state.frame) return;
    resize();
    state.frame = requestAnimationFrame(draw);
  }

  function stopCanvas() {
    if (!state.frame) return;
    cancelAnimationFrame(state.frame);
    state.frame = 0;
  }

  function initReveal() {
    const revealItems = document.querySelectorAll('.reveal');
    if (!revealItems.length) return;

    if (reduceMotion || !('IntersectionObserver' in window)) {
      revealItems.forEach((el) => el.classList.add('is-visible'));
      return;
    }

    const observer = new IntersectionObserver((entries) => {
      for (const entry of entries) {
        if (entry.isIntersecting) entry.target.classList.add('is-visible');
      }
    }, { threshold: 0.16 });

    revealItems.forEach((el, index) => {
      el.style.transitionDelay = Math.min(index * 55, 360) + 'ms';
      observer.observe(el);
    });
  }

  function initHoverGlow() {
    const hoverState = new WeakMap();
    document.querySelectorAll('.action, .project-card, .theme-card, .archive-row').forEach((el) => {
      hoverState.set(el, { frame: 0, x: 0, y: 0 });

      el.addEventListener('pointerenter', () => {
        hoverState.get(el).rect = el.getBoundingClientRect();
      });

      el.addEventListener('pointermove', (event) => {
        const stateForEl = hoverState.get(el);
        stateForEl.x = event.clientX;
        stateForEl.y = event.clientY;
        if (stateForEl.frame) return;

        stateForEl.frame = requestAnimationFrame(() => {
          stateForEl.frame = 0;
          const rect = stateForEl.rect || el.getBoundingClientRect();
          el.style.setProperty('--mx', (stateForEl.x - rect.left).toFixed(1) + 'px');
          el.style.setProperty('--my', (stateForEl.y - rect.top).toFixed(1) + 'px');
        });
      }, { passive: true });
    });
  }

  function initMobileNav() {
    const nav = document.querySelector('.nav');
    const links = document.querySelector('.nav-links');
    if (!nav || !links) return;

    const toggle = document.createElement('button');
    toggle.className = 'nav-toggle';
    toggle.type = 'button';
    toggle.setAttribute('aria-controls', 'site-nav-links');
    toggle.setAttribute('aria-expanded', 'false');
    toggle.setAttribute('aria-label', '打开导航');
    toggle.innerHTML = '<span></span><span></span><span></span>';
    links.id ||= 'site-nav-links';
    nav.appendChild(toggle);

    toggle.addEventListener('click', () => {
      const isOpen = nav.classList.toggle('is-open');
      toggle.setAttribute('aria-expanded', String(isOpen));
      toggle.setAttribute('aria-label', isOpen ? '关闭导航' : '打开导航');
    });

    links.addEventListener('click', (event) => {
      if (!(event.target instanceof HTMLAnchorElement)) return;
      nav.classList.remove('is-open');
      toggle.setAttribute('aria-expanded', 'false');
      toggle.setAttribute('aria-label', '打开导航');
    });
  }

  initReveal();
  initHoverGlow();
  initMobileNav();
  syncCursor();
  startCanvas();

  addEventListener('pointermove', (event) => setPointerTarget(event.clientX, event.clientY), { passive: true });
  addEventListener('resize', resize);
  document.addEventListener('visibilitychange', () => {
    state.paused = reduceMotion || document.hidden;
    if (state.paused) stopCanvas();
    else startCanvas();
  });
})();
