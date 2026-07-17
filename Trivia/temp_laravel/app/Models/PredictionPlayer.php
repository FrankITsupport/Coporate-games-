<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class PredictionPlayer extends Model
{
    use HasFactory;

    protected $fillable = [
        'game_id',
        'team_name',
        'display_name',
        'shirt_number',
        'position',
        'squad_status',
        'is_available',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_available' => 'boolean',
        ];
    }

    public function game(): BelongsTo
    {
        return $this->belongsTo(Game::class);
    }

    public function scopeAvailable($query)
    {
        return $query->where('is_available', true);
    }

    public function scopeOrdered($query)
    {
        return $query->orderBy('sort_order')->orderBy('display_name');
    }

    public function predictedAsFirstGoalscorer(): HasMany
    {
        return $this->hasMany(Prediction::class, 'predicted_first_goalscorer_id');
    }

    public function predictedAsPlayerOfMatch(): HasMany
    {
        return $this->hasMany(Prediction::class, 'predicted_player_of_match_id');
    }
}
