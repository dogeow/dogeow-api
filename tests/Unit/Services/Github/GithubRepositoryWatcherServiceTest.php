<?php

namespace Tests\Unit\Services\Github;

use App\Services\Github\GithubRepositoryWatcherService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GithubRepositoryWatcherServiceTest extends TestCase
{
    private GithubRepositoryWatcherService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GithubRepositoryWatcherService;
    }

    #[Test]
    public function parse_github_url_extracts_owner_and_repo_from_github_com_url(): void
    {
        // Arrange
        $url = 'https://github.com/owner/repo';

        // Act
        $result = $this->service->parseGithubUrl($url);

        // Assert
        $this->assertEquals(['owner', 'repo'], $result);
    }

    #[Test]
    public function parse_github_url_extracts_owner_and_repo_from_github_com_url_with_git_suffix(): void
    {
        // Arrange
        $url = 'https://github.com/owner/repo.git';

        // Act
        $result = $this->service->parseGithubUrl($url);

        // Assert
        $this->assertEquals(['owner', 'repo'], $result);
    }

    #[Test]
    public function parse_github_url_extracts_owner_and_repo_from_www_github_com_url(): void
    {
        // Arrange
        $url = 'https://www.github.com/owner/repo';

        // Act
        $result = $this->service->parseGithubUrl($url);

        // Assert
        $this->assertEquals(['owner', 'repo'], $result);
    }

    #[Test]
    public function parse_github_url_throws_exception_for_invalid_url(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function parse_github_url_throws_exception_when_owner_repo_not_found(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function sync_from_url_creates_watched_repository(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function sync_from_url_returns_existing_watched_repository(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function refresh_updates_existing_watched_repository(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function sync_repository_fetches_latest_release_info(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function sync_repository_fetches_manifest_info(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function sync_repository_throws_exception_when_api_fails(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }
}
