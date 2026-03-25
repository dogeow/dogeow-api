<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Services\Game\OfflineRewardCalculator;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class OfflineRewardCalculatorTest extends TestCase
{
    use RefreshDatabase;

    protected OfflineRewardCalculator $calculator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->calculator = new OfflineRewardCalculator;
    }

    public function test_check_returns_no_rewards_when_no_last_online(): void
    {
        $character = GameCharacter::factory()->create([
            'last_online' => null,
            'claimed_offline_at' => null,
        ]);

        $result = $this->calculator->check($character);

        $this->assertFalse($result['available']);
        $this->assertEquals(0, $result['offline_seconds']);
    }

    public function test_check_returns_no_rewards_when_offline_less_than_60_seconds(): void
    {
        $character = GameCharacter::factory()->create([
            'last_online' => Carbon::now()->subSeconds(30),
            'claimed_offline_at' => null,
        ]);

        $result = $this->calculator->check($character);

        $this->assertFalse($result['available']);
        $this->assertEquals(30, $result['offline_seconds']);
    }

    public function test_check_returns_rewards_when_offline_more_than_60_seconds(): void
    {
        config(['game.offline_rewards.max_seconds' => 86400]);
        config(['game.offline_rewards.experience_per_level' => 1]);
        config(['game.offline_rewards.copper_per_level' => 0.5]);

        $character = GameCharacter::factory()->create([
            'level' => 10,
            'experience' => 0,
            'copper' => 100,
            'last_online' => Carbon::now()->subSeconds(120),
            'claimed_offline_at' => null,
        ]);

        $result = $this->calculator->check($character);

        $this->assertTrue($result['available']);
        $this->assertEquals(120, $result['offline_seconds']);
        $this->assertGreaterThan(0, $result['experience']);
        $this->assertGreaterThan(0, $result['copper']);
    }

    public function test_check_caps_offline_seconds_at_max_config(): void
    {
        config(['game.offline_rewards.max_seconds' => 3600]);
        config(['game.offline_rewards.experience_per_level' => 1]);
        config(['game.offline_rewards.copper_per_level' => 0.5]);

        $character = GameCharacter::factory()->create([
            'level' => 5,
            'last_online' => Carbon::now()->subSeconds(7200),
            'claimed_offline_at' => null,
        ]);

        $result = $this->calculator->check($character);

        $this->assertTrue($result['available']);
        $this->assertEquals(3600, $result['offline_seconds']);
    }

    public function test_check_respects_claimed_offline_at_as_start_point(): void
    {
        config(['game.offline_rewards.max_seconds' => 86400]);
        config(['game.offline_rewards.experience_per_level' => 1]);
        config(['game.offline_rewards.copper_per_level' => 0.5]);

        $claimedTime = Carbon::now()->subSeconds(100);
        $lastOnline = Carbon::now()->subSeconds(300);

        $character = GameCharacter::factory()->create([
            'level' => 5,
            'last_online' => $lastOnline,
            'claimed_offline_at' => $claimedTime,
        ]);

        $result = $this->calculator->check($character);

        $this->assertTrue($result['available']);
        $this->assertEquals(100, $result['offline_seconds']);
    }

    public function test_claim_returns_zero_rewards_when_not_available(): void
    {
        $character = GameCharacter::factory()->create([
            'level' => 5,
            'experience' => 100,
            'copper' => 500,
            'last_online' => null,
            'claimed_offline_at' => null,
        ]);

        $result = $this->calculator->claim($character);

        $this->assertEquals(0, $result['experience']);
        $this->assertEquals(0, $result['copper']);
        $this->assertFalse($result['level_up']);
        $this->assertEquals(5, $result['new_level']);
    }

    public function test_claim_adds_experience_and_copper(): void
    {
        config(['game.offline_rewards.max_seconds' => 86400]);
        config(['game.offline_rewards.experience_per_level' => 100]);
        config(['game.offline_rewards.copper_per_level' => 50]);

        $character = GameCharacter::factory()->create([
            'level' => 1,
            'experience' => 0,
            'copper' => 0,
            'last_online' => Carbon::now()->subSeconds(120),
            'claimed_offline_at' => null,
        ]);

        $result = $this->calculator->claim($character);

        $this->assertGreaterThan(0, $result['experience']);
        $this->assertGreaterThan(0, $result['copper']);
        $this->assertEquals(1, $result['new_level']);
    }

    public function test_claim_updates_claimed_offline_at(): void
    {
        config(['game.offline_rewards.max_seconds' => 86400]);
        config(['game.offline_rewards.experience_per_level' => 100]);
        config(['game.offline_rewards.copper_per_level' => 50]);

        $character = GameCharacter::factory()->create([
            'level' => 1,
            'experience' => 0,
            'copper' => 0,
            'last_online' => Carbon::now()->subSeconds(120),
            'claimed_offline_at' => null,
        ]);

        $this->calculator->claim($character);

        $character->refresh();
        $this->assertNotNull($character->claimed_offline_at);
    }

    public function test_claim_detects_level_up(): void
    {
        config(['game.offline_rewards.max_seconds' => 86400]);
        config(['game.offline_rewards.experience_per_level' => 500]);
        config(['game.offline_rewards.copper_per_level' => 100]);

        $character = GameCharacter::factory()->create([
            'level' => 1,
            'experience' => 0,
            'copper' => 0,
            'last_online' => Carbon::now()->subSeconds(120),
            'claimed_offline_at' => null,
        ]);

        $result = $this->calculator->claim($character);

        $this->assertEquals(2, $result['new_level']);
    }
}
