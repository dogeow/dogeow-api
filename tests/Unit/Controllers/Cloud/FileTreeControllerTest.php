<?php

namespace Tests\Unit\Controllers\Cloud;

use App\Http\Controllers\Api\Cloud\FileTreeController;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Test stubs for FileTreeController
 *
 * @group cloud
 * @group stubs
 */
class FileTreeControllerTest extends TestCase
{
    use RefreshDatabase;

    protected FileTreeController $controller;

    protected function setUp(): void
    {
        parent::setUp();

        $this->controller = new FileTreeController;
    }

    /**
     * @test
     */
    public function index_returns_file_tree(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }

    /**
     * @test
     */
    public function file_tree_requires_authentication(): void
    {
        $this->markTestIncomplete('Test stub - implementation needed');
    }
}
