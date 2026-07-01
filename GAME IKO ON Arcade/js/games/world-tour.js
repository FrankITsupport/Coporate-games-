(function () {
  "use strict";

  const profiles = {
    easy: { cycle: 2500, zone: 30, perfectRatio: .38, successPoints: 260, perfectPoints: 520 },
    standard: { cycle: 1750, zone: 22, perfectRatio: .32, successPoints: 340, perfectPoints: 680 },
    pro: { cycle: 1200, zone: 16, perfectRatio: .28, successPoints: 460, perfectPoints: 900 }
  };

  const destinations = [
    { city: "NAIROBI", country: "KENYA", currency: "KES", symbol: "KSh", amount: "3,200", x: 55, y: 58 },
    { city: "CAIRO", country: "EGYPT", currency: "EGP", symbol: "E£", amount: "1,450", x: 54, y: 38 },
    { city: "PARIS", country: "FRANCE", currency: "EUR", symbol: "€", amount: "38.00", x: 50, y: 27 },
    { city: "LONDON", country: "UNITED KINGDOM", currency: "GBP", symbol: "£", amount: "29.00", x: 47, y: 23 },
    { city: "NEW YORK", country: "USA", currency: "USD", symbol: "$", amount: "24.00", x: 25, y: 29 },
    { city: "TORONTO", country: "CANADA", currency: "CAD", symbol: "C$", amount: "31.00", x: 23, y: 21 },
    { city: "MEXICO CITY", country: "MEXICO", currency: "MXN", symbol: "MX$", amount: "520", x: 20, y: 42 },
    { city: "SÃO PAULO", country: "BRAZIL", currency: "BRL", symbol: "R$", amount: "135", x: 34, y: 67 },
    { city: "DUBAI", country: "UAE", currency: "AED", symbol: "AED", amount: "110", x: 64, y: 43 },
    { city: "TOKYO", country: "JAPAN", currency: "JPY", symbol: "¥", amount: "4,200", x: 84, y: 39 }
  ];

  class WorldTourGame {
    constructor(elements, callbacks) {
      this.el = elements;
      this.callbacks = callbacks;
      this.state = null;
      this.timers = new Set();
      this.animationFrame = null;
      this.el.payButton.addEventListener("click", () => this.makePayment());
    }

    start(options) {
      this.stop();
      this.options = options;
      this.profile = profiles[options.difficulty] || profiles.standard;
      const shuffled = [...destinations].sort(() => Math.random() - .5);
      this.state = {
        running: true, paused: false, locked: false, score: 0, streak: 1, bestStreak: 1,
        timeLeft: options.duration, startTime: performance.now(), markerStart: performance.now(),
        totalPaused: 0, pausedAt: 0, destinationIndex: 0, completed: 0, itinerary: shuffled,
        markerPosition: 0, zoneLeft: 40, zoneWidth: this.profile.zone
      };
      this.el.feedback.className = "world-feedback";
      this.el.payButton.disabled = false;
      this.renderPins();
      this.prepareDestination();
      this.updateHud();
      this.tick();
    }

    tick() {
      if (!this.state?.running) return;
      const now = performance.now();
      if (!this.state.paused) {
        const elapsed = (now - this.state.startTime - this.state.totalPaused) / 1000;
        this.state.timeLeft = Math.max(0, Math.ceil(this.options.duration - elapsed));
        const markerElapsed = (now - this.state.markerStart) % this.profile.cycle;
        const phase = markerElapsed / this.profile.cycle;
        this.state.markerPosition = phase <= .5 ? phase * 200 : (1 - phase) * 200;
        this.el.marker.style.left = `${this.state.markerPosition}%`;
        this.updateHud();
        if (elapsed >= this.options.duration) return this.finish();
      }
      this.animationFrame = requestAnimationFrame(() => this.tick());
    }

    prepareDestination() {
      if (!this.state?.running) return;
      const destination = this.state.itinerary[this.state.destinationIndex % this.state.itinerary.length];
      this.state.currentDestination = destination;
      const width = this.profile.zone;
      const left = 7 + Math.random() * (86 - width);
      this.state.zoneWidth = width;
      this.state.zoneLeft = left;
      this.el.zone.style.left = `${left}%`;
      this.el.zone.style.width = `${width}%`;
      this.el.city.textContent = destination.city;
      this.el.country.textContent = destination.country;
      this.el.currency.textContent = destination.currency;
      this.el.terminalCurrency.textContent = destination.currency;
      this.el.amount.textContent = `${destination.symbol}${destination.amount}`;
      this.el.prompt.textContent = "Tap when the signal reaches green";
      this.state.markerStart = performance.now();
      this.state.locked = false;
      this.el.payButton.disabled = false;
      this.renderPins();
      this.renderPassport();
    }

    makePayment() {
      if (!this.state?.running || this.state.paused || this.state.locked) return;
      this.state.locked = true;
      this.el.payButton.disabled = true;
      const center = this.state.zoneLeft + this.state.zoneWidth / 2;
      const distance = Math.abs(this.state.markerPosition - center);
      const halfWidth = this.state.zoneWidth / 2;
      const hit = distance <= halfWidth;
      const perfect = hit && distance <= halfWidth * this.profile.perfectRatio;
      if (perfect) {
        const points = Math.round(this.profile.perfectPoints * this.state.streak);
        this.state.score += points;
        this.state.streak = Math.min(5, this.state.streak + .5);
        this.state.bestStreak = Math.max(this.state.bestStreak, this.state.streak);
        this.state.completed++;
        this.showFeedback(`PERFECT +${points}`, "perfect");
        this.advanceDestination();
      } else if (hit) {
        const points = Math.round(this.profile.successPoints * this.state.streak);
        this.state.score += points;
        this.state.streak = Math.min(5, this.state.streak + .25);
        this.state.bestStreak = Math.max(this.state.bestStreak, this.state.streak);
        this.state.completed++;
        this.showFeedback(`APPROVED +${points}`, "good");
        this.advanceDestination();
      } else {
        this.state.streak = 1;
        this.showFeedback("SO CLOSE — TRY AGAIN", "retry");
        this.el.prompt.textContent = "Resetting payment signal…";
        this.setTimer(() => this.prepareDestination(), 850);
      }
      this.updateHud();
    }

    advanceDestination() {
      const current = this.el.pins.querySelector(".map-pin.current");
      current?.classList.remove("current");
      current?.classList.add("complete");
      this.state.destinationIndex++;
      this.el.prompt.textContent = "Payment approved — next destination";
      this.setTimer(() => this.prepareDestination(), 850);
    }

    renderPins() {
      this.el.pins.innerHTML = "";
      this.state.itinerary.slice(0, 7).forEach((destination, index) => {
        const pin = document.createElement("span");
        pin.className = `map-pin ${index < this.state.destinationIndex ? "complete" : index === this.state.destinationIndex ? "current" : ""}`;
        pin.style.left = `${destination.x}%`;
        pin.style.top = `${destination.y}%`;
        pin.title = destination.city;
        this.el.pins.appendChild(pin);
      });
    }

    renderPassport() {
      this.el.passport.innerHTML = "";
      const lapPosition = this.state.destinationIndex % 7;
      for (let index = 0; index < 7; index++) {
        const stamp = document.createElement("span");
        stamp.className = `passport-stamp ${index < lapPosition ? "complete" : index === lapPosition ? "current" : ""}`;
        stamp.textContent = index < lapPosition ? "✓" : String(index + 1).padStart(2, "0");
        this.el.passport.appendChild(stamp);
      }
    }

    showFeedback(text, type) {
      this.el.feedback.textContent = text;
      this.el.feedback.className = `world-feedback ${type}`;
      void this.el.feedback.offsetWidth;
      this.el.feedback.classList.add("show");
    }

    updateHud() {
      if (!this.state) return;
      this.el.time.textContent = this.state.timeLeft;
      this.el.score.textContent = this.state.score.toLocaleString();
      this.el.combo.textContent = `×${this.state.streak.toFixed(this.state.streak % 1 ? 2 : 0).replace(/0$/, "")}`;
    }

    pause() {
      if (!this.state?.running || this.state.paused) return;
      this.state.paused = true;
      this.state.pausedAt = performance.now();
      this.el.payButton.disabled = true;
    }

    resume() {
      if (!this.state?.paused) return;
      const pauseDuration = performance.now() - this.state.pausedAt;
      this.state.totalPaused += pauseDuration;
      this.state.markerStart += pauseDuration;
      this.state.paused = false;
      this.el.payButton.disabled = this.state.locked;
    }

    finish() {
      if (!this.state?.running) return;
      const result = { score: this.state.score, bestCombo: this.state.bestStreak, difficulty: this.options.difficulty, completed: this.state.completed };
      this.state.running = false;
      this.stopTimers();
      this.el.payButton.disabled = true;
      this.callbacks.complete(result);
    }

    stop() {
      if (this.state) this.state.running = false;
      this.stopTimers();
      if (this.el?.payButton) this.el.payButton.disabled = false;
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

  window.WorldTourGame = WorldTourGame;
})();
