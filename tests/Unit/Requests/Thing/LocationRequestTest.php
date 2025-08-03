<?php

namespace Tests\Unit\Requests\Thing;

use Tests\TestCase;
use App\Http\Requests\Thing\LocationRequest;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LocationRequestTest extends TestCase
{
    use RefreshDatabase;

    private LocationRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new LocationRequest();
    }

    public function test_authorize_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_validation_rules_contain_required_fields()
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('name', $rules);
    }

    public function test_name_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['name']);
        $this->assertStringContainsString('string', $rules['name']);
        $this->assertStringContainsString('max:255', $rules['name']);
    }

    public function test_messages_contain_custom_messages()
    {
        $messages = $this->request->messages();

        $this->assertArrayHasKey('name.required', $messages);
        $this->assertArrayHasKey('name.max', $messages);
        $this->assertArrayHasKey('area_id.required', $messages);
        $this->assertArrayHasKey('area_id.exists', $messages);
        $this->assertArrayHasKey('room_id.required', $messages);
        $this->assertArrayHasKey('room_id.exists', $messages);
    }

    public function test_name_required_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('名称不能为空', $messages['name.required']);
    }

    public function test_name_max_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('名称不能超过255个字符', $messages['name.max']);
    }

    public function test_area_id_required_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('区域ID不能为空', $messages['area_id.required']);
    }

    public function test_area_id_exists_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('所选区域不存在', $messages['area_id.exists']);
    }

    public function test_room_id_required_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('房间ID不能为空', $messages['room_id.required']);
    }

    public function test_room_id_exists_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('所选房间不存在', $messages['room_id.exists']);
    }
} 