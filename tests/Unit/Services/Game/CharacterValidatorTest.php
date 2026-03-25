<?php

namespace Tests\Unit\Services\Game;

use App\Models\Game\GameCharacter;
use App\Services\Game\CharacterValidator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterValidatorTest extends TestCase
{
    use RefreshDatabase;

    protected CharacterValidator $validator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validator = new CharacterValidator;
    }

    public function test_validate_name_throws_exception_for_name_too_short(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('角色名至少需要 2 个字符');

        $this->validator->validateName('a');
    }

    public function test_validate_name_throws_exception_for_name_too_long(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('角色名最多 12 个字符');

        $this->validator->validateName('十二个字符的名称呀');
    }

    public function test_validate_name_throws_exception_for_invalid_characters(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('角色名只能包含中文、英文和数字');

        $this->validator->validateName('name@something');
    }

    public function test_validate_name_passes_for_valid_chinese_name(): void
    {
        $result = $this->validator->validateName('测试角色');

        $this->assertNull($result);
    }

    public function test_validate_name_passes_for_valid_english_name(): void
    {
        $result = $this->validator->validateName('TestChar');

        $this->assertNull($result);
    }

    public function test_validate_name_passes_for_valid_alphanumeric_name(): void
    {
        $result = $this->validator->validateName('角色123');

        $this->assertNull($result);
    }

    public function test_is_name_taken_returns_false_for_unused_name(): void
    {
        $result = $this->validator->isNameTaken('UnusedName123');

        $this->assertFalse($result);
    }

    public function test_is_name_taken_returns_true_for_existing_name(): void
    {
        $character = GameCharacter::factory()->create(['name' => 'ExistingChar']);

        $result = $this->validator->isNameTaken('ExistingChar');

        $this->assertTrue($result);
    }

    public function test_validate_name_not_taken_throws_exception_for_taken_name(): void
    {
        GameCharacter::factory()->create(['name' => 'TakenName']);

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('角色名已被使用');

        $this->validator->validateNameNotTaken('TakenName');
    }

    public function test_validate_name_not_taken_passes_for_available_name(): void
    {
        $result = $this->validator->validateNameNotTaken('AvailableName');

        $this->assertNull($result);
    }

    public function test_get_class_base_stats_returns_default_stats(): void
    {
        $stats = $this->validator->getClassBaseStats('warrior');

        $this->assertIsArray($stats);
        $this->assertArrayHasKey('strength', $stats);
        $this->assertArrayHasKey('dexterity', $stats);
        $this->assertArrayHasKey('vitality', $stats);
        $this->assertArrayHasKey('energy', $stats);
    }

    public function test_get_class_base_stats_returns_config_values_when_available(): void
    {
        config(['game.class_base_stats.mage' => [
            'strength' => 1,
            'dexterity' => 2,
            'vitality' => 3,
            'energy' => 10,
        ]]);

        $stats = $this->validator->getClassBaseStats('mage');

        $this->assertEquals(1, $stats['strength']);
        $this->assertEquals(10, $stats['energy']);
    }

    public function test_get_starting_copper_returns_zero_by_default(): void
    {
        $copper = $this->validator->getStartingCopper('unknown_class');

        $this->assertEquals(0, $copper);
    }

    public function test_get_starting_copper_returns_config_value_when_available(): void
    {
        config(['game.starting_copper.warrior' => 1000]);

        $copper = $this->validator->getStartingCopper('warrior');

        $this->assertEquals(1000, $copper);
    }
}
