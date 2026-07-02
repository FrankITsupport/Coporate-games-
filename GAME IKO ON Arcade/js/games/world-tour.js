(function () {
  "use strict";

  const profiles = {
    easy: { cycle: 2500, zone: 30, perfectRatio: .38, successPoints: 260, perfectPoints: 520 },
    standard: { cycle: 1750, zone: 22, perfectRatio: .32, successPoints: 340, perfectPoints: 680 },
    pro: { cycle: 1200, zone: 16, perfectRatio: .28, successPoints: 460, perfectPoints: 900 }
  };

  const project = (latitude, longitude) => ({ x: (longitude + 180) / 3.6, y: (90 - latitude) / 1.8 });
  const destinations = [
    { city: "NAIROBI", country: "KENYA", currency: "KES", symbol: "KSh", amount: "3,200", lat: -1.2921, lon: 36.8219 },
    { city: "CAIRO", country: "EGYPT", currency: "EGP", symbol: "E£", amount: "1,450", lat: 30.0444, lon: 31.2357 },
    { city: "PARIS", country: "FRANCE", currency: "EUR", symbol: "€", amount: "38.00", lat: 48.8566, lon: 2.3522 },
    { city: "LONDON", country: "UNITED KINGDOM", currency: "GBP", symbol: "£", amount: "29.00", lat: 51.5074, lon: -0.1278 },
    { city: "NEW YORK", country: "USA", currency: "USD", symbol: "$", amount: "24.00", lat: 40.7128, lon: -74.006 },
    { city: "TORONTO", country: "CANADA", currency: "CAD", symbol: "C$", amount: "31.00", lat: 43.6532, lon: -79.3832 },
    { city: "MEXICO CITY", country: "MEXICO", currency: "MXN", symbol: "MX$", amount: "520", lat: 19.4326, lon: -99.1332 },
    { city: "SÃO PAULO", country: "BRAZIL", currency: "BRL", symbol: "R$", amount: "135", lat: -23.5505, lon: -46.6333 },
    { city: "DUBAI", country: "UAE", currency: "AED", symbol: "AED", amount: "110", lat: 25.2048, lon: 55.2708 },
    { city: "TOKYO", country: "JAPAN", currency: "JPY", symbol: "¥", amount: "4,200", lat: 35.6762, lon: 139.6503 }
  ].map(destination => Object.assign(destination, project(destination.lat, destination.lon)));

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
        totalPaused: 0, pausedAt: 0, destinationIndex: 0, completed: 0, itinerary: shuffled.slice(0, 7),
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
      this.focusMap(destination);
      this.state.markerStart = performance.now();
      this.state.locked = false;
      this.el.payButton.disabled = false;
      this.renderPins();
      this.renderRoute();
      this.renderPassport();
    }

    focusMap(destination) {
      // Keep the active country in the clear left-middle area, away from the payment console.
      const destinationFocus = 42;
      const mapLeft = 3;
      const mapWidth = 94;
      const focusInsideMap = (destinationFocus - mapLeft) / mapWidth * 100;
      const shift = focusInsideMap - destination.x;
      this.el.map.style.setProperty("--map-pan", `${shift}%`);
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
      const lapPosition = this.state.destinationIndex % this.state.itinerary.length;
      this.state.itinerary.forEach((destination, index) => {
        const pin = document.createElement("span");
        pin.className = `map-pin ${index < lapPosition ? "complete" : index === lapPosition ? "current" : ""}`;
        pin.style.left = `${destination.x}%`;
        pin.style.top = `${destination.y}%`;
        pin.title = destination.city;
        pin.dataset.label = `${destination.city} · ${destination.country}`;
        this.el.pins.appendChild(pin);
      });
    }

    renderRoute() {
      const lapPosition = this.state.destinationIndex % this.state.itinerary.length;
      const stops = this.state.itinerary.slice(0, lapPosition + 1);
      if (stops.length < 2) {
        this.el.route.setAttribute("d", "");
        return;
      }
      let path = `M ${stops[0].x.toFixed(2)} ${stops[0].y.toFixed(2)}`;
      for (let index = 1; index < stops.length; index++) {
        const from = stops[index - 1];
        const to = stops[index];
        const middleX = (from.x + to.x) / 2;
        const middleY = Math.max(3, (from.y + to.y) / 2 - Math.min(9, Math.abs(to.x - from.x) * .14 + 3));
        path += ` Q ${middleX.toFixed(2)} ${middleY.toFixed(2)} ${to.x.toFixed(2)} ${to.y.toFixed(2)}`;
      }
      this.el.route.setAttribute("d", path);
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
