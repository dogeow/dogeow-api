<?php

namespace Tests\Unit\Controllers;

use Tests\TestCase;
use App\Http\Controllers\Api\DebugController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use ReflectionClass;

class DebugControllerTest extends TestCase
{
    use RefreshDatabase;

    private DebugController $controller;

    protected function setUp(): void
    {
        parent::setUp();
        $this->controller = new DebugController();
    }

    /**
     * Test the logError method with authenticated user
     */
    public function test_log_error_method_with_authenticated_user()
    {
        $user = User::factory()->create();
        Auth::login($user);

        $request = new Request([
            'error_type' => 'test_error',
            'error_message' => 'Test error message',
            'error_details' => ['detail' => 'value'],
            'user_agent' => 'Test User Agent',
            'timestamp' => '2023-01-01T00:00:00Z',
            'url' => 'https://example.com/test',
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
        $this->assertEquals('success', $response->getData()->status);
    }

    /**
     * Test the logError method with guest user
     */
    public function test_log_error_method_with_guest_user()
    {
        Auth::forgetGuards();

        $request = new Request([
            'error_type' => 'guest_error',
            'error_message' => 'Guest user error',
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
        $this->assertEquals('success', $response->getData()->status);
    }

    /**
     * Test validation with missing required fields
     */
    public function test_log_error_validation_missing_error_type()
    {
        $request = new Request([
            'error_message' => 'Error without type',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller->logError($request);
    }

    /**
     * Test validation with missing error message
     */
    public function test_log_error_validation_missing_error_message()
    {
        $request = new Request([
            'error_type' => 'test_error',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller->logError($request);
    }

    /**
     * Test validation with long error type
     */
    public function test_log_error_validation_long_error_type()
    {
        $request = new Request([
            'error_type' => str_repeat('a', 101), // 超过100字符
            'error_message' => 'Valid error message',
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller->logError($request);
    }

    /**
     * Test validation with long error message
     */
    public function test_log_error_validation_long_error_message()
    {
        $request = new Request([
            'error_type' => 'test_error',
            'error_message' => str_repeat('a', 1001), // 超过1000字符
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller->logError($request);
    }

    /**
     * Test validation with long user agent
     */
    public function test_log_error_validation_long_user_agent()
    {
        $request = new Request([
            'error_type' => 'test_error',
            'error_message' => 'Valid error message',
            'user_agent' => str_repeat('a', 1001), // 超过1000字符
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller->logError($request);
    }

    /**
     * Test validation with long URL
     */
    public function test_log_error_validation_long_url()
    {
        $request = new Request([
            'error_type' => 'test_error',
            'error_message' => 'Valid error message',
            'url' => str_repeat('a', 1001), // 超过1000字符
        ]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $this->controller->logError($request);
    }

    /**
     * Test image upload error logging
     */
    public function test_log_error_image_upload_error()
    {
        $request = new Request([
            'error_type' => 'image_upload_error',
            'error_message' => 'Image upload failed',
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test canvas error logging
     */
    public function test_log_error_canvas_error()
    {
        $request = new Request([
            'error_type' => 'canvas_error',
            'error_message' => 'Canvas operation failed',
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test upload error logging
     */
    public function test_log_error_upload_error()
    {
        $request = new Request([
            'error_type' => 'upload_error',
            'error_message' => 'Upload failed',
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test regular error logging (not image/upload/canvas related)
     */
    public function test_log_error_regular_error()
    {
        $request = new Request([
            'error_type' => 'regular_error',
            'error_message' => 'Regular error occurred',
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with all optional fields
     */
    public function test_log_error_with_all_optional_fields()
    {
        $request = new Request([
            'error_type' => 'complete_error',
            'error_message' => 'Complete error with all fields',
            'error_details' => [
                'stack_trace' => 'Error stack trace',
                'context' => 'Error context',
            ],
            'user_agent' => 'Custom User Agent',
            'timestamp' => '2023-12-25T10:30:00Z',
            'url' => 'https://example.com/error-page',
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with null optional fields
     */
    public function test_log_error_with_null_optional_fields()
    {
        $request = new Request([
            'error_type' => 'null_fields_error',
            'error_message' => 'Error with null fields',
            'error_details' => null,
            'user_agent' => null,
            'timestamp' => null,
            'url' => null,
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with empty array error details
     */
    public function test_log_error_with_empty_array_details()
    {
        $request = new Request([
            'error_type' => 'empty_details_error',
            'error_message' => 'Error with empty details array',
            'error_details' => [],
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with complex error details
     */
    public function test_log_error_with_complex_details()
    {
        $complexDetails = [
            'stack_trace' => [
                'file' => 'app.js',
                'line' => 123,
                'function' => 'handleError'
            ],
            'context' => [
                'user_id' => 456,
                'session_id' => 'abc123',
                'browser' => 'Chrome'
            ],
            'performance' => [
                'load_time' => 2.5,
                'memory_usage' => '128MB'
            ]
        ];

        $request = new Request([
            'error_type' => 'complex_error',
            'error_message' => 'Complex error with detailed information',
            'error_details' => $complexDetails,
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with special characters
     */
    public function test_log_error_with_special_characters()
    {
        $request = new Request([
            'error_type' => 'special_chars_error',
            'error_message' => 'Error with special chars: !@#$%^&*()_+-=[]{}|;:,.<>?',
            'error_details' => [
                'special' => '!@#$%^&*()_+-=[]{}|;:,.<>?',
                'unicode' => '测试中文和特殊字符 🚀🌟💻',
                'quotes' => '"double quotes" and \'single quotes\'',
            ],
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with unicode characters
     */
    public function test_log_error_with_unicode_characters()
    {
        $request = new Request([
            'error_type' => 'unicode_error',
            'error_message' => 'Unicode error: 测试中文错误信息 🚀🌟💻',
            'error_details' => [
                'chinese' => '中文错误详情',
                'emoji' => '🚀🌟💻📱💻',
                'mixed' => 'Mixed 中文 and English 错误',
            ],
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with different image upload error types
     */
    public function test_log_error_different_image_upload_types()
    {
        $imageErrorTypes = [
            'image_upload_failed',
            'image_processing_error',
            'image_compression_failed',
            'image_format_error',
            'image_size_error',
        ];

        foreach ($imageErrorTypes as $errorType) {
            $request = new Request([
                'error_type' => $errorType,
                'error_message' => "Image upload error: {$errorType}",
            ]);

            $response = $this->controller->logError($request);

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('错误日志已记录', $response->getData()->message);
        }
    }

    /**
     * Test error logging with different canvas error types
     */
    public function test_log_error_different_canvas_types()
    {
        $canvasErrorTypes = [
            'canvas_draw_error',
            'canvas_save_error',
            'canvas_resize_error',
            'canvas_filter_error',
            'canvas_export_error',
        ];

        foreach ($canvasErrorTypes as $errorType) {
            $request = new Request([
                'error_type' => $errorType,
                'error_message' => "Canvas error: {$errorType}",
            ]);

            $response = $this->controller->logError($request);

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('错误日志已记录', $response->getData()->message);
        }
    }

    /**
     * Test error logging with different upload error types
     */
    public function test_log_error_different_upload_types()
    {
        $uploadErrorTypes = [
            'file_upload_failed',
            'file_size_exceeded',
            'file_type_not_allowed',
            'upload_timeout',
            'upload_cancelled',
        ];

        foreach ($uploadErrorTypes as $errorType) {
            $request = new Request([
                'error_type' => $errorType,
                'error_message' => "Upload error: {$errorType}",
            ]);

            $response = $this->controller->logError($request);

            $this->assertEquals(200, $response->getStatusCode());
            $this->assertEquals('错误日志已记录', $response->getData()->message);
        }
    }

    /**
     * Test error logging with malformed timestamp
     */
    public function test_log_error_malformed_timestamp()
    {
        $request = new Request([
            'error_type' => 'malformed_timestamp_error',
            'error_message' => 'Error with malformed timestamp',
            'timestamp' => 'invalid-timestamp-format',
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with future timestamp
     */
    public function test_log_error_future_timestamp()
    {
        $futureTimestamp = now()->addDays(1)->toISOString();
        $request = new Request([
            'error_type' => 'future_timestamp_error',
            'error_message' => 'Error with future timestamp',
            'timestamp' => $futureTimestamp,
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with past timestamp
     */
    public function test_log_error_past_timestamp()
    {
        $pastTimestamp = now()->subDays(1)->toISOString();
        $request = new Request([
            'error_type' => 'past_timestamp_error',
            'error_message' => 'Error with past timestamp',
            'timestamp' => $pastTimestamp,
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with invalid URL format
     */
    public function test_log_error_invalid_url_format()
    {
        $request = new Request([
            'error_type' => 'invalid_url_error',
            'error_message' => 'Error with invalid URL',
            'url' => 'not-a-valid-url',
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with very long error details
     */
    public function test_log_error_very_long_details()
    {
        $veryLongDetails = [];
        for ($i = 0; $i < 100; $i++) {
            $veryLongDetails["key_{$i}"] = str_repeat("value_{$i}_", 10);
        }

        $request = new Request([
            'error_type' => 'very_long_details_error',
            'error_message' => 'Error with very long details',
            'error_details' => $veryLongDetails,
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with nested error details
     */
    public function test_log_error_nested_details()
    {
        $nestedDetails = [
            'level1' => [
                'level2' => [
                    'level3' => [
                        'level4' => [
                            'level5' => 'deep nested value'
                        ]
                    ]
                ]
            ],
            'array' => [
                'nested' => [
                    'items' => [1, 2, 3, 4, 5]
                ]
            ]
        ];

        $request = new Request([
            'error_type' => 'nested_details_error',
            'error_message' => 'Error with nested details',
            'error_details' => $nestedDetails,
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with boolean error details
     */
    public function test_log_error_boolean_details()
    {
        $request = new Request([
            'error_type' => 'boolean_details_error',
            'error_message' => 'Error with boolean details',
            'error_details' => [
                'is_error' => true,
                'is_critical' => false,
                'has_stack_trace' => true,
            ],
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }

    /**
     * Test error logging with numeric error details
     */
    public function test_log_error_numeric_details()
    {
        $request = new Request([
            'error_type' => 'numeric_details_error',
            'error_message' => 'Error with numeric details',
            'error_details' => [
                'error_code' => 500,
                'line_number' => 123,
                'memory_usage' => 1024.5,
                'execution_time' => 0.025,
            ],
        ]);

        $response = $this->controller->logError($request);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('错误日志已记录', $response->getData()->message);
    }
} 