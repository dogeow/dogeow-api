<?php

namespace Tests\Unit\Controllers\Concerns;

use App\Http\Controllers\Concerns\CharacterConcern;
use App\Models\Game\GameCharacter;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Mockery;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class CharacterConcernTest extends TestCase
{
    use RefreshDatabase;
    use CharacterConcern;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->user = User::factory()->create();
    }

    #[Test]
    public function get_character_returns_first_character_for_user_when_no_character_id_provided(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function get_character_returns_specific_character_when_character_id_provided(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function get_character_throws_exception_when_character_not_found(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function get_character_returns_character_owned_by_requesting_user_only(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function get_character_id_returns_null_when_no_character_id_in_request(): void
    {
        // Arrange
        $request = Request::create('/test', 'GET');

        // Act
        $result = $this->getCharacterId($request);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function get_character_id_returns_integer_from_query_param(): void
    {
        // Arrange
        $request = Request::create('/test', 'GET', ['character_id' => '42']);

        // Act
        $result = $this->getCharacterId($request);

        // Assert
        $this->assertEquals(42, $result);
    }

    #[Test]
    public function get_character_id_returns_integer_from_input_param(): void
    {
        // Arrange
        $request = Request::create('/test', 'POST', ['character_id' => '99']);

        // Act
        $result = $this->getCharacterId($request);

        // Assert
        $this->assertEquals(99, $result);
    }
}
