<?php

namespace Database\Seeders;

use App\Models\Game;
use App\Models\PredictionPlayer;
use App\Models\PredictionPrompt;
use App\Models\PredictionSetting;
use App\Models\Question;
use Illuminate\Database\Seeder;

/**
 * Loads the real "Game Iko On" event data: the 37-question client-supplied trivia bank
 * (plans/trivia-questions.md) and an England vs Argentina Fan Predictions setup. Idempotent —
 * safe to run against an existing "IKOON" game; it only fills in what's missing.
 */
class EventDemoSeeder extends Seeder
{
    public function run(): void
    {
        $game = Game::firstOrCreate(
            ['code' => 'IKOON'],
            [
                'name' => 'Game Iko On – Semi-Final Watch Party',
                'event_type' => 'both',
                'status' => 'draft',
            ],
        );

        if ($game->questions()->count() === 0) {
            $this->seedTriviaQuestions($game);
        }

        $this->seedPredictionSetting($game);
        $this->seedPredictionRoster($game);
        $this->seedPredictionPrompts($game);
    }

    private function seedTriviaQuestions(Game $game): void
    {
        $order = 1;

        foreach ($this->triviaRounds() as $round => $questions) {
            foreach ($questions as [$text, $options, $correctIndex]) {
                $question = $game->questions()->create([
                    'round_name' => $round,
                    'question_text' => $text,
                    'question_type' => 'multiple_choice',
                    'duration_seconds' => 20,
                    'order_number' => $order++,
                    'is_double_points' => false,
                    'is_sudden_death' => false,
                    'requires_manual_review' => false,
                ]);

                foreach ($options as $i => $optionText) {
                    $question->options()->create([
                        'label' => chr(97 + $i),
                        'option_text' => $optionText,
                        'is_correct' => $i === $correctIndex,
                        'is_enabled' => true,
                        'order_number' => $i,
                    ]);
                }
            }
        }
    }

    /**
     * @return array<string, array<int, array{0: string, 1: string[], 2: int}>>
     *         round_name => [ [question_text, [option, option, option, option], correct_index], ... ]
     */
    private function triviaRounds(): array
    {
        return [
            'General Knowledge' => [
                ['What does NFC stand for in contactless payments?', ['Near Field Communication', 'Network Financial Connection', 'New Finance Channel', 'National Fast Connectivity'], 0],
                ['Which Kenyan city is known as the "Silicon Savannah"?', ['Mombasa', 'Kisumu', 'Nairobi', 'Nakuru'], 2],
                ['Which company owns Android?', ['Samsung', 'Microsoft', 'Google', 'Apple'], 2],
                ['Which is the largest ocean on Earth?', ['Atlantic', 'Indian', 'Arctic', 'Pacific'], 3],
                ['Which Kenyan athlete won Olympic gold in the men\'s 800 metres at both London 2012 and Rio 2016?', ['Emmanuel Wanyonyi', 'Ferguson Rotich', 'David Rudisha', 'Timothy Cheruiyot'], 2],
                ['What does VAR stand for in football?', ['Video Assistant Referee', 'Virtual Assistant Replay', 'Video Action Review', 'Verified Assistant Referee'], 0],
                ['Which payment technology allows a customer to simply tap their card or phone to pay?', ['Magnetic Stripe', 'Contactless Payments', 'SWIFT', 'RTGS'], 1],
                ['Which company sponsors the "Player of the Match" award at the FIFA World Cup?', ['Coca-Cola', 'Visa', 'Adidas', 'Hyundai'], 2],
            ],
            'FIFA World Cup' => [
                ['Which country has won the FIFA World Cup the most times?', ['Germany', 'Italy', 'Argentina', 'Brazil'], 3],
                ['Which three countries are hosting the FIFA World Cup 2026?', ['USA, Mexico & Canada', 'USA, Panama & Mexico', 'USA, Costa Rica & Canada', 'Canada, Brazil & USA'], 0],
                ['How many teams are participating in the FIFA World Cup 2026?', ['32', '40', '48', '64'], 2],
                ['Which country hosted the first FIFA World Cup in 1930?', ['Brazil', 'Uruguay', 'Italy', 'England'], 1],
                ['What is the maximum duration of extra time in a knockout match?', ['20 minutes', '25 minutes', '30 minutes', '40 minutes'], 2],
                ['If a knockout match remains tied after extra time, what determines the winner?', ['Golden Goal', 'Coin Toss', 'Penalty Shootout', 'Replay'], 2],
                ['Which African nation has won the Africa Cup of Nations (AFCON) the most times?', ['Cameroon', 'Nigeria', 'Egypt', 'Ghana'], 2],
                ['Which country has appeared in every FIFA World Cup tournament?', ['Germany', 'Argentina', 'Brazil', 'Italy'], 2],
            ],
            'Visa Questions' => [
                ['What is Visa Kenya\'s official social media handle?', ['@visakenya254', '@visa_kenya', '@visaEA', '@visaafrica'], 1],
                ['How many countries and territories worldwide accept Visa?', ['Over 100', 'Over 150', 'Over 200', 'Over 300'], 2],
                ['Where can you use your Visa card?', ['Restaurants', 'Supermarkets', 'Online stores', 'All of the above'], 3],
                ['True or False: The M-Pesa GlobalPay Virtual Visa Card allows Kenyans to spend globally using their M-Pesa balance.', ['True', 'False'], 0],
                ['What technology allows Kenyan merchants to accept card payments using only a smartphone, no separate card machine required?', ['QR Pay', 'Tap to Phone', 'Scan & Go', 'Mobile POS Lite'], 1],
                ['Nairobi serves as Visa\'s hub for which region?', ['Kenya', 'East Africa', 'Southern Africa', 'Horn of Africa'], 1],
                ['True or False: Visa is not a bank and does not issue cards directly to customers — banks and financial institutions do.', ['True', 'False'], 0],
                ['What is the name of Visa\'s real-time payments platform that allows money to be sent directly to a Visa card, often used for instant payouts?', ['Visa Direct', 'Visa Instant', 'Visa Flow', 'Visa Express'], 0],
            ],
            'M-Pesa Questions' => [
                ['In what year was M-Pesa launched in Kenya?', ['2005', '2007', '2010', '2012'], 1],
                ['Which company launched and operates M-Pesa in Kenya?', ['Airtel', 'Safaricom', 'Equity Bank', 'KCB'], 1],
                ['What does the "M" in M-Pesa stand for?', ['Money', 'Mobile', 'Micro', 'Modern'], 1],
                ['What is the name of the M-Pesa facility that allows users to transact even with insufficient balance?', ['Okoa Jahazi', 'M-Shwari', 'Fuliza', 'KCB M-Pesa'], 2],
                ['What network of physical outlets allows customers to deposit and withdraw M-Pesa cash?', ['Branches', 'Agents', 'Kiosks', 'Booths'], 1],
                ['Which product lets M-Pesa customers make global payments using a virtual Visa card?', ['M-Pesa World Card', 'Safaricom Travel Card', 'M-Pesa GlobalPay Virtual Visa Card', 'M-Pesa Elite Card'], 2],
                ['Which app do customers use to activate and manage their M-Pesa GlobalPay Virtual Visa Card?', ['M-Pesa App', 'Safaricom Neo', 'MyOne App', 'Vodafone Pay'], 2],
                ['What keeps the M-Pesa GlobalPay Virtual Visa Card secure for online transactions?', ['A fixed CVV printed on the card', 'A dynamic, regularly refreshed CVV', 'No CVV is required', 'Physical card swipe only'], 1],
            ],
            'Campaign Questions' => [
                ['What does "Game Iko On" mean in English?', ['The Match is Over', 'The Game is On', 'Football Forever', 'Play to Win'], 1],
                ['What is the exact name of the card promoted throughout the Game Iko On campaign?', ['M-Pesa Visa Card', 'Safaricom World Card', 'M-Pesa GlobalPay Virtual Visa Card', 'Visa Kenya Card'], 2],
                ['Where have Game Iko On activations taken place across Kenya this season?', ['Malls', 'Stadiums', 'Universities', 'Online only'], 0],
                ['What is the spirit behind the Game Iko On campaign this season?', ['Bringing football fans together through the M-Pesa GlobalPay Virtual Visa Card', 'Promoting a new mobile banking app', 'Launching a new radio station', 'Celebrating Kenya\'s rugby season'], 0],
                ['Which two radio stations have carried the Game Iko On campaign this season?', ['Kiss FM and Classic 105', 'Capital FM and Homeboyz Radio', 'Radio Jambo and Ghetto Radio', 'NRG Radio and Metro FM'], 1],
            ],
        ];
    }

    private function seedPredictionSetting(Game $game): void
    {
        $game->predictionSetting()->firstOrCreate([], [
            'team_a_name' => 'England',
            'team_b_name' => 'Argentina',
            'team_a_flag' => '🏴',
            'team_b_flag' => '🇦🇷',
            'allow_prediction_edits' => true,
            'winner_rule' => 'points',
        ]);
    }

    private function seedPredictionRoster(Game $game): void
    {
        if ($game->predictionPlayers()->count() > 0) {
            return;
        }

        $england = [
            ['display_name' => 'Harry Kane', 'shirt_number' => '9', 'position' => 'FW', 'squad_status' => 'starter'],
            ['display_name' => 'Jude Bellingham', 'shirt_number' => '5', 'position' => 'MF', 'squad_status' => 'starter'],
            ['display_name' => 'Phil Foden', 'shirt_number' => '20', 'position' => 'MF', 'squad_status' => 'starter'],
            ['display_name' => 'Bukayo Saka', 'shirt_number' => '7', 'position' => 'FW', 'squad_status' => 'starter'],
            ['display_name' => 'Declan Rice', 'shirt_number' => '4', 'position' => 'MF', 'squad_status' => 'starter'],
            ['display_name' => 'Kyle Walker', 'shirt_number' => '2', 'position' => 'DF', 'squad_status' => 'starter'],
            ['display_name' => 'John Stones', 'shirt_number' => '6', 'position' => 'DF', 'squad_status' => 'starter'],
            ['display_name' => 'Luke Shaw', 'shirt_number' => '3', 'position' => 'DF', 'squad_status' => 'starter'],
            ['display_name' => 'Jordan Pickford', 'shirt_number' => '1', 'position' => 'GK', 'squad_status' => 'starter'],
            ['display_name' => 'Marcus Rashford', 'shirt_number' => '11', 'position' => 'FW', 'squad_status' => 'substitute'],
            ['display_name' => 'Jack Grealish', 'shirt_number' => '10', 'position' => 'FW', 'squad_status' => 'substitute'],
        ];

        $argentina = [
            ['display_name' => 'Lionel Messi', 'shirt_number' => '10', 'position' => 'FW', 'squad_status' => 'starter'],
            ['display_name' => 'Julián Álvarez', 'shirt_number' => '9', 'position' => 'FW', 'squad_status' => 'starter'],
            ['display_name' => 'Rodrigo De Paul', 'shirt_number' => '7', 'position' => 'MF', 'squad_status' => 'starter'],
            ['display_name' => 'Enzo Fernández', 'shirt_number' => '8', 'position' => 'MF', 'squad_status' => 'starter'],
            ['display_name' => 'Alexis Mac Allister', 'shirt_number' => '20', 'position' => 'MF', 'squad_status' => 'starter'],
            ['display_name' => 'Nicolás Otamendi', 'shirt_number' => '19', 'position' => 'DF', 'squad_status' => 'starter'],
            ['display_name' => 'Cristian Romero', 'shirt_number' => '4', 'position' => 'DF', 'squad_status' => 'starter'],
            ['display_name' => 'Nahuel Molina', 'shirt_number' => '26', 'position' => 'DF', 'squad_status' => 'starter'],
            ['display_name' => 'Emiliano Martínez', 'shirt_number' => '1', 'position' => 'GK', 'squad_status' => 'starter'],
            ['display_name' => 'Ángel Di María', 'shirt_number' => '11', 'position' => 'FW', 'squad_status' => 'substitute'],
            ['display_name' => 'Lautaro Martínez', 'shirt_number' => '22', 'position' => 'FW', 'squad_status' => 'substitute'],
        ];

        $this->seedTeam($game, 'England', $england);
        $this->seedTeam($game, 'Argentina', $argentina);

        PredictionPlayer::create([
            'game_id' => $game->id,
            'team_name' => null,
            'display_name' => 'No goalscorer',
            'squad_status' => 'starter',
            'is_available' => true,
            'sort_order' => 999,
        ]);
    }

    private function seedTeam(Game $game, string $teamName, array $players): void
    {
        $order = 1;

        foreach ($players as $player) {
            PredictionPlayer::create([
                'game_id' => $game->id,
                'team_name' => $teamName,
                'display_name' => $player['display_name'],
                'shirt_number' => $player['shirt_number'],
                'position' => $player['position'],
                'squad_status' => $player['squad_status'],
                'is_available' => $player['squad_status'] !== 'unavailable',
                'sort_order' => $order++,
            ]);
        }
    }

    private function seedPredictionPrompts(Game $game): void
    {
        if ($game->predictionPrompts()->count() > 0) {
            return;
        }

        $prompts = [
            ['match_outcome', 'Who will win?', 'flag_choice', 30],
            ['final_score', 'Predict the final score', 'score_input', 45],
            ['first_goalscorer', 'Who will score first?', 'player_select', 30],
            ['player_of_match', 'Who will be Player of the Match?', 'player_select', 30],
            ['first_goal_minute', 'What minute will the first goal be scored?', 'number_input', 30],
        ];

        foreach ($prompts as $order => [$key, $label, $type, $duration]) {
            PredictionPrompt::create([
                'game_id' => $game->id,
                'prompt_key' => $key,
                'prompt_label' => $label,
                'prompt_type' => $type,
                'is_enabled' => true,
                'duration_seconds' => $duration,
                'order_number' => $order + 1,
            ]);
        }
    }
}
