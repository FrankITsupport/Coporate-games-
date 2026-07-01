(function () {
  "use strict";

  const profiles = {
    easy: { speed: .29, spawnGap: 820, obstacleChance: .23, basePoints: 190 },
    standard: { speed: .37, spawnGap: 650, obstacleChance: .31, basePoints: 240 },
    pro: { speed: .47, spawnGap: 500, obstacleChance: .39, basePoints: 310 }
  };

  const itemTypes = {
    trophy: { label: "🏆", kind: "collect" },
    "visa-shield": { label: "VISA", kind: "power" },
    "global-boost": { label: "×2", kind: "power" },
    "golden-ball": { label: "GOLD", kind: "collect" },
    "red-card": { label: "RED", kind: "obstacle" },
    defender: { label: "BLOCK", kind: "obstacle" }
  };

  class TrophyRushGame {
    constructor(elements, callbacks) {
      this.el = elements;
      this.callbacks = callbacks;
      this.state = null;
      this.animationFrame = null;
      this.pointerStart = null;
      this.el.leftButton.addEventListener("click", () => this.moveLane(-1));
      this.el.rightButton.addEventListener("click", () => this.moveLane(1));
      this.el.arena.addEventListener("pointerdown", event => this.onPointerDown(event));
      this.el.arena.addEventListener("pointerup", event => this.onPointerUp(event));
      document.addEventListener("keydown", event => {
        if (!this.state?.running || this.state.paused) return;
        if (event.key === "ArrowLeft") this.moveLane(-1);
        if (event.key === "ArrowRight") this.moveLane(1);
      });
    }

    start(options) {
      this.stop();
      this.options = options;
      this.profile = profiles[options.difficulty] || profiles.standard;
      const now = performance.now();
      this.state = {
        running: true, paused: false, lane: 1, score: 0, combo: 1, bestCombo: 1,
        timeLeft: options.duration, startTime: now, lastFrame: now, lastSpawn: now - 400,
        totalPaused: 0, pausedAt: 0, items: [], shield: false, boostUntil: 0,
        distance: 0, collected: 0
      };
      this.el.layer.innerHTML = "";
      this.el.feedback.className = "rush-feedback";
      this.setLane(1);
      this.updatePowerUI();
      this.updateHud();
      this.loop(now);
    }

    loop(now) {
      if (!this.state?.running) return;
      if (!this.state.paused) {
        const dt = Math.min(40, now - this.state.lastFrame) / 1000;
        this.state.lastFrame = now;
        const elapsed = (now - this.state.startTime - this.state.totalPaused) / 1000;
        this.state.timeLeft = Math.max(0, Math.ceil(this.options.duration - elapsed));
        this.state.distance = Math.floor(elapsed * (11 + this.profile.speed * 15));
        if (elapsed >= this.options.duration) return this.finish();
        if (now - this.state.lastSpawn >= this.profile.spawnGap) {
          this.spawnItem();
          this.state.lastSpawn = now;
        }
        this.updateItems(dt, now);
        if (this.state.boostUntil && now >= this.state.boostUntil) {
          this.state.boostUntil = 0;
          this.updatePowerUI();
        }
        this.updateHud();
      } else {
        this.state.lastFrame = now;
      }
      this.animationFrame = requestAnimationFrame(time => this.loop(time));
    }

    spawnItem() {
      const roll = Math.random();
      let type;
      if (roll < this.profile.obstacleChance) type = Math.random() < .55 ? "red-card" : "defender";
      else if (roll < this.profile.obstacleChance + .11) type = "visa-shield";
      else if (roll < this.profile.obstacleChance + .21) type = "global-boost";
      else if (roll < this.profile.obstacleChance + .28) type = "golden-ball";
      else type = "trophy";
      const lane = Math.floor(Math.random() * 3);
      const element = document.createElement("div");
      element.className = `rush-item ${type}`;
      element.textContent = itemTypes[type].label;
      element.setAttribute("aria-hidden", "true");
      this.el.layer.appendChild(element);
      this.state.items.push({ element, type, lane, progress: 0, resolved: false });
    }

    updateItems(dt, now) {
      for (let index = this.state.items.length - 1; index >= 0; index--) {
        const item = this.state.items[index];
        item.progress += this.profile.speed * dt;
        const laneOffset = item.lane - 1;
        const spread = 7 + item.progress * 25;
        const left = 50 + laneOffset * spread;
        const top = 5 + item.progress * 88;
        const scale = .34 + item.progress * .95;
        const rotation = item.type === "red-card" ? " rotate(8deg)" : "";
        item.element.style.left = `${left}%`;
        item.element.style.top = `${top}%`;
        item.element.style.transform = `translate(-50%,-50%) scale(${scale})${rotation}`;
        if (!item.resolved && item.progress >= .79 && item.progress <= 1.02 && item.lane === this.state.lane) {
          item.resolved = true;
          this.resolveItem(item, now);
          this.removeItem(index);
          continue;
        }
        if (item.progress > 1.08) {
          if (!item.resolved && itemTypes[item.type].kind === "collect") {
            this.state.combo = Math.max(1, this.state.combo - .25);
          }
          this.removeItem(index);
        }
      }
    }

    resolveItem(item, now) {
      const kind = itemTypes[item.type].kind;
      if (kind === "obstacle") {
        if (this.state.shield) {
          this.state.shield = false;
          this.showFeedback("VISA SHIELD SAVED YOU", "power");
        } else {
          const penalty = item.type === "defender" ? 350 : 250;
          this.state.score = Math.max(0, this.state.score - penalty);
          this.state.combo = 1;
          this.showFeedback(`−${penalty} ${item.type === "defender" ? "BLOCKED" : "RED CARD"}`, "bad");
        }
      } else if (item.type === "visa-shield") {
        this.state.shield = true;
        this.state.score += 100;
        this.showFeedback("VISA SHIELD ON", "power");
      } else if (item.type === "global-boost") {
        this.state.boostUntil = now + 7000;
        this.state.score += 150;
        this.showFeedback("GLOBALPAY ×2", "power");
      } else {
        const base = item.type === "golden-ball" ? 650 : this.profile.basePoints;
        const boost = this.state.boostUntil > now ? 2 : 1;
        const points = Math.round(base * this.state.combo * boost);
        this.state.score += points;
        this.state.combo = Math.min(5, this.state.combo + .25);
        this.state.bestCombo = Math.max(this.state.bestCombo, this.state.combo);
        this.state.collected++;
        this.showFeedback(`+${points} ${item.type === "golden-ball" ? "GOLDEN BALL" : "TROPHY"}`, "good");
      }
      this.updatePowerUI();
      this.updateHud();
    }

    removeItem(index) {
      this.state.items[index].element.remove();
      this.state.items.splice(index, 1);
    }

    onPointerDown(event) {
      if (!this.state?.running || this.state.paused || event.target.closest("button")) return;
      this.pointerStart = { x: event.clientX, y: event.clientY };
      this.el.arena.setPointerCapture?.(event.pointerId);
    }

    onPointerUp(event) {
      if (!this.pointerStart || !this.state?.running || this.state.paused) return;
      const dx = event.clientX - this.pointerStart.x;
      this.pointerStart = null;
      if (Math.abs(dx) < 38) return;
      this.moveLane(dx < 0 ? -1 : 1);
    }

    moveLane(direction) {
      if (!this.state?.running || this.state.paused) return;
      this.setLane(Math.max(0, Math.min(2, this.state.lane + direction)));
    }

    setLane(lane) {
      if (!this.state) return;
      this.state.lane = lane;
      this.el.ball.classList.remove("lane-0", "lane-1", "lane-2");
      this.el.ball.classList.add(`lane-${lane}`);
    }

    updatePowerUI() {
      if (!this.state) return;
      this.el.shield.classList.toggle("active", this.state.shield);
      this.el.shield.querySelector("strong").textContent = this.state.shield ? "ACTIVE" : "INACTIVE";
      const boosted = this.state.boostUntil > performance.now();
      this.el.multiplier.classList.toggle("active", boosted);
      this.el.multiplier.querySelector("strong").textContent = boosted ? "ACTIVE" : "INACTIVE";
      this.el.ball.classList.toggle("shielded", this.state.shield);
      this.el.ball.classList.toggle("boosted", boosted);
    }

    updateHud() {
      if (!this.state) return;
      this.el.time.textContent = this.state.timeLeft;
      this.el.score.textContent = this.state.score.toLocaleString();
      this.el.combo.textContent = `×${this.formatMultiplier(this.state.combo)}`;
      this.el.distance.textContent = `${this.state.distance}m`;
    }

    formatMultiplier(value) {
      return value.toFixed(value % 1 ? 2 : 0).replace(/0$/, "");
    }

    showFeedback(text, type) {
      this.el.feedback.textContent = text;
      this.el.feedback.className = `rush-feedback ${type}`;
      void this.el.feedback.offsetWidth;
      this.el.feedback.classList.add("show");
    }

    pause() {
      if (!this.state?.running || this.state.paused) return;
      this.state.paused = true;
      this.state.pausedAt = performance.now();
    }

    resume() {
      if (!this.state?.paused) return;
      const pauseDuration = performance.now() - this.state.pausedAt;
      this.state.totalPaused += pauseDuration;
      this.state.lastSpawn += pauseDuration;
      if (this.state.boostUntil) this.state.boostUntil += pauseDuration;
      this.state.lastFrame = performance.now();
      this.state.paused = false;
    }

    finish() {
      if (!this.state?.running) return;
      const result = {
        score: this.state.score, bestCombo: this.state.bestCombo,
        difficulty: this.options.difficulty, collected: this.state.collected,
        distance: this.state.distance
      };
      this.state.running = false;
      if (this.animationFrame) cancelAnimationFrame(this.animationFrame);
      this.callbacks.complete(result);
    }

    stop() {
      if (this.state) this.state.running = false;
      if (this.animationFrame) cancelAnimationFrame(this.animationFrame);
      if (this.el?.layer) this.el.layer.innerHTML = "";
    }
  }

  window.TrophyRushGame = TrophyRushGame;
})();
