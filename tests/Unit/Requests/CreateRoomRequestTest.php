<?php

namespace Tests\Unit\Requests;

use App\Http\Requests\Chat\CreateRoomRequest;
use App\Models\ChatRoom;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

class CreateRoomRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorize_returns_true()
    {
        $request = new CreateRoomRequest();
        
        $this->assertTrue($request->authorize());
    }

    public function test_rules_returns_correct_validation_rules()
    {
        $request = new CreateRoomRequest();
        $rules = $request->rules();

        $this->assertArrayHasKey('name', $rules);
        $this->assertArrayHasKey('description', $rules);
        $this->assertContains('required', explode('|', $rules['name']));
        $this->assertContains('string', explode('|', $rules['name']));
        $this->assertContains('max:255', explode('|', $rules['name']));
        $this->assertContains('unique:chat_rooms,name', explode('|', $rules['name']));
        $this->assertContains('nullable', explode('|', $rules['description']));
        $this->assertContains('string', explode('|', $rules['description']));
        $this->assertContains('max:1000', explode('|', $rules['description']));
    }

    public function test_attributes_returns_correct_attributes()
    {
        $request = new CreateRoomRequest();
        $attributes = $request->attributes();

        $this->assertEquals('Room Name', $attributes['name']);
        $this->assertEquals('Room Description', $attributes['description']);
    }

    public function test_messages_returns_custom_error_messages()
    {
        $request = new CreateRoomRequest();
        $messages = $request->messages();

        $this->assertEquals('Room name is required.', $messages['name.required']);
        $this->assertEquals('A room with this name already exists.', $messages['name.unique']);
        $this->assertEquals('Room name cannot exceed 255 characters.', $messages['name.max']);
        $this->assertEquals('Room description cannot exceed 1000 characters.', $messages['description.max']);
    }

    public function test_validation_passes_with_valid_data()
    {
        $request = new CreateRoomRequest();
        $rules = $request->rules();
        $messages = $request->messages();

        $data = [
            'name' => 'Test Room',
            'description' => 'A test room description',
        ];

        $validator = Validator::make($data, $rules, $messages);
        
        $this->assertTrue($validator->passes());
    }

    public function test_validation_fails_without_name()
    {
        $request = new CreateRoomRequest();
        $rules = $request->rules();
        $messages = $request->messages();

        $data = [
            'description' => 'A test room description',
        ];

        $validator = Validator::make($data, $rules, $messages);
        
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('name'));
    }

    public function test_validation_fails_with_duplicate_name()
    {
        ChatRoom::factory()->create(['name' => 'Existing Room']);

        $request = new CreateRoomRequest();
        $rules = $request->rules();
        $messages = $request->messages();

        $data = [
            'name' => 'Existing Room',
            'description' => 'A test room description',
        ];

        $validator = Validator::make($data, $rules, $messages);
        
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('name'));
    }

    public function test_validation_fails_with_name_too_long()
    {
        $request = new CreateRoomRequest();
        $rules = $request->rules();
        $messages = $request->messages();

        $data = [
            'name' => str_repeat('a', 256), // 256 characters
            'description' => 'A test room description',
        ];

        $validator = Validator::make($data, $rules, $messages);
        
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('name'));
    }

    public function test_validation_fails_with_description_too_long()
    {
        $request = new CreateRoomRequest();
        $rules = $request->rules();
        $messages = $request->messages();

        $data = [
            'name' => 'Test Room',
            'description' => str_repeat('a', 1001), // 1001 characters
        ];

        $validator = Validator::make($data, $rules, $messages);
        
        $this->assertTrue($validator->fails());
        $this->assertTrue($validator->errors()->has('description'));
    }

    public function test_validation_passes_with_null_description()
    {
        $request = new CreateRoomRequest();
        $rules = $request->rules();
        $messages = $request->messages();

        $data = [
            'name' => 'Test Room',
            'description' => null,
        ];

        $validator = Validator::make($data, $rules, $messages);
        
        $this->assertTrue($validator->passes());
    }

    public function test_validation_passes_without_description()
    {
        $request = new CreateRoomRequest();
        $rules = $request->rules();
        $messages = $request->messages();

        $data = [
            'name' => 'Test Room',
        ];

        $validator = Validator::make($data, $rules, $messages);
        
        $this->assertTrue($validator->passes());
    }
} 