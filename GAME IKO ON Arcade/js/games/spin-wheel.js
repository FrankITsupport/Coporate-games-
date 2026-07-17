(function () {
  "use strict";

  const SVG_NS = "http://www.w3.org/2000/svg";
  const RADIANS = Math.PI / 180;

  const RARITY_WEIGHTS = { "common": 1, "rare": .5, "very-rare": .25, "ultra-rare": .1, "none": 0 };
  const CUSTOMER_PRIZE_MULTIPLIERS = { "existing": 2.5 };

  const segments = [
    { id: "tote-bag", label: ["TOTE", "BAG"], name: "TOTE BAG", type: "prize", icon: "assets/prize-tote-bag.svg", color: "#3bb44a", value: 500, weight: 1 },
    { id: "try-again", label: ["TRY", "AGAIN"], name: "TRY AGAIN", type: "miss", color: "#1434cb", value: 0, weight: .65 },
    { id: "jersey", label: ["JERSEY"], name: "JERSEY", type: "prize", icon: "assets/prize-jersey.svg", color: "#3bb44a", value: 800, weight: .25 },
    { id: "try-again", label: ["TRY", "AGAIN"], name: "TRY AGAIN", type: "miss", color: "#1434cb", value: 0, weight: .65 },
    { id: "water-bottle", label: ["WATER", "BOTTLE"], name: "WATER BOTTLE", type: "prize", icon: "assets/prize-water-bottle.svg", color: "#3bb44a", value: 400, weight: .25 },
    { id: "try-again", label: ["TRY", "AGAIN"], name: "TRY AGAIN", type: "miss", color: "#1434cb", value: 0, weight: .65 },
    { id: "phone-charger", label: ["PHONE", "CHARGER"], name: "PHONE CHARGER", type: "prize", icon: "assets/prize-phone-charger.svg", color: "#3bb44a", value: 550, weight: .25 },
    { id: "try-again", label: ["TRY", "AGAIN"], name: "TRY AGAIN", type: "miss", color: "#1434cb", value: 0, weight: .65 },
    { id: "bucket-hat", label: ["BUCKET", "HAT"], name: "BUCKET HAT", type: "prize", icon: "assets/prize-bucket-hat.svg", color: "#3bb44a", value: 600, weight: 1 },
    { id: "try-again", label: ["TRY", "AGAIN"], name: "TRY AGAIN", type: "miss", color: "#1434cb", value: 0, weight: .65 },
    { id: "tshirt", label: ["T-SHIRT"], name: "BRANDED T-SHIRT", type: "prize", icon: "assets/prize-tshirt.svg", color: "#3bb44a", value: 700, weight: .25 },
    { id: "try-again", label: ["TRY", "AGAIN"], name: "TRY AGAIN", type: "miss", color: "#1434cb", value: 0, weight: .65 }
  ];

  const SEGMENT_ANGLE = 360 / segments.length;

  const rimPoint = (radius, angle) => ({ x: 100 + radius * Math.sin(angle * RADIANS), y: 100 - radius * Math.cos(angle * RADIANS) });

  class SpinWheelGame {
    constructor(elements, callbacks) {
      this.el = elements;
      this.callbacks = callbacks;
      this.state = null;
      this.rotation = 0;
      this.customerCategory = "new";
      this.animationFrame = null;
      this.timers = new Set();
      this.buildWheel();
      this.el.spinButton.addEventListener("click", () => this.spin());
      this.el.wheel.addEventListener("pointerdown", () => this.spin());
    }

    buildWheel() {
      const svg = this.el.wheel;
      svg.innerHTML = "";
      const rim = document.createElementNS(SVG_NS, "circle");
      rim.setAttribute("cx", "100"); rim.setAttribute("cy", "100"); rim.setAttribute("r", "99");
      rim.setAttribute("class", "wheel-rim");
      svg.appendChild(rim);
      this.rotor = document.createElementNS(SVG_NS, "g");
      this.rotor.setAttribute("class", "wheel-rotor");
      svg.appendChild(this.rotor);
      this.wedges = segments.map((segment, index) => {
        const start = index * SEGMENT_ANGLE;
        const end = start + SEGMENT_ANGLE;
        const middle = start + SEGMENT_ANGLE / 2;
        const group = document.createElementNS(SVG_NS, "g");
        group.setAttribute("class", `wheel-seg ${segment.type}`);
        const from = rimPoint(91, start);
        const to = rimPoint(91, end);
        const wedge = document.createElementNS(SVG_NS, "path");
        wedge.setAttribute("d", `M100,100 L${from.x.toFixed(2)},${from.y.toFixed(2)} A91,91 0 0 1 ${to.x.toFixed(2)},${to.y.toFixed(2)} Z`);
        wedge.setAttribute("fill", segment.color);
        wedge.setAttribute("stroke", "#0c2911");
        wedge.setAttribute("stroke-width", "1.4");
        group.appendChild(wedge);
        segment.label.forEach((line, lineIndex) => {
          const text = document.createElementNS(SVG_NS, "text");
          text.setAttribute("x", "100");
          text.setAttribute("y", String(22 + lineIndex * 10 + (segment.label.length === 1 ? 5 : 0)));
          text.setAttribute("class", "wheel-seg-label");
          text.setAttribute("text-anchor", "middle");
          text.setAttribute("transform", `rotate(${middle} 100 100)`);
          text.textContent = line;
          group.appendChild(text);
        });
        if (segment.icon) {
          const icon = document.createElementNS(SVG_NS, "image");
          icon.setAttribute("href", segment.icon);
          icon.setAttribute("x", "87"); icon.setAttribute("y", "46");
          icon.setAttribute("width", "26"); icon.setAttribute("height", "26");
          icon.setAttribute("transform", `rotate(${middle} 100 100)`);
          group.appendChild(icon);
        } else {
          const glyph = document.createElementNS(SVG_NS, "text");
          glyph.setAttribute("x", "100"); glyph.setAttribute("y", "66");
          glyph.setAttribute("class", "wheel-seg-glyph");
          glyph.setAttribute("text-anchor", "middle");
          glyph.setAttribute("transform", `rotate(${middle} 100 100)`);
          glyph.textContent = "↻";
          group.appendChild(glyph);
        }
        this.rotor.appendChild(group);
        return group;
      });
      for (let index = 0; index < 18; index++) {
        const spot = rimPoint(95, index * 20);
        const led = document.createElementNS(SVG_NS, "circle");
        led.setAttribute("cx", spot.x.toFixed(2)); led.setAttribute("cy", spot.y.toFixed(2));
        led.setAttribute("r", "2.4");
        led.setAttribute("class", `wheel-led ${index % 2 ? "odd" : ""}`);
        svg.appendChild(led);
      }
    }

    start(options = {}) {
      this.stop();
      this.customerCategory = options.customerCategory === "existing" ? "existing" : "new";
      this.state = { running: true, paused: false, spinning: false, resultIndex: null };
      this.wedges.forEach(wedge => wedge.classList.remove("won"));
      this.el.wheel.classList.remove("spinning");
      this.el.spinButton.disabled = false;
      this.el.status.textContent = "Land a prize to win. TRY AGAIN earns one extra spin.";
    }

    segmentWeights() {
      const settings = window.GameIkoStorage?.getSettings() || {};
      const rarity = this.customerCategory === "new"
        ? settings.newCustomerPrizeRarity || {}
        : settings.prizeRarity || {};
      const dailyLimits = settings.prizeDailyLimits || {};
      const dailyCounts = window.GameIkoStorage?.getDailyPrizeCounts?.() || {};
      return segments.map(segment => {
        if (segment.type !== "prize") return segment.weight;
        const dailyLimit = dailyLimits[segment.id] === "" || dailyLimits[segment.id] == null
          ? Infinity
          : Number(dailyLimits[segment.id]);
        if (Number.isFinite(dailyLimit) && (dailyCounts[segment.id] || 0) >= Math.max(0, dailyLimit)) return 0;
        if (rarity[segment.id] === "none") return 0;
        const weight = RARITY_WEIGHTS[rarity[segment.id]];
        const baseWeight = typeof weight === "number" ? weight : segment.weight;
        return baseWeight * (CUSTOMER_PRIZE_MULTIPLIERS[this.customerCategory] || 1);
      });
    }

    pickSegmentIndex() {
      const weights = this.segmentWeights();
      const total = weights.reduce((sum, weight) => sum + weight, 0);
      let roll = Math.random() * total;
      for (let index = 0; index < segments.length; index++) {
        roll -= weights[index];
        if (roll <= 0 && weights[index] > 0) return index;
      }
      return segments.findIndex(segment => segment.type === "miss");
    }

    spin() {
      if (!this.state?.running || this.state.paused || this.state.spinning) return;
      const index = this.pickSegmentIndex();
      const jitter = (Math.random() * .64 - .32) * SEGMENT_ANGLE;
      const landing = ((360 - (index * SEGMENT_ANGLE + SEGMENT_ANGLE / 2 + jitter)) % 360 + 360) % 360;
      const current = ((this.rotation % 360) + 360) % 360;
      const turns = 5 + Math.floor(Math.random() * 3);
      this.state.spinning = true;
      this.state.resultIndex = index;
      this.el.spinButton.disabled = true;
      this.el.wheel.classList.add("spinning");
      this.el.status.textContent = "Round and round it goes…";
      this.animation = {
        from: this.rotation,
        delta: ((landing - current) % 360 + 360) % 360 + turns * 360,
        start: performance.now(),
        duration: 4400 + Math.random() * 700,
        lastSegment: Math.floor(this.rotation / SEGMENT_ANGLE)
      };
      this.frame();
    }

    frame() {
      if (!this.state?.running) return;
      if (!this.state.paused) {
        const progress = Math.min(1, (performance.now() - this.animation.start) / this.animation.duration);
        const eased = 1 - Math.pow(1 - progress, 4);
        this.rotation = this.animation.from + this.animation.delta * eased;
        this.rotor.setAttribute("transform", `rotate(${(this.rotation % 360).toFixed(3)} 100 100)`);
        const segmentUnderPointer = Math.floor(this.rotation / SEGMENT_ANGLE);
        if (segmentUnderPointer !== this.animation.lastSegment) {
          this.animation.lastSegment = segmentUnderPointer;
          this.el.pointer.classList.remove("tick");
          void this.el.pointer.offsetWidth;
          this.el.pointer.classList.add("tick");
        }
        if (progress >= 1) return this.settle();
      }
      this.animationFrame = requestAnimationFrame(() => this.frame());
    }

    settle() {
      const segment = segments[this.state.resultIndex];
      this.el.wheel.classList.remove("spinning");
      this.wedges[this.state.resultIndex].classList.add("won");
      this.el.status.textContent = segment.type === "prize"
        ? `${segment.name}! What a spin!`
        : "TRY AGAIN!";
      this.setTimer(() => {
        if (!this.state) return;
        this.state.running = false;
        this.callbacks.complete({
          id: segment.id, label: segment.name, icon: segment.icon || null,
          value: segment.value, won: segment.type === "prize",
          customerCategory: this.customerCategory
        });
      }, 1500);
    }

    pause() {
      if (!this.state?.running || this.state.paused) return;
      this.state.paused = true;
      this.state.pausedAt = performance.now();
    }

    resume() {
      if (!this.state?.paused) return;
      if (this.animation) this.animation.start += performance.now() - this.state.pausedAt;
      this.state.paused = false;
    }

    stop() {
      if (this.state) this.state.running = false;
      if (this.animationFrame) cancelAnimationFrame(this.animationFrame);
      this.timers.forEach(id => clearTimeout(id));
      this.timers.clear();
      if (this.el?.spinButton) this.el.spinButton.disabled = false;
      this.el?.wheel?.classList.remove("spinning");
    }

    setTimer(callback, delay) {
      const id = window.setTimeout(() => { this.timers.delete(id); callback(); }, delay);
      this.timers.add(id);
      return id;
    }
  }

  window.SpinWheelGame = SpinWheelGame;
})();
