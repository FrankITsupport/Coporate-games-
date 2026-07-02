(function () {
  "use strict";

  const DB_NAME = "game_iko_on_arcade";
  const DB_VERSION = 1;
  const PLAYER_STORE = "players";
  const SETTINGS_KEY = "game_iko_on_settings_v1";

  let dbPromise;

  function openDatabase() {
    if (!window.indexedDB) return Promise.resolve(null);
    if (dbPromise) return dbPromise;
    dbPromise = new Promise((resolve, reject) => {
      const request = indexedDB.open(DB_NAME, DB_VERSION);
      request.onupgradeneeded = () => {
        const db = request.result;
        if (!db.objectStoreNames.contains(PLAYER_STORE)) {
          const store = db.createObjectStore(PLAYER_STORE, { keyPath: "id" });
          store.createIndex("normalizedName", "normalizedName", { unique: true });
        }
      };
      request.onsuccess = () => resolve(request.result);
      request.onerror = () => reject(request.error);
    });
    return dbPromise;
  }

  function normalizeName(value) {
    return String(value || "").trim().replace(/\s+/g, " ").toLocaleUpperCase();
  }

  function cleanName(value) {
    return String(value || "").trim().replace(/\s+/g, " ").slice(0, 20).toLocaleUpperCase();
  }

  async function getAllPlayers() {
    const db = await openDatabase();
    if (!db) return JSON.parse(localStorage.getItem("game_iko_on_players_fallback") || "[]");
    return new Promise((resolve, reject) => {
      const request = db.transaction(PLAYER_STORE, "readonly").objectStore(PLAYER_STORE).getAll();
      request.onsuccess = () => resolve(request.result || []);
      request.onerror = () => reject(request.error);
    });
  }

  async function putPlayer(player) {
    const db = await openDatabase();
    if (!db) {
      const players = await getAllPlayers();
      const index = players.findIndex(item => item.id === player.id);
      if (index >= 0) players[index] = player; else players.push(player);
      localStorage.setItem("game_iko_on_players_fallback", JSON.stringify(players));
      return player;
    }
    return new Promise((resolve, reject) => {
      const request = db.transaction(PLAYER_STORE, "readwrite").objectStore(PLAYER_STORE).put(player);
      request.onsuccess = () => resolve(player);
      request.onerror = () => reject(request.error);
    });
  }

  async function createPlayer(name) {
    const displayName = cleanName(name);
    if (displayName.length < 2) throw new Error("Enter at least 2 characters.");
    const players = await getAllPlayers();
    const normalizedName = normalizeName(displayName);
    if (players.some(player => normalizeName(player.normalizedName || player.name) === normalizedName)) throw new Error("That name already exists. Select it from the leaderboard or choose another name.");
    const now = new Date().toISOString();
    const player = {
      id: (crypto.randomUUID ? crypto.randomUUID() : `${Date.now()}-${Math.random().toString(16).slice(2)}`),
      name: displayName,
      normalizedName,
      createdAt: now,
      lastPlayedAt: now,
      games: {}
    };
    return putPlayer(player);
  }

  async function recordScore(playerId, gameId, score, details) {
    const players = await getAllPlayers();
    const player = players.find(item => item.id === playerId);
    if (!player) throw new Error("Player profile not found.");
    const previous = player.games[gameId] || { bestScore: 0, attempts: 0, bestCombo: 1 };
    const numericScore = Math.max(0, Math.round(Number(score) || 0));
    const isMatch = ["win", "loss", "draw"].includes(details.matchResult);
    player.games[gameId] = {
      bestScore: Math.max(previous.bestScore || 0, numericScore),
      lastScore: numericScore,
      attempts: (previous.attempts || 0) + 1,
      bestCombo: Math.max(previous.bestCombo || 1, details.bestCombo || 1),
      wins: (previous.wins || 0) + (details.matchResult === "win" ? 1 : 0),
      draws: (previous.draws || 0) + (details.matchResult === "draw" ? 1 : 0),
      matches: (previous.matches || 0) + (isMatch ? 1 : 0),
      difficulty: details.difficulty || "standard",
      updatedAt: new Date().toISOString()
    };
    player.lastPlayedAt = new Date().toISOString();
    await putPlayer(player);
    return { player, previousBest: previous.bestScore || 0, isPersonalBest: numericScore > (previous.bestScore || 0) };
  }

  async function resetAll() {
    const db = await openDatabase();
    if (!db) {
      localStorage.removeItem("game_iko_on_players_fallback");
      return;
    }
    return new Promise((resolve, reject) => {
      const request = db.transaction(PLAYER_STORE, "readwrite").objectStore(PLAYER_STORE).clear();
      request.onsuccess = () => resolve();
      request.onerror = () => reject(request.error);
    });
  }

  function getSettings() {
    const defaults = { sound: true, duration: 45, idleSeconds: 120 };
    try { return Object.assign(defaults, JSON.parse(localStorage.getItem(SETTINGS_KEY) || "{}")); }
    catch (_) { return defaults; }
  }

  function saveSettings(settings) {
    localStorage.setItem(SETTINGS_KEY, JSON.stringify(settings));
    return settings;
  }

  window.GameIkoStorage = { normalizeName, cleanName, getAllPlayers, createPlayer, putPlayer, recordScore, resetAll, getSettings, saveSettings };
})();
