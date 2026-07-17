<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PredictionPrompt extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'prompt_key',
        'prompt_label',
        'prompt_type',
        'is_enabled',
        'duration_seconds',
        'order_number',
        'started_at',
        'closed_at',
    ];

    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'started_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function predictionAnswers(): HasMany
    {
        return $this->hasMany(PredictionAnswer::class);
    }

    public function scopeEnabled($query)
    {
        return $query->where('is_enabled', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('order_number')->orderBy('id');
    }

    /**
     * The active-prompt payload shape from plans/design.md, shared by the
     * PredictionPromptStarted broadcast and PredictionController's initial page
     * load. For player_select prompts we include the admin-managed roster so the
     * phone can render the selector without a second round-trip.
     */
    public function toActivePromptPayload(?PredictionSetting $setting = null): array
    {
        $closesAt = $this->started_at->clone()->addSeconds($this->duration_seconds ?? 0);

        $payload = [
            'prompt_key'  => $this->prompt_key,
            'prompt_label'=> $this->prompt_label,
            'prompt_type' => $this->prompt_type,
        ];

        if ($setting) {
            $payload['team_a_name'] = $setting->team_a_name;
            $payload['team_b_name'] = $setting->team_b_name;
            $payload['team_a_flag'] = $setting->team_a_flag;
            $payload['team_b_flag'] = $setting->team_b_flag;
        }

        if ($this->prompt_type === 'player_select') {
            $payload['roster_options'] = $this->game->predictionPlayers()
                ->available()
                ->ordered()
                ->get(['id', 'display_name', 'team_name', 'shirt_number', 'squad_status'])
                ->toArray();
        }

        return [
            'prompt_mode'     => 'prediction',
            'prompt_id'       => $this->id,
            'duration_seconds'=> $this->duration_seconds,
            'started_at'      => $this->started_at->toIso8601String(),
            'closes_at'       => $closesAt->toIso8601String(),
            'payload'         => $payload,
        ];
    }
}
