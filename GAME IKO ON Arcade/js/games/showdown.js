(function () {
  "use strict";

  const profiles = {
    friendly: { lifetime: 2200, spawnGap: 700, maxTargets: 3, push: 12, dangerChance: .09, powerChance: .19 },
    standard: { lifetime: 1650, spawnGap: 500, maxTargets: 4, push: 11, dangerChance: .14, powerChance: .18 },
    finals: { lifetime: 1150, spawnGap: 350, maxTargets: 5, push: 10, dangerChance: .19, powerChance: .17 }
  };

  class ShowdownGame {
    constructor(elements, callbacks) {
      this.el = elements;
      this.callbacks = callbacks;
      this.state = null;
      this.animationFrame = null;
      this.goalTimer = null;
    }

    start(options) {
      this.stop();
      this.options = options;
      this.profile = profiles[options.difficulty] || profiles.standard;
      const now = performance.now();
      this.state = {
        running: true, paused: false, locked: false, startTime: now,
        totalPaused: 0, pausedAt: 0, timeLeft: options.duration, position: 0,
        goals: [0, 0], hits: [0, 0], combo: [1, 1], bestCombo: [1, 1],
        targets: [[], []], nextSpawn: [now, now + this.profile.spawnGap / 2]
      };
      this.el.nameOne.textContent = options.players[0].name;
      this.el.nameTwo.textContent = options.players[1].name;
      this.el.layerOne.innerHTML = "";
      this.el.layerTwo.innerHTML = "";
      this.el.feedback.className = "showdown-feedback";
      this.updateUI();
      this.loop(now);
    }

    loop(now) {
      if (!this.state?.running) return;
      if (!this.state.paused) {
        const elapsed = (now - this.state.startTime - this.state.totalPaused) / 1000;
        this.state.timeLeft = Math.max(0, Math.ceil(this.options.duration - elapsed));
        if (elapsed >= this.options.duration) return this.finish();
        if (!this.state.locked) {
          for (let side = 0; side < 2; side++) {
            if (now >= this.state.nextSpawn[side] && this.state.targets[side].length < this.profile.maxTargets) {
              this.spawnTarget(side, now);
              this.state.nextSpawn[side] = now + this.profile.spawnGap * (.82 + Math.random() * .36);
            }
            this.expireTargets(side, now);
          }
        }
        this.updateUI();
      }
      this.animationFrame = requestAnimationFrame(time => this.loop(time));
    }

    spawnTarget(side, now) {
      const roll = Math.random();
      const type = roll < this.profile.dangerChance ? "danger" : roll < this.profile.dangerChance + this.profile.powerChance ? "power" : "normal";
      const target = document.createElement("button");
      target.type = "button";
      target.className = `showdown-target ${type === "normal" ? "" : type}`;
      target.textContent = type === "danger" ? "RED" : type === "power" ? "POWER" : "+";
      let x = 50;
      let y = 50;
      for (let attempt = 0; attempt < 12; attempt++) {
        x = 13 + Math.random() * 74;
        y = 13 + Math.random() * 72;
        const clear = this.state.targets[side].every(existing => Math.hypot(x - existing.x, y - existing.y) > 25);
        if (clear) break;
      }
      target.style.left = `${x}%`;
      target.style.top = `${y}%`;
      const item = { element: target, type, x, y, expiresAt: now + this.profile.lifetime, resolved: false };
      target.addEventListener("pointerdown", event => this.hitTarget(event, side, item), { once: true });
      (side === 0 ? this.el.layerOne : this.el.layerTwo).appendChild(target);
      this.state.targets[side].push(item);
    }

    hitTarget(event, side, item) {
      event.preventDefault();
      event.stopPropagation();
      if (!this.state?.running || this.state.paused || this.state.locked || item.resolved) return;
      item.resolved = true;
      this.removeTarget(side, item);
      const direction = side === 0 ? 1 : -1;
      if (item.type === "danger") {
        this.state.position -= direction * this.profile.push * 1.15;
        this.state.combo[side] = 1;
        this.showFeedback(`${this.options.players[side].name} RED CARD`, side === 0 ? "left" : "right");
      } else {
        const comboBoost = 1 + (this.state.combo[side] - 1) * .07;
        const power = item.type === "power" ? 2.05 : 1;
        this.state.position += direction * this.profile.push * power * comboBoost;
        this.state.hits[side]++;
        this.state.combo[side] = Math.min(5, this.state.combo[side] + (item.type === "power" ? .5 : .25));
        this.state.bestCombo[side] = Math.max(this.state.bestCombo[side], this.state.combo[side]);
        if (item.type === "power") this.showFeedback(`${side === 0 ? "GREEN" : "NAVY"} POWER SHOT`, side === 0 ? "left" : "right");
      }
      this.state.position = Math.max(-100, Math.min(100, this.state.position));
      if (Math.abs(this.state.position) >= 96) this.scoreGoal(this.state.position > 0 ? 0 : 1);
      this.updateUI();
    }

    expireTargets(side, now) {
      [...this.state.targets[side]].forEach(item => {
        if (now < item.expiresAt) return;
        this.removeTarget(side, item);
        if (item.type !== "danger") this.state.combo[side] = Math.max(1, this.state.combo[side] - .25);
      });
    }

    removeTarget(side, item) {
      item.element.remove();
      const index = this.state.targets[side].indexOf(item);
      if (index >= 0) this.state.targets[side].splice(index, 1);
    }

    scoreGoal(side) {
      this.state.goals[side]++;
      this.state.locked = true;
      this.clearTargets();
      this.el.ball.classList.remove("goal-flash");
      void this.el.ball.offsetWidth;
      this.el.ball.classList.add("goal-flash");
      this.showFeedback(`GOAL! ${this.options.players[side].name}`, "goal");
      this.goalTimer = window.setTimeout(() => {
        if (!this.state?.running) return;
        this.state.position = 0;
        this.state.combo = [1, 1];
        this.state.locked = false;
        const now = performance.now();
        this.state.nextSpawn = [now + 250, now + 480];
        this.updateUI();
      }, 900);
    }

    clearTargets() {
      for (let side = 0; side < 2; side++) {
        this.state.targets[side].forEach(item => item.element.remove());
        this.state.targets[side] = [];
      }
    }

    updateUI() {
      if (!this.state) return;
      this.el.time.textContent = this.state.timeLeft;
      this.el.goalsOne.textContent = this.state.goals[0];
      this.el.goalsTwo.textContent = this.state.goals[1];
      this.el.comboOne.textContent = `×${this.formatMultiplier(this.state.combo[0])}`;
      this.el.comboTwo.textContent = `×${this.formatMultiplier(this.state.combo[1])}`;
      this.el.ball.style.left = `${50 + this.state.position * .43}%`;
      this.el.greenBar.style.width = `${Math.max(0, this.state.position) * .43}%`;
      this.el.navyBar.style.width = `${Math.max(0, -this.state.position) * .43}%`;
    }

    showFeedback(text, type) {
      this.el.feedback.textContent = text;
      this.el.feedback.className = `showdown-feedback ${type}`;
      void this.el.feedback.offsetWidth;
      this.el.feedback.classList.add("show");
    }

    formatMultiplier(value) {
      return value.toFixed(value % 1 ? 2 : 0).replace(/0$/, "");
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
      this.state.nextSpawn = this.state.nextSpawn.map(value => value + pauseDuration);
      this.state.targets.flat().forEach(item => { item.expiresAt += pauseDuration; });
      this.state.paused = false;
    }

    finish() {
      if (!this.state?.running) return;
      this.state.running = false;
      this.clearTargets();
      if (this.animationFrame) cancelAnimationFrame(this.animationFrame);
      let winner = null;
      if (this.state.goals[0] !== this.state.goals[1]) winner = this.state.goals[0] > this.state.goals[1] ? 0 : 1;
      else if (Math.abs(this.state.position) >= 8) winner = this.state.position > 0 ? 0 : 1;
      this.callbacks.complete({
        winner, goals: [...this.state.goals], hits: [...this.state.hits],
        bestCombo: [...this.state.bestCombo], position: this.state.position,
        difficulty: this.options.difficulty, players: this.options.players
      });
    }

    stop() {
      if (this.state) {
        this.state.running = false;
        this.clearTargets();
      }
      if (this.animationFrame) cancelAnimationFrame(this.animationFrame);
      clearTimeout(this.goalTimer);
    }
  }

  window.ShowdownGame = ShowdownGame;
})();
