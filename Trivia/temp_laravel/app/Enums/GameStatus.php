<?php

namespace App\Enums;

enum GameStatus: string
{
    case Draft = 'draft';
    case RegistrationOpen = 'registration_open';
    case Waiting = 'waiting';
    case QuestionLive = 'question_live';
    case QuestionClosed = 'question_closed';
    case AnswerReveal = 'answer_reveal';
    case Leaderboard = 'leaderboard';
    case Podium = 'podium';
    case Paused = 'paused';
    case Cancelled = 'cancelled';
    case Ended = 'ended';
}
