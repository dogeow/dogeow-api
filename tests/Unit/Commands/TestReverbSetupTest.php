<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\TestReverbSetup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class TestReverbSetupTest extends TestCase
{
    protected TestReverbSetup $command;

    protected function setUp(): void
    {
        parent::setUp();
        $this->command = new TestReverbSetup();
    }

    public function test_command_exists_and_has_correct_signature()
    {
        $this->artisan('test:reverb-setup')
            ->assertExitCode(1); // Should fail because Redis is not configured in tests
    }

    public function test_command_has_correct_description()
    {
        $this->assertEquals(
            'Test Laravel Reverb and WebSocket infrastructure setup',
            $this->command->getDescription()
        );
    }

    public function test_command_has_correct_signature()
    {
        $this->assertEquals(
            'test:reverb-setup',
            $this->command->getName()
        );
    }

    public function test_command_can_be_instantiated()
    {
        $this->assertInstanceOf(TestReverbSetup::class, $this->command);
    }

    public function test_command_extends_base_command()
    {
        $this->assertInstanceOf(\Illuminate\Console\Command::class, $this->command);
    }
} 