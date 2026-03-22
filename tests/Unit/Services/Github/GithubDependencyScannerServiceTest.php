<?php

namespace Tests\Unit\Services\Github;

use App\Services\Github\GithubDependencyScannerService;
use App\Services\Github\GithubRepositoryWatcherService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class GithubDependencyScannerServiceTest extends TestCase
{
    private GithubDependencyScannerService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new GithubDependencyScannerService(new GithubRepositoryWatcherService);
    }

    #[Test]
    public function preview_dependencies_returns_structure_with_source_and_manifests(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function preview_dependencies_extracts_npm_dependencies_from_package_json(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function preview_dependencies_extracts_composer_dependencies_from_composer_json(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function preview_dependencies_skips_php_in_composer_dependencies(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function preview_dependencies_throws_exception_when_no_manifests_found(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function preview_dependencies_throws_exception_when_github_api_fails(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function normalize_version_extracts_semver_from_constraint(): void
    {
        // Arrange
        $constraint = '^1.2.3';

        // Act
        $result = $this->service->normalizeVersion($constraint);

        // Assert
        $this->assertEquals('1.2.3', $result);
    }

    #[Test]
    public function normalize_version_handles_minor_version_constraint(): void
    {
        // Arrange
        $constraint = '^1.2';

        // Act
        $result = $this->service->normalizeVersion($constraint);

        // Assert
        $this->assertEquals('1.2.0', $result);
    }

    #[Test]
    public function normalize_version_handles_major_only_constraint(): void
    {
        // Arrange
        $constraint = '^5';

        // Act
        $result = $this->service->normalizeVersion($constraint);

        // Assert
        $this->assertEquals('5.0.0', $result);
    }

    #[Test]
    public function normalize_version_returns_null_for_invalid_constraint(): void
    {
        // Arrange
        $constraint = 'invalid';

        // Act
        $result = $this->service->normalizeVersion($constraint);

        // Assert
        $this->assertNull($result);
    }

    #[Test]
    public function normalize_version_returns_null_for_empty_constraint(): void
    {
        // Act
        $result = $this->service->normalizeVersion(null);

        // Assert
        $this->assertNull($result);
    }
}
