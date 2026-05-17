const canvas = document.querySelector("#field");
const ctx = canvas.getContext("2d");
const glow = document.querySelector(".cursor-glow");
const progress = document.querySelector(".scroll-progress span");
const menuButton = document.querySelector(".menu-button");
const mobileMenu = document.querySelector("#mobile-menu");
const reduceMotion = window.matchMedia("(prefers-reduced-motion: reduce)");

const state = {
  width: 0,
  height: 0,
  particles: [],
  pointer: {
    x: window.innerWidth * 0.5,
    y: window.innerHeight * 0.5,
    active: false,
  },
  animationId: null,
};

function randomParticle(index) {
  return {
    x: Math.random() * state.width,
    y: Math.random() * state.height,
    r: 0.7 + Math.random() * 2.1,
    vx: (Math.random() - 0.5) * 0.42,
    vy: (Math.random() - 0.5) * 0.42,
    hue: index % 4,
    phase: Math.random() * Math.PI * 2,
  };
}

function resize() {
  const ratio = Math.min(window.devicePixelRatio || 1, 2);
  state.width = window.innerWidth;
  state.height = window.innerHeight;
  canvas.width = Math.floor(state.width * ratio);
  canvas.height = Math.floor(state.height * ratio);
  canvas.style.width = `${state.width}px`;
  canvas.style.height = `${state.height}px`;
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

  const count = reduceMotion.matches
    ? 0
    : Math.min(140, Math.max(64, Math.floor((state.width * state.height) / 15000)));
  state.particles = Array.from({ length: count }, (_, index) => randomParticle(index));
}

function colorFor(particle) {
  if (particle.hue === 0) return "82, 245, 255";
  if (particle.hue === 1) return "201, 255, 53";
  if (particle.hue === 2) return "19, 56, 255";
  return "255, 79, 216";
}

function draw() {
  ctx.clearRect(0, 0, state.width, state.height);

  const gradient = ctx.createRadialGradient(
    state.pointer.x,
    state.pointer.y,
    0,
    state.pointer.x,
    state.pointer.y,
    Math.max(state.width, state.height) * 0.45,
  );
  gradient.addColorStop(0, "rgba(82, 245, 255, 0.12)");
  gradient.addColorStop(0.38, "rgba(19, 56, 255, 0.05)");
  gradient.addColorStop(1, "rgba(0, 0, 0, 0)");
  ctx.fillStyle = gradient;
  ctx.fillRect(0, 0, state.width, state.height);

  for (const particle of state.particles) {
    const dx = state.pointer.x - particle.x;
    const dy = state.pointer.y - particle.y;
    const distance = Math.hypot(dx, dy);
    const pull = state.pointer.active ? Math.max(0, 1 - distance / 260) : 0;
    const drift = Math.sin(performance.now() * 0.001 + particle.phase) * 0.08;

    particle.x += particle.vx + drift - dx * pull * 0.0015;
    particle.y += particle.vy - drift - dy * pull * 0.0015;

    if (particle.x < -24) particle.x = state.width + 24;
    if (particle.x > state.width + 24) particle.x = -24;
    if (particle.y < -24) particle.y = state.height + 24;
    if (particle.y > state.height + 24) particle.y = -24;

    ctx.beginPath();
    ctx.fillStyle = `rgba(${colorFor(particle)}, ${0.22 + pull * 0.52})`;
    ctx.arc(particle.x, particle.y, particle.r + pull * 2.6, 0, Math.PI * 2);
    ctx.fill();
  }

  for (let i = 0; i < state.particles.length; i += 1) {
    const a = state.particles[i];
    for (let j = i + 1; j < state.particles.length; j += 1) {
      const b = state.particles[j];
      const distance = Math.hypot(a.x - b.x, a.y - b.y);
      if (distance < 112) {
        ctx.strokeStyle = `rgba(246, 241, 223, ${0.1 * (1 - distance / 112)})`;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(a.x, a.y);
        ctx.lineTo(b.x, b.y);
        ctx.stroke();
      }
    }
  }

  state.animationId = requestAnimationFrame(draw);
}

function updateProgress() {
  const max = document.documentElement.scrollHeight - window.innerHeight;
  const value = max > 0 ? window.scrollY / max : 0;
  progress.style.transform = `scaleX(${Math.min(1, Math.max(0, value))})`;
}

function setMenu(open) {
  document.body.classList.toggle("menu-open", open);
  menuButton.setAttribute("aria-expanded", String(open));
}

function updateSpotlight(event) {
  const element = event.currentTarget;
  const rect = element.getBoundingClientRect();
  element.style.setProperty("--mx", `${event.clientX - rect.left}px`);
  element.style.setProperty("--my", `${event.clientY - rect.top}px`);
}

window.addEventListener("resize", () => {
  resize();
  updateProgress();
});

window.addEventListener("scroll", updateProgress, { passive: true });

window.addEventListener("pointermove", (event) => {
  state.pointer.x = event.clientX;
  state.pointer.y = event.clientY;
  state.pointer.active = true;

  if (glow) {
    glow.style.left = `${event.clientX}px`;
    glow.style.top = `${event.clientY}px`;
  }
});

window.addEventListener("pointerleave", () => {
  state.pointer.active = false;
});

menuButton.addEventListener("click", () => {
  setMenu(!document.body.classList.contains("menu-open"));
});

mobileMenu.querySelectorAll("a").forEach((link) => {
  link.addEventListener("click", () => setMenu(false));
});

document.addEventListener("keydown", (event) => {
  if (event.key === "Escape") setMenu(false);
});

document.querySelectorAll(".spotlight").forEach((element) => {
  element.addEventListener("pointermove", updateSpotlight);
});

const revealTargets = document.querySelectorAll(
  ".section-panel > *, .metric-card, .capability-card, .method-node, .note-link, .contact-panel",
);

revealTargets.forEach((element) => element.classList.add("reveal"));

if ("IntersectionObserver" in window && !reduceMotion.matches) {
  const observer = new IntersectionObserver(
    (entries) => {
      entries.forEach((entry) => {
        if (entry.isIntersecting) {
          entry.target.classList.add("is-visible");
          observer.unobserve(entry.target);
        }
      });
    },
    { threshold: 0.15, rootMargin: "0px 0px -8% 0px" },
  );

  revealTargets.forEach((element) => observer.observe(element));
} else {
  revealTargets.forEach((element) => element.classList.add("is-visible"));
}

resize();
updateProgress();

if (!reduceMotion.matches) {
  draw();
}

reduceMotion.addEventListener("change", () => {
  if (state.animationId) cancelAnimationFrame(state.animationId);
  resize();
  if (!reduceMotion.matches) draw();
});
