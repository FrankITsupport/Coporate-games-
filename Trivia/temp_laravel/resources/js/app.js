import '../css/app.css';
import './echo';

import Alpine from 'alpinejs';
import { createApp } from 'vue';
import PlayerGame from './components/PlayerGame.vue';
import StageScreen from './components/StageScreen.vue';
import PredictionForm from './components/PredictionForm.vue';

window.Alpine = Alpine;
Alpine.start();

// Mount the player live game screen
const playerGameEl = document.getElementById('player-game');
if (playerGameEl) {
    const initialState = JSON.parse(playerGameEl.dataset.initial || '{}');
    const gameCode     = playerGameEl.dataset.gameCode;
    const answerUrlTpl = playerGameEl.dataset.questionAnswerUrlTemplate;
    const csrf         = playerGameEl.dataset.csrf;

    createApp(PlayerGame, { initialState, gameCode, answerUrlTpl, csrf }).mount(playerGameEl);
}

// Mount the stage screen
const stageScreenEl = document.getElementById('stage-screen');
if (stageScreenEl) {
    const initialState = JSON.parse(stageScreenEl.dataset.initial || '{}');
    const gameCode     = stageScreenEl.dataset.gameCode;

    createApp(StageScreen, { initialState, gameCode }).mount(stageScreenEl);
}

// Mount the player prediction screen
const predictionFormEl = document.getElementById('prediction-form');
if (predictionFormEl) {
    const initialState = JSON.parse(predictionFormEl.dataset.initial || '{}');
    const gameCode     = predictionFormEl.dataset.gameCode;
    const predictUrlTpl = predictionFormEl.dataset.predictUrlTemplate;
    const csrf         = predictionFormEl.dataset.csrf;

    createApp(PredictionForm, { initialState, gameCode, predictUrlTpl, csrf }).mount(predictionFormEl);
}
