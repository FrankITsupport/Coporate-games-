(function () {
  "use strict";

  const profiles = {
    easy: { lifetime: 2300, gap: 560, keeperSpeed: 700, base: 140, dangerChance: .10, bonusChance: .14 },
    standard: { lifetime: 1700, gap: 390, keeperSpeed: 520, base: 180, dangerChance: .15, bonusChance: .16 },
    pro: { lifetime: 1150, gap: 260, keeperSpeed: 340, base: 230, dangerChance: .20, bonusChance: .18 }
  };

  class GoldenGoalGame {
    constructor(elements, callbacks) {
      this.el = elements;
      this.callbacks = callbacks;
      this.state = null;
      this.timers = new Set();
      this.animationFrame = null;
      this.pointerStart = null;
      this.boundDown = event => this.onPointerDown(event);
      this.boundUp = event => this.onPointerUp(event);
      this.el.ball.addEventListener("pointerdown", this.boundDown);
      window.addEventListener("pointerup", this.boundUp);
    }

    start(options) {
      this.stopTimers();
      this.options = options;
      this.profile = profiles[options.difficulty] || profiles.standard;
      this.state = { running: true, paused: false, score: 0, combo: 1, bestCombo: 1, timeLeft: options.duration, target: null, startTime: performance.now(), pausedAt: 0, totalPaused: 0 };
      this.el.layer.innerHTML = "";
      this.el.feedback.innerHTML = "";
      this.resetBall();
      this.updateHud();
      this.spawnTarget();
      this.moveKeeper();
      this.tick();
    }

    tick() {
      if (!this.state || !this.state.running) return;
      if (!this.state.paused) {
        const elapsed = (performance.now() - this.state.startTime - this.state.totalPaused) / 1000;
        this.state.timeLeft = Math.max(0, Math.ceil(this.options.duration - elapsed));
        this.updateHud();
        if (elapsed >= this.options.duration) return this.finish();
      }
      this.animationFrame = requestAnimationFrame(() => this.tick());
    }

    spawnTarget() {
      if (!this.state?.running || this.state.paused) return;
      if (this.state.target) this.state.target.remove();
      const roll = Math.random();
      const type = roll < this.profile.dangerChance ? "danger" : roll < this.profile.dangerChance + this.profile.bonusChance ? "bonus" : "normal";
      const target = document.createElement("div");
      target.className = `goal-target ${type === "normal" ? "" : type}`;
      target.dataset.type = type;
      target.textContent = type === "danger" ? "RED" : type === "bonus" ? "×2" : `+${this.profile.base}`;
      const sizeAllowance = 18;
      target.style.left = `${sizeAllowance / 2 + Math.random() * (100 - sizeAllowance)}%`;
      target.style.top = `${5 + Math.random() * 70}%`;
      target.style.transform = "translate(-50%,-50%)";
      this.el.layer.appendChild(target);
      this.state.target = target;
      const expiry = this.setTimer(() => {
        if (this.state?.target === target) {
          target.remove();
          this.state.target = null;
          this.state.combo = 1;
          this.updateHud();
          this.setTimer(() => this.spawnTarget(), this.profile.gap);
        }
      }, this.profile.lifetime);
      target.dataset.expiry = String(expiry);
    }

    moveKeeper() {
      if (!this.state?.running) return;
      if (!this.state.paused) this.el.keeper.style.left = `${20 + Math.random() * 60}%`;
      this.setTimer(() => this.moveKeeper(), this.profile.keeperSpeed);
    }

    onPointerDown(event) {
      if (!this.state?.running || this.state.paused) return;
      event.preventDefault();
      this.pointerStart = { x: event.clientX, y: event.clientY, time: performance.now() };
      this.el.ball.setPointerCapture?.(event.pointerId);
      this.el.ball.classList.add("ready");
    }

    onPointerUp(event) {
      if (!this.pointerStart || !this.state?.running || this.state.paused) return;
      const start = this.pointerStart;
      this.pointerStart = null;
      this.el.ball.classList.remove("ready");
      const dx = event.clientX - start.x;
      const dy = event.clientY - start.y;
      const distance = Math.hypot(dx, dy);
      if (distance < 35 || dy > -12) {
        this.callbacks.feedback?.("Swipe upward to shoot");
        return;
      }
      const arenaRect = this.el.arena.getBoundingClientRect();
      const goalRect = this.el.layer.getBoundingClientRect();
      const power = Math.min(1, distance / 330);
      const projectedX = Math.max(goalRect.left + 20, Math.min(goalRect.right - 20, start.x + dx * (1.25 + power)));
      const projectedY = Math.max(goalRect.top + 20, Math.min(goalRect.bottom - 20, start.y + dy * (1.35 + power)));
      this.shoot(projectedX - arenaRect.left, projectedY - arenaRect.top, projectedX, projectedY);
    }

    shoot(localX, localY, screenX, screenY) {
      this.el.ball.classList.add("shooting");
      this.el.ball.style.left = `${localX}px`;
      this.el.ball.style.bottom = `${this.el.arena.clientHeight - localY}px`;
      this.el.ball.style.transform = "translate(-50%,50%) scale(.42) rotate(160deg)";
      this.setTimer(() => {
        this.resolveShot(screenX, screenY);
        this.resetBall();
      }, 430);
    }

    resolveShot(screenX, screenY) {
      const target = this.state?.target;
      if (!target) return this.miss(screenX, screenY);
      const rect = target.getBoundingClientRect();
      const padding = Math.max(rect.width * .22, 20);
      const hit = screenX >= rect.left - padding && screenX <= rect.right + padding && screenY >= rect.top - padding && screenY <= rect.bottom + padding;
      if (!hit) return this.miss(screenX, screenY);
      const type = target.dataset.type;
      target.remove();
      this.state.target = null;
      if (type === "danger") {
        this.state.score = Math.max(0, this.state.score - 250);
        this.state.combo = 1;
        this.pop("−250 RED CARD", screenX, screenY, "bad");
      } else {
        const bonus = type === "bonus" ? 2 : 1;
        const points = Math.round(this.profile.base * this.state.combo * bonus);
        this.state.score += points;
        this.state.combo = Math.min(5, this.state.combo + .5);
        this.state.bestCombo = Math.max(this.state.bestCombo, this.state.combo);
        this.pop(`+${points}`, screenX, screenY, type === "bonus" ? "bonus" : "good");
      }
      this.updateHud();
      this.setTimer(() => this.spawnTarget(), this.profile.gap);
    }

    miss(x, y) {
      this.state.combo = 1;
      this.pop("MISS", x, y, "bad");
      this.updateHud();
    }

    pop(text, screenX, screenY, kind) {
      const rect = this.el.arena.getBoundingClientRect();
      const pop = document.createElement("div");
      pop.className = `score-pop ${kind}`;
      pop.textContent = text;
      pop.style.left = `${screenX - rect.left}px`;
      pop.style.top = `${screenY - rect.top}px`;
      this.el.feedback.appendChild(pop);
      this.setTimer(() => pop.remove(), 850);
    }

    resetBall() {
      this.el.ball.classList.remove("shooting", "ready");
      this.el.ball.style.left = "50%";
      this.el.ball.style.bottom = "9%";
      this.el.ball.style.transform = "translateX(-50%)";
    }

    updateHud() {
      if (!this.state) return;
      this.el.time.textContent = this.state.timeLeft;
      this.el.score.textContent = this.state.score.toLocaleString();
      this.el.combo.textContent = `×${this.state.combo.toFixed(this.state.combo % 1 ? 1 : 0)}`;
    }

    pause() {
      if (!this.state?.running || this.state.paused) return;
      this.state.paused = true;
      this.state.pausedAt = performance.now();
    }

    resume() {
      if (!this.state?.paused) return;
      this.state.totalPaused += performance.now() - this.state.pausedAt;
      this.state.paused = false;
      if (!this.state.target) this.spawnTarget();
    }

    finish() {
      if (!this.state?.running) return;
      this.state.running = false;
      const result = { score: this.state.score, bestCombo: this.state.bestCombo, difficulty: this.options.difficulty };
      this.stopTimers();
      this.el.layer.innerHTML = "";
      this.callbacks.complete(result);
    }

    stop() {
      if (this.state) this.state.running = false;
      this.stopTimers();
      this.el.layer.innerHTML = "";
      this.resetBall();
    }

    setTimer(callback, delay) {
      const id = window.setTimeout(() => { this.timers.delete(id); callback(); }, delay);
      this.timers.add(id);
      return id;
    }

    stopTimers() {
      this.timers.forEach(id => clearTimeout(id));
      this.timers.clear();
      if (this.animationFrame) cancelAnimationFrame(this.animationFrame);
    }
  }

  window.GoldenGoalGame = GoldenGoalGame;
})();
