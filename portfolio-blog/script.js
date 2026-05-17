const canvas = document.querySelector("#field");
const ctx = canvas.getContext("2d");
const glow = document.querySelector(".cursor-glow");

const state = {
  width: 0,
  height: 0,
  particles: [],
  pointer: { x: window.innerWidth * 0.5, y: window.innerHeight * 0.5 },
};

function resize() {
  const ratio = Math.min(window.devicePixelRatio || 1, 2);
  state.width = window.innerWidth;
  state.height = window.innerHeight;
  canvas.width = Math.floor(state.width * ratio);
  canvas.height = Math.floor(state.height * ratio);
  canvas.style.width = `${state.width}px`;
  canvas.style.height = `${state.height}px`;
  ctx.setTransform(ratio, 0, 0, ratio, 0, 0);

  const count = Math.min(150, Math.max(70, Math.floor((state.width * state.height) / 13000)));
  state.particles = Array.from({ length: count }, (_, index) => ({
    x: Math.random() * state.width,
    y: Math.random() * state.height,
    r: 0.8 + Math.random() * 1.8,
    vx: (Math.random() - 0.5) * 0.38,
    vy: (Math.random() - 0.5) * 0.38,
    hue: index % 3,
  }));
}

function draw() {
  ctx.clearRect(0, 0, state.width, state.height);

  for (const particle of state.particles) {
    const dx = state.pointer.x - particle.x;
    const dy = state.pointer.y - particle.y;
    const distance = Math.hypot(dx, dy);
    const pull = Math.max(0, 1 - distance / 230);

    particle.x += particle.vx - dx * pull * 0.0014;
    particle.y += particle.vy - dy * pull * 0.0014;

    if (particle.x < -20) particle.x = state.width + 20;
    if (particle.x > state.width + 20) particle.x = -20;
    if (particle.y < -20) particle.y = state.height + 20;
    if (particle.y > state.height + 20) particle.y = -20;

    const color =
      particle.hue === 0
        ? "87, 242, 229"
        : particle.hue === 1
          ? "217, 255, 115"
          : "255, 111, 145";

    ctx.beginPath();
    ctx.fillStyle = `rgba(${color}, ${0.28 + pull * 0.48})`;
    ctx.arc(particle.x, particle.y, particle.r + pull * 2.5, 0, Math.PI * 2);
    ctx.fill();
  }

  for (let i = 0; i < state.particles.length; i += 1) {
    const a = state.particles[i];
    for (let j = i + 1; j < state.particles.length; j += 1) {
      const b = state.particles[j];
      const distance = Math.hypot(a.x - b.x, a.y - b.y);
      if (distance < 105) {
        ctx.strokeStyle = `rgba(245, 243, 234, ${0.11 * (1 - distance / 105)})`;
        ctx.lineWidth = 1;
        ctx.beginPath();
        ctx.moveTo(a.x, a.y);
        ctx.lineTo(b.x, b.y);
        ctx.stroke();
      }
    }
  }

  requestAnimationFrame(draw);
}

window.addEventListener("resize", resize);
window.addEventListener("pointermove", (event) => {
  state.pointer.x = event.clientX;
  state.pointer.y = event.clientY;
  glow.style.left = `${event.clientX}px`;
  glow.style.top = `${event.clientY}px`;
});

document.querySelectorAll(".work-card, .note-list a, .primary-action, .ghost-action").forEach((element) => {
  element.addEventListener("pointermove", (event) => {
    const rect = element.getBoundingClientRect();
    element.style.setProperty("--mx", `${event.clientX - rect.left}px`);
    element.style.setProperty("--my", `${event.clientY - rect.top}px`);
  });
});

const revealTargets = document.querySelectorAll(
  ".section-panel > *, .work-card, .metrics article, .note-list a, .node",
);

revealTargets.forEach((element) => element.classList.add("reveal"));

const observer = new IntersectionObserver(
  (entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add("is-visible");
        observer.unobserve(entry.target);
      }
    });
  },
  { threshold: 0.16, rootMargin: "0px 0px -8% 0px" },
);

revealTargets.forEach((element) => observer.observe(element));

resize();
draw();
