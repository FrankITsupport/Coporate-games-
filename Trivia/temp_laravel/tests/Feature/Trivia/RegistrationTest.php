<?php

namespace Tests\Feature\Trivia;

use App\Models\Game;
use App\Models\Player;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_can_register_with_valid_data(): void
    {
        $game = Game::factory()->create();

        $response = $this->post(route('join.store', $game), [
            'nickname' => 'Frank',
            'phone' => '0712345678',
            'email' => 'frank@example.com',
            'consent_accepted' => '1',
        ]);

        $response->assertRedirect(route('play.show', $game));
        $this->assertDatabaseHas('players', [
            'game_id' => $game->id,
            'nickname' => 'Frank',
            'phone' => '254712345678',
        ]);
    }

    public function test_registration_requires_consent(): void
    {
        $game = Game::factory()->create();

        $response = $this->post(route('join.store', $game), [
            'nickname' => 'Frank',
            'phone' => '0712345678',
        ]);

        $response->assertSessionHasErrors('consent_accepted');
        $this->assertDatabaseMissing('players', ['game_id' => $game->id]);
    }

    public function test_registration_rejects_invalid_phone(): void
    {
        $game = Game::factory()->create();

        $response = $this->post(route('join.store', $game), [
            'nickname' => 'Frank',
            'phone' => '123',
            'consent_accepted' => '1',
        ]);

        $response->assertSessionHasErrors('phone');
    }

    public function test_registration_requires_nickname(): void
    {
        $game = Game::factory()->create();

        $response = $this->post(route('join.store', $game), [
            'phone' => '0712345678',
            'consent_accepted' => '1',
        ]);

        $response->assertSessionHasErrors('nickname');
    }

    public function test_duplicate_phone_number_reconnects_instead_of_duplicating(): void
    {
        $game = Game::factory()->create();

        $this->post(route('join.store', $game), [
            'nickname' => 'Frank',
            'phone' => '0712345678',
            'consent_accepted' => '1',
        ]);

        // Same person, different phone formatting, no prior cookie (as if on a new device).
        $response = $this->post(route('join.store', $game), [
            'nickname' => 'SomeoneElseEntirely',
            'phone' => '+254 712 345 678',
            'consent_accepted' => '1',
        ]);

        $response->assertRedirect(route('play.show', $game));
        $this->assertSame(1, Player::where('game_id', $game->id)->count());
        $this->assertSame('Frank', Player::where('game_id', $game->id)->first()->nickname);
    }

    public function test_phone_number_is_unique_per_game_at_the_database_level(): void
    {
        $game = Game::factory()->create();
        Player::factory()->for($game)->create(['phone' => '254712345678']);

        $this->expectException(\Illuminate\Database\QueryException::class);

        Player::factory()->for($game)->create(['phone' => '254712345678']);
    }

    public function test_same_phone_can_register_in_two_different_games(): void
    {
        $gameOne = Game::factory()->create();
        $gameTwo = Game::factory()->create();

        Player::factory()->for($gameOne)->create(['phone' => '254712345678']);
        $player = Player::factory()->for($gameTwo)->create(['phone' => '254712345678']);

        $this->assertNotNull($player->id);
    }
}
