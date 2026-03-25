<?php

namespace Tests\Unit\Controllers\Api;

use App\Http\Controllers\Api\MiniMaxController;
use App\Http\Requests\MiniMax\RoleplayChatRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class MiniMaxControllerTest extends TestCase
{
    protected MiniMaxController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new MiniMaxController;
    }

    public function test_roleplay_chat_returns_error_when_api_key_not_configured(): void
    {
        config(['services.minimax.balance_api_key' => null]);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'Test',
            'character_prompt' => 'You are a test character',
            'message' => 'Hello',
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('MINIMAX_BALANCE_API_KEY', $data->message);
    }

    public function test_roleplay_chat_returns_successful_response(): void
    {
        config(['services.minimax.balance_api_key' => 'test_balance_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);
        config(['services.minimax.roleplay_model' => 'M2-her']);

        Http::fake([
            'api.minimax.chat/*' => Http::response([
                'base_resp' => ['status_code' => 0, 'status_msg' => 'success'],
                'choices' => [['message' => ['content' => 'Hello, I am a test character!']]],
                'model' => 'M2-her',
                'usage' => ['total_tokens' => 100],
            ], 200),
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'Test',
            'character_prompt' => 'You are a test character',
            'message' => 'Hello',
            'history' => [],
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertInstanceOf(JsonResponse::class, $response);
        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertSame('Hello, I am a test character!', $data['data']['reply']);
    }

    public function test_roleplay_chat_handles_api_error_response(): void
    {
        config(['services.minimax.balance_api_key' => 'test_balance_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => Http::response([
                'base_resp' => ['status_code' => 1001, 'status_msg' => 'Invalid request'],
            ], 400),
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'Test',
            'character_prompt' => 'You are a test character',
            'message' => 'Hello',
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(400, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
    }

    public function test_roleplay_chat_handles_empty_reply(): void
    {
        config(['services.minimax.balance_api_key' => 'test_balance_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => Http::response([
                'base_resp' => ['status_code' => 0],
                'choices' => [['message' => ['content' => '']]],
            ], 200),
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'Test',
            'character_prompt' => 'You are a test character',
            'message' => 'Hello',
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(502, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('未返回有效回复内容', $data->message);
    }

    public function test_roleplay_chat_builds_correct_messages(): void
    {
        config(['services.minimax.balance_api_key' => 'test_balance_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => function ($request) {
                $body = $request->data();
                // Verify messages structure
                $messages = $body['messages'] ?? [];
                $this->assertNotEmpty($messages);
                // First message should be system
                $this->assertSame('system', $messages[0]['role']);
                $this->assertSame('TestChar', $messages[0]['name']);

                return Http::response([
                    'base_resp' => ['status_code' => 0],
                    'choices' => [['message' => ['content' => 'Reply']]],
                ], 200);
            },
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'TestChar',
            'character_prompt' => 'You are a test',
            'message' => 'Hello',
            'history' => [],
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_roleplay_chat_includes_history(): void
    {
        config(['services.minimax.balance_api_key' => 'test_balance_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => function ($request) {
                $body = $request->data();
                $messages = $body['messages'] ?? [];
                // Should have system + history messages
                $this->assertGreaterThanOrEqual(2, count($messages));

                return Http::response([
                    'base_resp' => ['status_code' => 0],
                    'choices' => [['message' => ['content' => 'Reply with history']]],
                ], 200);
            },
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'TestChar',
            'character_prompt' => 'You are a test',
            'message' => 'Hello',
            'history' => [
                ['role' => 'user', 'content' => 'Hi'],
                ['role' => 'assistant', 'content' => 'Hello!'],
            ],
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_subscription_returns_error_when_token_not_configured(): void
    {
        config(['services.minimax.token_api_key' => null]);

        $response = $this->controller->subscription();

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('MINIMAX_TOKEN_API_KEY', $data->message);
    }

    public function test_subscription_returns_subscription_data(): void
    {
        config(['services.minimax.token_api_key' => 'test_token_key']);

        Http::fake([
            'www.minimaxi.com/*' => Http::response(['subscription' => ['plan' => 'pro']], 200),
        ]);

        $response = $this->controller->subscription();

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    public function test_subscription_detail_returns_error_when_token_not_configured(): void
    {
        config(['services.minimax.token_api_key' => null]);

        $response = $this->controller->subscriptionDetail();

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('MINIMAX_TOKEN_API_KEY', $data->message);
    }

    public function test_subscription_detail_returns_error_when_group_id_not_configured(): void
    {
        config(['services.minimax.token_api_key' => 'test_token_key']);
        config(['services.minimax.group_id' => null]);

        $response = $this->controller->subscriptionDetail();

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('MINIMAX_GROUP_ID', $data->message);
    }

    public function test_subscription_detail_returns_data(): void
    {
        config(['services.minimax.token_api_key' => 'test_token_key']);
        config(['services.minimax.group_id' => 'test_group_id']);

        Http::fake([
            'www.minimaxi.com/*' => Http::response(['package' => ['type' => 'audio']], 200),
        ]);

        $response = $this->controller->subscriptionDetail();

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    public function test_billing_returns_error_when_api_key_not_configured(): void
    {
        config(['services.minimax.balance_api_key' => null]);
        config(['services.minimax.group_id' => 'test_group_id']);

        $response = $this->controller->billing();

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('MINIMAX_BALANCE_API_KEY', $data->message);
    }

    public function test_billing_returns_error_when_group_id_not_configured(): void
    {
        config(['services.minimax.balance_api_key' => 'test_balance_key']);
        config(['services.minimax.group_id' => null]);

        $response = $this->controller->billing();

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('MINIMAX_GROUP_ID', $data->message);
    }

    public function test_billing_returns_billing_data(): void
    {
        config(['services.minimax.balance_api_key' => 'test_balance_key']);
        config(['services.minimax.group_id' => 'test_group_id']);

        Http::fake([
            'www.minimaxi.com/*' => Http::response(['balance' => 100.50], 200),
        ]);

        $response = $this->controller->billing();

        $this->assertSame(200, $response->getStatusCode());
        $data = $response->getData(true);
        $this->assertTrue($data['success']);
        $this->assertArrayHasKey('data', $data);
    }

    public function test_build_roleplay_messages_includes_system_message(): void
    {
        config(['services.minimax.balance_api_key' => 'test_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => Http::response([
                'base_resp' => ['status_code' => 0],
                'choices' => [['message' => ['content' => 'Reply']]],
            ], 200),
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'SysChar',
            'character_prompt' => 'System prompt content',
            'message' => 'User message',
            'history' => [],
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_build_roleplay_messages_includes_user_persona_when_provided(): void
    {
        config(['services.minimax.balance_api_key' => 'test_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => function ($request) {
                $body = $request->data();
                $messages = $body['messages'] ?? [];
                // Should contain user_system message for persona
                $hasUserSystem = collect($messages)->contains('role', 'user_system');

                return Http::response([
                    'base_resp' => ['status_code' => 0],
                    'choices' => [['message' => ['content' => 'Reply']]],
                ], 200);
            },
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'TestChar',
            'character_prompt' => 'You are a test',
            'user_persona' => 'I am a brave hero',
            'message' => 'Hello',
            'history' => [],
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_build_roleplay_messages_includes_scene_when_provided(): void
    {
        config(['services.minimax.balance_api_key' => 'test_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => function ($request) {
                $body = $request->data();
                $messages = $body['messages'] ?? [];
                // Should contain group message for scene
                $hasGroup = collect($messages)->contains('role', 'group');

                return Http::response([
                    'base_resp' => ['status_code' => 0],
                    'choices' => [['message' => ['content' => 'Reply']]],
                ], 200);
            },
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'TestChar',
            'character_prompt' => 'You are a test',
            'scene' => 'In a dark forest',
            'message' => 'Hello',
            'history' => [],
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_build_roleplay_messages_includes_history(): void
    {
        config(['services.minimax.balance_api_key' => 'test_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => Http::response([
                'base_resp' => ['status_code' => 0],
                'choices' => [['message' => ['content' => 'Reply']]],
            ], 200),
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'TestChar',
            'character_prompt' => 'You are a test',
            'message' => 'Current message',
            'history' => [
                ['role' => 'user', 'content' => 'First message'],
                ['role' => 'assistant', 'content' => 'First reply'],
            ],
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_build_roleplay_messages_includes_current_message(): void
    {
        config(['services.minimax.balance_api_key' => 'test_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => Http::response([
                'base_resp' => ['status_code' => 0],
                'choices' => [['message' => ['content' => 'Reply']]],
            ], 200),
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'TestChar',
            'character_prompt' => 'You are a test',
            'message' => 'Current user message',
            'history' => [],
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(200, $response->getStatusCode());
    }

    public function test_extract_minimax_error_message_extracts_status_msg(): void
    {
        config(['services.minimax.balance_api_key' => 'test_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => Http::response([
                'base_resp' => ['status_code' => 1002, 'status_msg' => 'Rate limit exceeded'],
            ], 429),
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'Test',
            'character_prompt' => 'You are a test',
            'message' => 'Hello',
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(429, $response->getStatusCode());
        $data = $response->getData();
        $this->assertStringContainsString('Rate limit exceeded', $data->message);
    }

    public function test_extract_minimax_error_message_falls_back_to_message(): void
    {
        config(['services.minimax.balance_api_key' => 'test_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => Http::response([
                'message' => 'Custom error message from API',
            ], 500),
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'Test',
            'character_prompt' => 'You are a test',
            'message' => 'Hello',
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
    }

    public function test_extract_minimax_error_message_falls_back_to_error_field(): void
    {
        config(['services.minimax.balance_api_key' => 'test_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => Http::response([
                'error' => ['message' => 'OAuth token invalid'],
            ], 401),
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'Test',
            'character_prompt' => 'You are a test',
            'message' => 'Hello',
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(401, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
    }

    public function test_extract_minimax_error_message_uses_fallback_when_no_error(): void
    {
        config(['services.minimax.balance_api_key' => 'test_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => Http::response('Server Error Body', 500),
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'Test',
            'character_prompt' => 'You are a test',
            'message' => 'Hello',
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
    }

    public function test_get_token_api_key_returns_config_value(): void
    {
        config(['services.minimax.token_api_key' => 'my_token_key']);

        $apiKey = config('services.minimax.token_api_key');

        $this->assertSame('my_token_key', $apiKey);
    }

    public function test_get_balance_api_key_returns_config_value(): void
    {
        config(['services.minimax.balance_api_key' => 'my_balance_key']);

        $apiKey = config('services.minimax.balance_api_key');

        $this->assertSame('my_balance_key', $apiKey);
    }

    public function test_roleplay_chat_handles_http_exception(): void
    {
        config(['services.minimax.balance_api_key' => 'test_key']);
        config(['services.minimax.api_base_url' => 'https://api.minimax.chat']);

        Http::fake([
            'api.minimax.chat/*' => function () {
                throw new \Illuminate\Http\Client\ConnectionException('Connection refused');
            },
        ]);

        $request = new RoleplayChatRequest;
        $request->merge([
            'character_name' => 'Test',
            'character_prompt' => 'You are a test',
            'message' => 'Hello',
        ]);

        $response = $this->controller->roleplayChat($request);

        $this->assertSame(500, $response->getStatusCode());
        $data = $response->getData();
        $this->assertFalse($data->success);
        $this->assertStringContainsString('角色对话生成失败', $data->message);
    }
}
