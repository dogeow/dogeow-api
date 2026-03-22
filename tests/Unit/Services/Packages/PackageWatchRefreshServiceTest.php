<?php

namespace Tests\Unit\Services\Packages;

use App\Services\Packages\PackageRegistryService;
use App\Services\Packages\PackageWatchRefreshService;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PackageWatchRefreshServiceTest extends TestCase
{
    private PackageWatchRefreshService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->service = new PackageWatchRefreshService(new PackageRegistryService);
    }

    #[Test]
    public function refresh_package_updates_package_with_latest_registry_info(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function refresh_package_clears_last_error_on_success(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function refresh_repository_packages_refreshes_all_packages_for_repo(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function refresh_repository_packages_returns_count_of_refreshed_packages(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function refresh_stale_packages_refreshes_packages_not_checked_recently(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function refresh_stale_packages_respects_custom_stale_hours(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function refresh_stale_packages_limits_to_200_packages(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function refresh_stale_packages_returns_zero_when_no_stale_packages(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function refresh_packages_does_nothing_for_empty_collection(): void
    {
        // TODO: Implement test - private method, test via public interface
        $this->markTestSkipped('TODO: Implement test - private method, test via public interface');
    }

    #[Test]
    public function refresh_packages_updates_all_packages_with_registry_results(): void
    {
        // TODO: Implement test - private method, test via public interface
        $this->markTestSkipped('TODO: Implement test - private method, test via public interface');
    }
}
