(function () {
  "use strict";

  const $ = selector => document.querySelector(selector);
  const $$ = selector => [...document.querySelectorAll(selector)];
  const storage = window.GameIkoStorage;

  const screens = {
    attract: $("#attractScreen"), hub: $("#hubScreen"),
    goldenGame: $("#gameScreen"), worldGame: $("#worldGameScreen"), rushGame: $("#rushGameScreen"),
    showdownGame: $("#showdownGameScreen"), wheelGame: $("#wheelGameScreen")
  };
  const gameConfig = {
    "golden-goal": { name: "Golden Goal", screen: "goldenGame", instructions: "instructionsModal" },
    "world-tour": { name: "GlobalPay World Tour", screen: "worldGame", instructions: "worldInstructionsModal" },
    "trophy-rush": { name: "Trophy Rush", screen: "rushGame", instructions: "rushInstructionsModal" },
    showdown: { name: "Goal Line Showdown", screen: "showdownGame", instructions: "showdownInstructionsModal" },
    "spin-wheel": { name: "Spin & Win", screen: "wheelGame", instructions: "wheelInstructionsModal" }
  };
  const modals = $$(".modal");
  const backdrop = $("#modalBackdrop");
  let currentPlayer = null;
  let pendingPlayer = null;
  let pendingGameId = null;
  let currentScreen = "attract";
  let activeGameId = "golden-goal";
  let activeLeaderboardGame = "golden-goal";
  let selectedDifficulty = "standard";
  let selectedWorldDifficulty = "standard";
  let selectedRushDifficulty = "standard";
  let selectedShowdownDifficulty = "standard";
  let showdownPlayers = [];
  let lastPostGame = null;
  let settings = storage.getSettings();
  let idleTimer;
  let toastTimer;

  const games = {
    "golden-goal": new window.GoldenGoalGame({
      arena: $("#goalArena"), layer: $("#targetLayer"), feedback: $("#feedbackLayer"),
      keeper: $("#keeper"), ball: $("#gameBall"), time: $("#hudTime"),
      score: $("#hudScore"), combo: $("#hudCombo"),
      aimLine: $("#aimLine"), aimGhost: $("#aimGhost"), powerMeter: $("#powerMeter"), powerFill: $("#powerMeterFill"),
      tutorial: $("#goldenTutorial"), tutorialDismiss: $("#goldenTutorialDismiss")
    }, { complete: result => finishGameWithLeaderboard("golden-goal", result), feedback: showToast }),
    "world-tour": new window.WorldTourGame({
      arena: $("#worldArena"), marker: $("#scanMarker"), zone: $("#perfectZone"),
      payButton: $("#payNowButton"), prompt: $("#paymentPrompt"), feedback: $("#worldFeedback"),
      city: $("#destinationCity"), country: $("#destinationCountry"), currency: $("#destinationCurrency"),
      terminalCurrency: $("#terminalCurrency"), amount: $("#terminalAmount"), map: $(".world-map"), pins: $("#mapPins"), route: $("#routePath"),
      passport: $("#passportProgress"), time: $("#worldHudTime"), score: $("#worldHudScore"),
      combo: $("#worldHudCombo")
    }, { complete: result => finishGameWithLeaderboard("world-tour", result) }),
    "trophy-rush": new window.TrophyRushGame({
      arena: $("#rushArena"), layer: $("#rushItemLayer"), ball: $("#rushBall"),
      leftButton: $("#rushLeftButton"), rightButton: $("#rushRightButton"),
      feedback: $("#rushFeedback"), shield: $("#shieldIndicator"),
      multiplier: $("#multiplierIndicator"), distance: $("#rushDistance"),
      time: $("#rushHudTime"), score: $("#rushHudScore"), combo: $("#rushHudCombo")
    }, { complete: result => finishGameWithLeaderboard("trophy-rush", result) }),
    showdown: new window.ShowdownGame({
      layerOne: $("#showdownTargetsOne"), layerTwo: $("#showdownTargetsTwo"),
      nameOne: $("#showdownNameOne"), nameTwo: $("#showdownNameTwo"),
      goalsOne: $("#showdownGoalsOne"), goalsTwo: $("#showdownGoalsTwo"),
      pointsOne: $("#showdownPointsOne"), pointsTwo: $("#showdownPointsTwo"),
      comboOne: $("#showdownComboOne"), comboTwo: $("#showdownComboTwo"),
      time: $("#showdownTime"), ball: $("#showdownBall"),
      greenBar: $(".momentum-green"), navyBar: $(".momentum-navy"),
      feedback: $("#showdownFeedback")
    }, { complete: finishShowdownWithLeaderboard }),
    "spin-wheel": new window.SpinWheelGame({
      wheel: $("#prizeWheel"), pointer: $("#wheelPointer"),
      spinButton: $("#spinWheelButton"), status: $("#wheelStatus")
    }, { complete: finishWheel })
  };

  function showScreen(name) {
    Object.entries(screens).forEach(([key, element]) => element.classList.toggle("active", key === name));
    currentScreen = name;
    closeAllModals();
    resetIdleTimer();
  }

  function openModal(id) {
    const modal = typeof id === "string" ? document.getElementById(id) : id;
    closeAllModals(false);
    backdrop.classList.add("show");
    backdrop.setAttribute("aria-hidden", "false");
    modal.classList.add("show");
    window.setTimeout(() => modal.querySelector("input, button:not(.modal-close)")?.focus(), 80);
  }

  function closeAllModals(hideBackdrop = true) {
    modals.forEach(modal => modal.classList.remove("show"));
    if (hideBackdrop) {
      backdrop.classList.remove("show");
      backdrop.setAttribute("aria-hidden", "true");
    }
  }

  function updatePlayerUI() {
    const pill = $("#playerPill");
    pill.querySelector(".player-avatar").textContent = currentPlayer ? currentPlayer.name.charAt(0) : "?";
    pill.querySelector("strong").textContent = currentPlayer ? currentPlayer.name : "Sign in";
    $("#hudPlayer").textContent = currentPlayer?.name || "PLAYER";
    $("#worldHudPlayer").textContent = currentPlayer?.name || "PLAYER";
    $("#rushHudPlayer").textContent = currentPlayer?.name || "PLAYER";
    $("#wheelHudPlayer").textContent = currentPlayer?.name || "PLAYER";
  }

  function beginGame(gameId) {
    activeGameId = gameId;
    if (!currentPlayer) {
      $("#registrationModal").dataset.intent = gameId;
      openModal("registrationModal");
      return;
    }
    openModal(gameConfig[gameId].instructions);
  }

  function beginShowdown() {
    activeGameId = "showdown";
    $("#showdownSetupForm").reset();
    $("#showdownSetupError").textContent = "";
    $("#showdownPlayerOneInput").value = currentPlayer?.name || "";
    $("#showdownPlayerTwoInput").value = "";
    openModal("showdownSetupModal");
  }

  function openFreshRegistration() {
    $("#registrationForm").reset();
    $("#playerNameInput").value = "";
    $("#nameError").textContent = "";
    $("#registrationModal").dataset.intent = "";
    openModal("registrationModal");
    window.setTimeout(() => {
      $("#playerNameInput").value = "";
      $("#playerNameInput").focus();
    }, 100);
  }

  async function resolvePlayerByName(name) {
    const cleaned = storage.cleanName(name);
    if (cleaned.length < 2) throw new Error("Each player name needs at least 2 characters.");
    const normalized = storage.normalizeName(cleaned);
    const players = await storage.getAllPlayers();
    return players.find(player => storage.normalizeName(player.normalizedName || player.name) === normalized) || storage.createPlayer(cleaned);
  }

  async function setupShowdown(event) {
    event.preventDefault();
    const error = $("#showdownSetupError");
    error.textContent = "";
    const nameOne = storage.cleanName($("#showdownPlayerOneInput").value);
    const nameTwo = storage.cleanName($("#showdownPlayerTwoInput").value);
    if (storage.normalizeName(nameOne) === storage.normalizeName(nameTwo)) {
      error.textContent = "Players must use two different names.";
      return;
    }
    try {
      const playerOne = await resolvePlayerByName(nameOne);
      const playerTwo = await resolvePlayerByName(nameTwo);
      showdownPlayers = [playerOne, playerTwo];
      currentPlayer = playerOne;
      updatePlayerUI();
      openModal("showdownInstructionsModal");
    } catch (problem) {
      error.textContent = problem.message || "The players could not be set up.";
    }
  }

  async function registerPlayer(event) {
    event.preventDefault();
    const error = $("#nameError");
    error.textContent = "";
    try {
      currentPlayer = await storage.createPlayer($("#playerNameInput").value);
      $("#playerNameInput").value = "";
      updatePlayerUI();
      showToast(`Welcome, ${currentPlayer.name}`);
      const intent = $("#registrationModal").dataset.intent;
      $("#registrationModal").dataset.intent = "";
      if (gameConfig[intent]) {
        activeGameId = intent;
        openModal(gameConfig[intent].instructions);
      } else closeAllModals();
    } catch (problem) {
      error.textContent = problem.message || "That name could not be registered.";
    }
  }

  async function renderLeaderboard(query = "", gameId = activeLeaderboardGame) {
    activeLeaderboardGame = gameId;
    const players = await storage.getAllPlayers();
    const ranked = players
      .map(player => {
        const data = player.games?.[gameId] || {};
        return {
          player, score: gameId === "showdown" ? data.lastScore ?? data.bestScore ?? 0 : data.bestScore || 0,
          attempts: gameId === "showdown" ? data.matches || 0 : data.attempts || 0,
          bestScore: data.bestScore || 0
        };
      })
      .sort((a, b) => b.score - a.score || b.bestScore - a.bestScore || a.player.name.localeCompare(b.player.name));
    $("#playerCount").textContent = players.length.toLocaleString();
    $("#leaderboardGameLabel").textContent = gameConfig[gameId].name.toLocaleUpperCase();
    $$('[data-leaderboard-game]').forEach(button => button.classList.toggle("selected", button.dataset.leaderboardGame === gameId));
    const normalizedQuery = query.trim().toLocaleUpperCase();
    const visible = normalizedQuery ? ranked.filter(item => item.player.name.includes(normalizedQuery)) : ranked.slice(0, 50);
    const list = $("#leaderboardList");
    if (!visible.length) {
      list.innerHTML = `<div class="empty-board">${players.length ? "No matching player found." : "No scores yet. Be the first on the board."}</div>`;
      return;
    }
    list.innerHTML = "";
    visible.forEach(item => appendLeaderboardRow(list, item, ranked, gameId));
    if (!normalizedQuery && currentPlayer && !visible.some(item => item.player.id === currentPlayer.id)) {
      const currentItem = ranked.find(item => item.player.id === currentPlayer.id);
      if (currentItem) {
        const divider = document.createElement("div");
        divider.className = "leaderboard-help";
        divider.textContent = "YOUR POSITION";
        list.appendChild(divider);
        appendLeaderboardRow(list, currentItem, ranked, gameId);
      }
    }
  }

  function appendLeaderboardRow(list, item, ranked, gameId) {
    const rank = ranked.findIndex(entry => entry.player.id === item.player.id) + 1;
    const row = document.createElement("button");
    row.type = "button";
    row.className = `leaderboard-row ${currentPlayer?.id === item.player.id ? "current" : ""}`;
    const scoreLabel = gameId === "showdown" ? `${item.score.toLocaleString()} PTS` : item.score.toLocaleString();
    row.innerHTML = `<span class="rank ${rank <= 3 ? "top" : ""}">${rank}</span><span class="lb-name"></span><span class="lb-score">${scoreLabel}</span>`;
    const name = row.querySelector(".lb-name");
    name.textContent = item.player.name;
    const detail = document.createElement("small");
    detail.textContent = gameId === "showdown" ? `${item.bestScore || 0} best · ${item.attempts} ${item.attempts === 1 ? "match" : "matches"}` : `${item.attempts} ${item.attempts === 1 ? "attempt" : "attempts"}`;
    name.appendChild(detail);
    row.addEventListener("click", () => confirmReturningPlayer(item.player, gameId));
    list.appendChild(row);
  }

  function confirmReturningPlayer(player, gameId) {
    pendingPlayer = player;
    // If the player arrived here from a game's registration prompt, that game
    // wins over whichever tab the leaderboard happens to be showing.
    const intent = $("#registrationModal").dataset.intent;
    const targetGame = gameConfig[intent] ? intent : gameId;
    pendingGameId = targetGame;
    $("#confirmPlayerTitle").textContent = `Continue as ${player.name}?`;
    const gameData = player.games?.[targetGame];
    $("#confirmPlayerStats").textContent = gameData
      ? targetGame === "showdown" ? `${(gameData.lastScore ?? gameData.bestScore ?? 0).toLocaleString()} points last match · ${gameData.wins || 0} wins from ${gameData.matches || 0} matches.` : `${gameConfig[targetGame].name} personal best: ${gameData.bestScore.toLocaleString()}`
      : `No ${gameConfig[targetGame].name} score yet.`;
    openModal("confirmPlayerModal");
  }

  async function startCountdown(gameId) {
    activeGameId = gameId;
    showScreen(gameConfig[gameId].screen);
    const overlay = $("#countdownModal");
    overlay.classList.add("show");
    for (const value of [3, 2, 1]) {
      $("#countdownValue").textContent = value;
      $("#countdownValue").style.animation = "none";
      void $("#countdownValue").offsetWidth;
      $("#countdownValue").style.animation = "";
      await new Promise(resolve => setTimeout(resolve, 800));
    }
    $("#countdownValue").textContent = "GO!";
    await new Promise(resolve => setTimeout(resolve, 500));
    overlay.classList.remove("show");
    const difficulty = gameId === "world-tour" ? selectedWorldDifficulty : gameId === "trophy-rush" ? selectedRushDifficulty : gameId === "showdown" ? selectedShowdownDifficulty : selectedDifficulty;
    const options = { duration: Number(settings.duration), difficulty };
    if (gameId === "showdown") options.players = showdownPlayers;
    games[gameId].start(options);
  }

  async function finishGame(gameId, result) {
    activeGameId = gameId;
    const recorded = await storage.recordScore(currentPlayer.id, gameId, result.score, result);
    currentPlayer = recorded.player;
    const players = await storage.getAllPlayers();
    const ranked = players.sort((a, b) => (b.games?.[gameId]?.bestScore || 0) - (a.games?.[gameId]?.bestScore || 0));
    const rank = ranked.findIndex(player => player.id === currentPlayer.id) + 1;
    $("#finalScore").textContent = result.score.toLocaleString();
    $("#finalRank").textContent = `#${rank}`;
    $("#finalCombo").textContent = `×${formatMultiplier(result.bestCombo)}`;
    $("#personalBest").textContent = recorded.player.games[gameId].bestScore.toLocaleString();
    $("#resultGameLabel").textContent = gameId === "world-tour" ? `${result.completed} DESTINATIONS REACHED` : gameId === "trophy-rush" ? `${result.distance}M · ${result.collected} TROPHIES` : "FULL TIME";
    if (gameId === "world-tour") {
      $("#resultTitle").textContent = result.score >= 6000 ? "GlobalPay globetrotter!" : result.score >= 3000 ? "Journey complete!" : "Tour complete!";
    } else if (gameId === "trophy-rush") {
      $("#resultTitle").textContent = result.score >= 7000 ? "Trophy magnet!" : result.score >= 3500 ? "Glory collected!" : "Rush complete!";
    } else {
      $("#resultTitle").textContent = result.score >= 5000 ? "Golden performance!" : result.score >= 2500 ? "Great strike!" : "Full time!";
    }
    $("#improvementMessage").textContent = recorded.isPersonalBest
      ? `New personal best — improved by ${(result.score - recorded.previousBest).toLocaleString()} points!`
      : `Personal best remains ${recorded.previousBest.toLocaleString()}.`;
    openModal("resultModal");
  }

  async function finishShowdown(result) {
    activeGameId = "showdown";
    const matchResults = result.winner === null ? ["draw", "draw"] : result.winner === 0 ? ["win", "loss"] : ["loss", "win"];
    const performanceScores = result.points;
    const recordedOne = await storage.recordScore(result.players[0].id, "showdown", performanceScores[0], {
      difficulty: result.difficulty, bestCombo: result.bestCombo[0], matchResult: matchResults[0]
    });
    const recordedTwo = await storage.recordScore(result.players[1].id, "showdown", performanceScores[1], {
      difficulty: result.difficulty, bestCombo: result.bestCombo[1], matchResult: matchResults[1]
    });
    showdownPlayers = [recordedOne.player, recordedTwo.player];
    currentPlayer = recordedOne.player;
    updatePlayerUI();
    $("#showdownResultNameOne").textContent = result.players[0].name;
    $("#showdownResultNameTwo").textContent = result.players[1].name;
    $("#showdownResultGoalsOne").textContent = result.goals[0];
    $("#showdownResultGoalsTwo").textContent = result.goals[1];
    $("#showdownResultHitsOne").textContent = `${result.hits[0]} hits`;
    $("#showdownResultHitsTwo").textContent = `${result.hits[1]} hits`;
    if (result.winner === null) {
      $("#showdownResultTitle").textContent = "Honours even!";
      $("#showdownWinner").textContent = "MATCH DRAWN";
    } else {
      $("#showdownResultTitle").textContent = "What a match!";
      $("#showdownWinner").textContent = `${result.players[result.winner].name} WINS`;
    }
    openModal("showdownResultModal");
  }

  function startSpinWheel() {
    activeGameId = "spin-wheel";
    showScreen("wheelGame");
    games["spin-wheel"].start({});
  }

  async function finishWheel(result) {
    activeGameId = "spin-wheel";
    const recorded = await storage.recordScore(currentPlayer.id, "spin-wheel", result.value, { difficulty: "standard", bestCombo: 1 });
    currentPlayer = recorded.player;
    updatePlayerUI();
    const image = $("#wheelPrizeImage");
    const glyph = $("#wheelPrizeGlyph");
    if (result.won) {
      $("#wheelResultLabel").textContent = "PRIZE UNLOCKED";
      $("#wheelResultTitle").textContent = `You won a ${result.label}!`;
      $("#wheelResultCopy").textContent = "Show this screen at the prize desk to collect your merch.";
      image.src = result.icon;
      image.alt = result.label;
      image.style.display = "";
      glyph.style.display = "none";
      $("#wheelPrizeArt").classList.add("winner");
    } else {
      $("#wheelResultLabel").textContent = result.id === "almost" ? "SO CLOSE" : "NOT THIS TIME";
      $("#wheelResultTitle").textContent = result.id === "almost" ? "Almost! Inches away." : "Try again!";
      $("#wheelResultCopy").textContent = "The wheel says not yet — give it another spin and chase that merch.";
      image.style.display = "none";
      glyph.textContent = result.id === "almost" ? "◎" : "↻";
      glyph.style.display = "";
      $("#wheelPrizeArt").classList.remove("winner");
    }
    openModal("wheelResultModal");
  }

  async function finishGameWithLeaderboard(gameId, result) {
    activeGameId = gameId;
    const recorded = await storage.recordScore(currentPlayer.id, gameId, result.score, result);
    currentPlayer = recorded.player;
    updatePlayerUI();
    const title = gameId === "world-tour"
      ? result.score >= 6000 ? "GlobalPay globetrotter!" : "Journey complete!"
      : gameId === "trophy-rush"
        ? result.score >= 7000 ? "Trophy magnet!" : "Rush complete!"
        : result.score >= 5000 ? "Golden performance!" : "Great strike!";
    await showPostGameLeaderboard(gameId, {
      mode: "solo", title, players: [currentPlayer], result,
      detail: recorded.isPersonalBest
        ? `New personal best · +${(result.score - recorded.previousBest).toLocaleString()} improvement`
        : `Personal best ${recorded.player.games[gameId].bestScore.toLocaleString()}`
    });
  }

  async function finishShowdownWithLeaderboard(result) {
    activeGameId = "showdown";
    const matchResults = result.winner === null ? ["draw", "draw"] : result.winner === 0 ? ["win", "loss"] : ["loss", "win"];
    const performanceScores = result.points;
    const recordedOne = await storage.recordScore(result.players[0].id, "showdown", performanceScores[0], {
      difficulty: result.difficulty, bestCombo: result.bestCombo[0], matchResult: matchResults[0]
    });
    const recordedTwo = await storage.recordScore(result.players[1].id, "showdown", performanceScores[1], {
      difficulty: result.difficulty, bestCombo: result.bestCombo[1], matchResult: matchResults[1]
    });
    showdownPlayers = [recordedOne.player, recordedTwo.player];
    currentPlayer = recordedOne.player;
    updatePlayerUI();
    await showPostGameLeaderboard("showdown", {
      mode: "showdown",
      title: result.winner === null ? "Honours even!" : `${result.players[result.winner].name} wins!`,
      players: showdownPlayers, result, winner: result.winner
    });
  }

  async function getRankedEntries(gameId) {
    const players = await storage.getAllPlayers();
    return players.map(player => {
      const data = player.games?.[gameId] || {};
      return {
        player, data,
        score: gameId === "showdown" ? data.lastScore ?? data.bestScore ?? 0 : data.bestScore || 0,
        secondary: gameId === "showdown" ? data.wins || 0 : data.bestScore || 0
      };
    }).sort((a, b) => b.score - a.score || b.secondary - a.secondary || a.player.name.localeCompare(b.player.name));
  }

  async function showPostGameLeaderboard(gameId, summary) {
    const ranked = await getRankedEntries(gameId);
    const participantIds = new Set(summary.players.map(player => player.id));
    lastPostGame = { gameId, mode: summary.mode };
    $("#postGameLabel").textContent = summary.mode === "showdown" ? "MATCH COMPLETE" : "GAME COMPLETE";
    $("#postGameTitle").textContent = summary.title;
    $("#postBoardGame").textContent = gameConfig[gameId].name.toLocaleUpperCase();
    const summaryContainer = $("#postGameSummary");
    summaryContainer.innerHTML = "";
    const summaryCards = summary.players.map((player, index) => {
      const rank = ranked.findIndex(entry => entry.player.id === player.id) + 1;
      const data = player.games?.[gameId] || {};
      const detail = summary.mode === "showdown"
        ? `${summary.result.points[index].toLocaleString()} PTS · ${summary.result.goals[index]} goals · ${summary.result.hits[index]} hits · ${data.wins || 0} wins`
        : `${summary.result.score.toLocaleString()} points · ${summary.detail}`;
      return createPostPlayerCard(player, rank, detail, summary.mode === "showdown" && summary.winner === index);
    });
    summaryContainer.classList.toggle("showdown-summary", summary.mode === "showdown");
    if (summary.mode === "showdown") {
      const versus = document.createElement("div");
      versus.className = "post-versus";
      versus.innerHTML = `<strong>${summary.result.goals[0]}–${summary.result.goals[1]}</strong><span>VS</span>`;
      summaryContainer.append(summaryCards[0], versus, summaryCards[1]);
    } else summaryContainer.append(...summaryCards);
    const list = $("#postLeaderboardList");
    list.innerHTML = "";
    const topEntries = ranked.slice(0, 10);
    topEntries.forEach((entry, index) => appendPostLeaderboardRow(list, entry, index + 1, gameId, participantIds));
    const missing = summary.players.filter(player => !topEntries.some(entry => entry.player.id === player.id));
    if (missing.length) {
      const divider = document.createElement("div");
      divider.className = "post-position-divider";
      divider.textContent = summary.mode === "showdown" ? "PLAYER POSITIONS" : "YOUR POSITION";
      list.appendChild(divider);
      missing.forEach(player => {
        const index = ranked.findIndex(entry => entry.player.id === player.id);
        if (index >= 0) appendPostLeaderboardRow(list, ranked[index], index + 1, gameId, participantIds);
      });
    }
    $("#postContinueButton").querySelector("strong").textContent = summary.mode === "showdown" ? "Continue players" : `Continue as ${summary.players[0].name}`;
    $("#postNewPlayerButton").querySelector("strong").textContent = summary.mode === "showdown" ? "New players" : "New player";
    openModal("postGameModal");
  }

  function createPostPlayerCard(player, rank, detail, winner) {
    const card = document.createElement("div");
    card.className = `post-player-card current ${winner ? "winner" : ""}`;
    const info = document.createElement("div");
    const label = document.createElement("small");
    label.textContent = "PLAYER";
    const name = document.createElement("strong");
    name.className = "post-name";
    name.textContent = player.name;
    const detailElement = document.createElement("span");
    detailElement.className = "post-detail";
    detailElement.textContent = detail;
    info.append(label, name, detailElement);
    const rankElement = document.createElement("div");
    rankElement.className = "post-rank";
    rankElement.innerHTML = `<strong>#${rank}</strong><span>RANK</span>`;
    card.append(info, rankElement);
    return card;
  }

  function appendPostLeaderboardRow(list, entry, rank, gameId, participantIds) {
    const row = document.createElement("div");
    row.className = `leaderboard-row ${participantIds.has(entry.player.id) ? "current" : ""}`;
    const scoreLabel = gameId === "showdown" ? `${entry.score.toLocaleString()} PTS` : entry.score.toLocaleString();
    row.innerHTML = `<span class="rank ${rank <= 3 ? "top" : ""}">${rank}</span><span class="lb-name"></span><span class="lb-score">${scoreLabel}</span>`;
    row.querySelector(".lb-name").textContent = entry.player.name;
    list.appendChild(row);
  }

  function formatMultiplier(value) {
    const numeric = Number(value || 1);
    return numeric.toFixed(numeric % 1 ? 2 : 0).replace(/0$/, "");
  }

  function stopActiveGame() {
    Object.values(games).forEach(game => game.stop());
  }

  function leaveGame() {
    stopActiveGame();
    showScreen("hub");
  }

  function applySettingsToForm() {
    $("#soundSetting").checked = !!settings.sound;
    $("#durationSetting").value = String(settings.duration);
    $("#idleSetting").value = String(settings.idleSeconds);
  }

  function resetIdleTimer() {
    clearTimeout(idleTimer);
    if (currentScreen.endsWith("Game") || modals.some(modal => modal.classList.contains("show"))) return;
    idleTimer = setTimeout(() => {
      currentPlayer = null;
      updatePlayerUI();
      showScreen("attract");
    }, Number(settings.idleSeconds) * 1000);
  }

  function showToast(message) {
    const toast = $("#toast");
    toast.textContent = message;
    toast.classList.add("show");
    clearTimeout(toastTimer);
    toastTimer = setTimeout(() => toast.classList.remove("show"), 2100);
  }

  $$('[data-action="enter-arcade"]').forEach(button => button.addEventListener("click", () => showScreen("hub")));
  $$('[data-action="home"]').forEach(button => button.addEventListener("click", () => { stopActiveGame(); showScreen("hub"); }));
  $$('[data-action="go-home"]').forEach(button => button.addEventListener("click", () => {
    stopActiveGame();
    currentPlayer = null;
    updatePlayerUI();
    showScreen("attract");
  }));
  $$('[data-action="new-player"]').forEach(button => button.addEventListener("click", () => {
    currentPlayer = null;
    updatePlayerUI();
    openFreshRegistration();
  }));
  $$('[data-play="golden-goal"]').forEach(button => button.addEventListener("click", () => beginGame("golden-goal")));
  $$('[data-play="world-tour"]').forEach(button => button.addEventListener("click", () => beginGame("world-tour")));
  $$('[data-play="trophy-rush"]').forEach(button => button.addEventListener("click", () => beginGame("trophy-rush")));
  $$('[data-play="showdown"]').forEach(button => button.addEventListener("click", beginShowdown));
  $$('[data-play="spin-wheel"]').forEach(button => button.addEventListener("click", () => beginGame("spin-wheel")));
  $$('[data-action="player-menu"]').forEach(button => button.addEventListener("click", () => currentPlayer ? showToast(`Playing as ${currentPlayer.name}`) : openModal("registrationModal")));
  $$('[data-action="leaderboard"]').forEach(button => button.addEventListener("click", async () => {
    $("#registrationModal").dataset.intent = "";
    await renderLeaderboard("", activeLeaderboardGame);
    openModal("leaderboardModal");
  }));
  $$('[data-action="settings"]').forEach(button => button.addEventListener("click", () => { applySettingsToForm(); openModal("settingsModal"); }));
  $$('[data-action="open-returning"]').forEach(button => button.addEventListener("click", async () => { await renderLeaderboard("", activeLeaderboardGame); openModal("leaderboardModal"); }));
  $$('[data-action="leave-game"]').forEach(button => button.addEventListener("click", leaveGame));
  $$('[data-action="pause-game"]').forEach(button => button.addEventListener("click", () => { games[activeGameId].pause(); openModal("pauseModal"); }));
  $$('[data-action="resume-game"]').forEach(button => button.addEventListener("click", () => { closeAllModals(); games[activeGameId].resume(); }));
  $$('[data-action="result-home"]').forEach(button => button.addEventListener("click", () => showScreen("hub")));
  $$('[data-action="replay"]').forEach(button => button.addEventListener("click", () => openModal(gameConfig[activeGameId].instructions)));
  $("#postContinueButton").addEventListener("click", () => {
    if (lastPostGame?.mode === "showdown") openModal("showdownInstructionsModal");
    else showScreen("hub");
  });
  $("#postNewPlayerButton").addEventListener("click", () => {
    const wasShowdown = lastPostGame?.mode === "showdown";
    currentPlayer = null;
    updatePlayerUI();
    if (wasShowdown) {
      showdownPlayers = [];
      $("#showdownSetupForm").reset();
      $("#showdownPlayerOneInput").value = "";
      $("#showdownPlayerTwoInput").value = "";
      beginShowdown();
    } else {
      showScreen("hub");
      openFreshRegistration();
    }
  });
  $$('[data-close-modal]').forEach(button => button.addEventListener("click", closeAllModals));
  backdrop.addEventListener("click", () => {
    const lockedModalOpen = ["pauseModal", "resultModal", "showdownResultModal", "postGameModal", "wheelResultModal"]
      .some(id => document.getElementById(id)?.classList.contains("show"));
    if (!lockedModalOpen) closeAllModals();
  });
  $("#registrationForm").addEventListener("submit", registerPlayer);
  $("#showdownSetupForm").addEventListener("submit", setupShowdown);
  $("#leaderboardSearch").addEventListener("input", event => renderLeaderboard(event.target.value, activeLeaderboardGame));
  $$('[data-leaderboard-game]').forEach(button => button.addEventListener("click", () => {
    $("#leaderboardSearch").value = "";
    renderLeaderboard("", button.dataset.leaderboardGame);
  }));
  $("#confirmPlayerButton").addEventListener("click", () => {
    if (!pendingPlayer) return;
    currentPlayer = pendingPlayer;
    const gameId = pendingGameId;
    pendingPlayer = null;
    pendingGameId = null;
    $("#registrationModal").dataset.intent = "";
    updatePlayerUI();
    showToast(`Welcome back, ${currentPlayer.name}`);
    if (gameId && gameConfig[gameId]) {
      activeGameId = gameId;
      openModal(gameConfig[gameId].instructions);
    } else {
      showScreen("hub");
    }
  });
  $$('[data-difficulty]').forEach(button => button.addEventListener("click", () => {
    selectedDifficulty = button.dataset.difficulty;
    $$('[data-difficulty]').forEach(item => item.classList.toggle("selected", item === button));
  }));
  $$('[data-world-difficulty]').forEach(button => button.addEventListener("click", () => {
    selectedWorldDifficulty = button.dataset.worldDifficulty;
    $$('[data-world-difficulty]').forEach(item => item.classList.toggle("selected", item === button));
  }));
  $$('[data-rush-difficulty]').forEach(button => button.addEventListener("click", () => {
    selectedRushDifficulty = button.dataset.rushDifficulty;
    $$('[data-rush-difficulty]').forEach(item => item.classList.toggle("selected", item === button));
  }));
  $$('[data-showdown-difficulty]').forEach(button => button.addEventListener("click", () => {
    selectedShowdownDifficulty = button.dataset.showdownDifficulty;
    $$('[data-showdown-difficulty]').forEach(item => item.classList.toggle("selected", item === button));
  }));
  $("#startGoldenGoal").addEventListener("click", () => startCountdown("golden-goal"));
  $("#startWorldTour").addEventListener("click", () => startCountdown("world-tour"));
  $("#startTrophyRush").addEventListener("click", () => startCountdown("trophy-rush"));
  $("#startShowdown").addEventListener("click", () => startCountdown("showdown"));
  $("#startSpinWheel").addEventListener("click", startSpinWheel);
  $$('[data-action="wheel-spin-again"]').forEach(button => button.addEventListener("click", startSpinWheel));
  $$('[data-action="showdown-rematch"]').forEach(button => button.addEventListener("click", () => openModal("showdownInstructionsModal")));
  $("#saveSettings").addEventListener("click", () => {
    settings = storage.saveSettings({ sound: $("#soundSetting").checked, duration: Number($("#durationSetting").value), idleSeconds: Number($("#idleSetting").value) });
    closeAllModals();
    resetIdleTimer();
    showToast("Settings saved");
  });
  $("#resetDataButton").addEventListener("click", async () => {
    if (!confirm("Reset every player and score on this device? This cannot be undone.")) return;
    await storage.resetAll();
    currentPlayer = null;
    updatePlayerUI();
    closeAllModals();
    showToast("Player data reset");
  });
  $("#fullscreenButton").addEventListener("click", async () => {
    try { if (!document.fullscreenElement) await document.documentElement.requestFullscreen(); else await document.exitFullscreen(); }
    catch (_) { showToast("Fullscreen is not available in this browser"); }
  });
  ["pointerdown", "keydown"].forEach(eventName => document.addEventListener(eventName, resetIdleTimer, { passive: true }));
  window.addEventListener("beforeunload", stopActiveGame);

  applySettingsToForm();
  updatePlayerUI();
  resetIdleTimer();
})();
