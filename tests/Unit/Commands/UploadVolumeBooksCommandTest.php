<?php

namespace Tests\Unit\Commands;

use App\Console\Commands\UploadVolumeBooksCommand;
use App\Services\UpyunService;
use Illuminate\Console\OutputStyle;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\NullOutput;
use Tests\TestCase;

class UploadVolumeBooksCommandTest extends TestCase
{
    private string $sourceDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->sourceDir = sys_get_temp_dir() . '/volume_upload_' . uniqid();
        mkdir($this->sourceDir, 0777, true);
        mkdir($this->sourceDir . '/part-01', 0777, true);
        file_put_contents($this->sourceDir . '/index.json', '{"title":"边城"}');
        file_put_contents($this->sourceDir . '/part-01/chapter-01.txt', "由四川过湖南去。\n");
    }

    protected function tearDown(): void
    {
        $this->deleteDirectory($this->sourceDir);
        parent::tearDown();
    }

    /**
     * @param  array<string, string>  $input
     */
    private function runCommand(UploadVolumeBooksCommand $command, array $input): int
    {
        $symfonyInput = new ArrayInput($input);
        $symfonyInput->bind($command->getDefinition());
        $command->setInput($symfonyInput);
        $command->setOutput(new OutputStyle($symfonyInput, new NullOutput));

        return $command->handle($this->app->make(UpyunService::class));
    }

    public function test_returns_failure_when_id_is_invalid(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $upyun->shouldNotReceive('upload');
        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UploadVolumeBooksCommand::class);
        $exitCode = $this->runCommand($command, ['id' => '../etc']);

        $this->assertSame(UploadVolumeBooksCommand::FAILURE, $exitCode);
    }

    public function test_returns_failure_when_upyun_not_configured(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(false);
        $upyun->shouldNotReceive('upload');
        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UploadVolumeBooksCommand::class);
        $exitCode = $this->runCommand($command, ['id' => 'biancheng', '--source' => $this->sourceDir]);

        $this->assertSame(UploadVolumeBooksCommand::FAILURE, $exitCode);
    }

    public function test_returns_failure_when_source_directory_missing(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UploadVolumeBooksCommand::class);
        $exitCode = $this->runCommand($command, [
            'id' => 'biancheng',
            '--source' => $this->sourceDir . '/missing',
        ]);

        $this->assertSame(UploadVolumeBooksCommand::FAILURE, $exitCode);
    }

    public function test_uploads_index_and_chapter_files_under_prefix(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $sourceDir = realpath($this->sourceDir) ?: $this->sourceDir;
        $upyun->shouldReceive('upload')
            ->once()
            ->with($sourceDir . '/index.json', 'books/biancheng/index.json', 'application/json')
            ->andReturn(['success' => true, 'path' => 'books/biancheng/index.json']);
        $upyun->shouldReceive('upload')
            ->once()
            ->with($sourceDir . '/part-01/chapter-01.txt', 'books/biancheng/part-01/chapter-01.txt', 'text/plain; charset=utf-8')
            ->andReturn(['success' => true, 'path' => 'books/biancheng/part-01/chapter-01.txt']);
        $upyun->shouldReceive('buildPublicUrl')
            ->once()
            ->with('/books/biancheng/index.json')
            ->andReturn('https://upyun.dogeow.com/books/biancheng/index.json');
        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UploadVolumeBooksCommand::class);
        $exitCode = $this->runCommand($command, ['id' => 'biancheng', '--source' => $this->sourceDir]);

        $this->assertSame(UploadVolumeBooksCommand::SUCCESS, $exitCode);
    }

    public function test_returns_failure_when_any_upload_fails(): void
    {
        $upyun = $this->mock(UpyunService::class);
        $upyun->shouldReceive('isConfigured')->once()->andReturn(true);
        $upyun->shouldReceive('upload')->once()->andReturn(['success' => false, 'message' => '上传失败']);
        $this->app->instance(UpyunService::class, $upyun);

        $command = $this->app->make(UploadVolumeBooksCommand::class);
        $exitCode = $this->runCommand($command, ['id' => 'biancheng', '--source' => $this->sourceDir]);

        $this->assertSame(UploadVolumeBooksCommand::FAILURE, $exitCode);
    }

    private function deleteDirectory(string $dir): void
    {
        if (! is_dir($dir)) {
            return;
        }

        $items = scandir($dir);
        if ($items === false) {
            return;
        }

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $path = $dir . DIRECTORY_SEPARATOR . $item;
            if (is_dir($path)) {
                $this->deleteDirectory($path);
            } else {
                unlink($path);
            }
        }

        rmdir($dir);
    }
}
