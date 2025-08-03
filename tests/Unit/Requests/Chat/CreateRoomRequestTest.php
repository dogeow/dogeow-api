<?php

namespace Tests\Unit\Requests\Chat;

use Tests\TestCase;
use App\Http\Requests\Chat\CreateRoomRequest;

class CreateRoomRequestTest extends TestCase
{

    private CreateRoomRequest $request;

    protected function setUp(): void
    {
        parent::setUp();
        $this->request = new CreateRoomRequest();
    }

    public function test_authorize_returns_true()
    {
        $this->assertTrue($this->request->authorize());
    }

    public function test_validation_rules_contain_required_fields()
    {
        $rules = $this->request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('description', $rules);
    }

    public function test_name_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('required', $rules['name']);
        $this->assertStringContainsString('string', $rules['name']);
        $this->assertStringContainsString('max:255', $rules['name']);
        $this->assertStringContainsString('unique:chat_rooms,name', $rules['name']);
    }

    public function test_description_validation_rules()
    {
        $rules = $this->request->rules();

        $this->assertStringContainsString('nullable', $rules['description']);
        $this->assertStringContainsString('string', $rules['description']);
        $this->assertStringContainsString('max:1000', $rules['description']);
    }

    public function test_validation_attributes_contain_custom_attributes()
    {
        $attributes = $this->request->attributes();

        $this->assertArrayHasKey('name', $attributes);
        $this->assertArrayHasKey('description', $attributes);
        $this->assertEquals('Room Name', $attributes['name']);
        $this->assertEquals('Room Description', $attributes['description']);
    }

    public function test_validation_messages_contain_custom_messages()
    {
        $messages = $this->request->messages();

        $this->assertArrayHasKey('name.required', $messages);
        $this->assertArrayHasKey('name.unique', $messages);
        $this->assertArrayHasKey('name.max', $messages);
        $this->assertArrayHasKey('description.max', $messages);
    }

    public function test_name_required_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('Room name is required.', $messages['name.required']);
    }

    public function test_name_unique_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('A room with this name already exists.', $messages['name.unique']);
    }

    public function test_name_max_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('Room name cannot exceed 255 characters.', $messages['name.max']);
    }

    public function test_description_max_message()
    {
        $messages = $this->request->messages();

        $this->assertEquals('Room description cannot exceed 1000 characters.', $messages['description.max']);
    }
} 