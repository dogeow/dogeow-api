<?php

namespace Tests\Feature;

use App\Models\Cloud\File;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class CloudPrivateMigrationTest extends TestCase
{
    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        Storage::fake('cloud');
        $this->user = User::factory()->create();
        $this->actingAs($this->user);
    }

    private function legacyFile(array $overrides = []): File
    {
        $file = File::factory()->create(array_merge([
            'user_id' => $this->user->id, 'is_folder' => false, 'path' => 'cloud/legacy.txt',
            'original_name' => 'legacy.txt', 'extension' => 'txt', 'mime_type' => 'text/plain',
        ], $overrides));
        Storage::disk('public')->put($file->path, 'private content');

        return $file;
    }

    public function test_default_is_read_only_dry_run(): void
    {
        $file = $this->legacyFile();
        $this->artisan('cloud:migrate-private')->assertSuccessful();
        Storage::disk('public')->assertExists($file->path);
        Storage::disk('cloud')->assertMissing($file->path);
    }

    public function test_apply_verifies_private_copy_then_removes_public_file_and_is_repeatable(): void
    {
        $file = $this->legacyFile();
        $this->artisan('cloud:migrate-private', ['--apply' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing($file->path);
        $this->assertSame('private content', Storage::disk('cloud')->get($file->path));
        $this->assertSame($file->path, $file->fresh()->path);
        $this->assertSame([], Storage::disk('cloud')->files('.migration'));
        $this->artisan('cloud:migrate-private', ['--apply' => true])->assertSuccessful();
        $this->get('/api/cloud/files/' . $file->id . '/download')->assertOk()->assertDownload('legacy.txt');
    }

    public function test_conflicting_private_copy_is_never_overwritten(): void
    {
        $file = $this->legacyFile();
        Storage::disk('cloud')->put($file->path, 'different private content');
        $this->artisan('cloud:migrate-private', ['--apply' => true])->assertFailed();
        $this->assertSame('private content', Storage::disk('public')->get($file->path));
        $this->assertSame('different private content', Storage::disk('cloud')->get($file->path));
    }

    public function test_existing_identical_private_copy_can_complete_an_interrupted_migration(): void
    {
        $file = $this->legacyFile();
        Storage::disk('cloud')->put($file->path, 'private content');
        $this->artisan('cloud:migrate-private', ['--apply' => true])->assertSuccessful();
        Storage::disk('public')->assertMissing($file->path);
    }

    public function test_user_filter_leaves_other_users_files_untouched(): void
    {
        $owned = $this->legacyFile();
        $other = $this->legacyFile(['user_id' => User::factory()->create()->id, 'path' => 'cloud/other.txt']);
        $this->artisan('cloud:migrate-private', ['--apply' => true, '--user' => $this->user->id])->assertSuccessful();
        Storage::disk('public')->assertMissing($owned->path);
        Storage::disk('public')->assertExists($other->path);
    }

    public function test_invalid_user_and_missing_files_fail_explicitly(): void
    {
        $this->artisan('cloud:migrate-private', ['--user' => 'not-an-id'])->assertFailed();
        File::factory()->create(['user_id' => $this->user->id, 'is_folder' => false, 'path' => 'cloud/missing.txt']);
        $this->artisan('cloud:migrate-private', ['--apply' => true])->assertFailed();
    }

    public function test_invalid_path_is_rejected_without_touching_unrelated_files(): void
    {
        File::factory()->create(['user_id' => $this->user->id, 'is_folder' => false, 'path' => '../outside.txt']);
        Storage::disk('public')->put('unrelated.txt', 'keep');
        $this->artisan('cloud:migrate-private', ['--apply' => true])->assertFailed();
        $this->assertSame('keep', Storage::disk('public')->get('unrelated.txt'));
        $this->assertSame([], Storage::disk('cloud')->allFiles());
    }

    public function test_failed_copy_preserves_the_public_original(): void
    {
        $file = $this->legacyFile();
        $public = Storage::disk('public');
        $private = \Mockery::mock(Filesystem::class);
        $private->shouldReceive('exists')->andReturn(false);
        $private->shouldReceive('writeStream')->once()->andReturn(false);
        $private->shouldReceive('delete')->andReturn(true);
        Storage::shouldReceive('disk')->with('public')->andReturn($public);
        Storage::shouldReceive('disk')->with('cloud')->andReturn($private);
        $this->artisan('cloud:migrate-private', ['--apply' => true])->assertFailed();
        $this->assertSame('private content', $public->get($file->path));
    }

    public function test_private_file_created_during_copy_is_not_overwritten(): void
    {
        $file = $this->legacyFile();
        $public = Storage::disk('public');
        $private = Storage::disk('cloud');
        $observed = \Mockery::mock(Filesystem::class);
        $observed->shouldReceive('exists')->andReturnUsing(fn (string $path) => $private->exists($path));
        $observed->shouldReceive('readStream')->andReturnUsing(fn (string $path) => $private->readStream($path));
        $observed->shouldReceive('size')->andReturnUsing(fn (string $path) => $private->size($path));
        $observed->shouldReceive('delete')->andReturnUsing(fn ($path) => $private->delete($path));
        $observed->shouldNotReceive('move');
        $observed->shouldReceive('writeStream')->once()->andReturnUsing(function (string $path, $stream) use ($private, $file): bool {
            $written = $private->writeStream($path, $stream);
            $private->put($file->path, 'created during copy');

            return $written;
        });
        Storage::shouldReceive('disk')->with('public')->andReturn($public);
        Storage::shouldReceive('disk')->with('cloud')->andReturn($observed);

        $this->artisan('cloud:migrate-private', ['--apply' => true])->assertFailed();

        $this->assertSame('private content', $public->get($file->path));
        $this->assertSame('created during copy', $private->get($file->path));
        $this->assertSame([], $private->files('.migration'));
    }

    public function test_incomplete_hash_read_preserves_the_original(): void
    {
        $file = $this->legacyFile();
        $public = Storage::disk('public');
        $private = Storage::disk('cloud');
        $truncated = \Mockery::mock(Filesystem::class);
        $truncated->shouldReceive('exists')->with($file->path)->andReturn(true);
        $truncated->shouldReceive('size')->with($file->path)->andReturn($public->size($file->path));
        $truncated->shouldReceive('readStream')->with($file->path)->andReturnUsing(function () {
            $stream = fopen('php://temp', 'r+');
            fwrite($stream, 'partial');
            rewind($stream);

            return $stream;
        });
        $truncated->shouldNotReceive('delete');
        Storage::shouldReceive('disk')->with('public')->andReturn($truncated);
        Storage::shouldReceive('disk')->with('cloud')->andReturn($private);

        $this->artisan('cloud:migrate-private', ['--apply' => true])->assertFailed();

        $this->assertSame('private content', $public->get($file->path));
        $this->assertSame([], $private->allFiles());
    }
}
