<?php

namespace Tests\Unit\Models\Repo;

use App\Models\Repo\RepositoryUpdate;
use App\Models\Repo\WatchedRepository;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class RepositoryUpdateTest extends TestCase
{
    #[Test]
    public function belongs_to_watched_repository(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function metadata_is_cast_to_array(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function published_at_is_cast_to_datetime(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }

    #[Test]
    public function fillable_contains_expected_fields(): void
    {
        // TODO: Implement test
        $this->markTestSkipped('TODO: Implement test');
    }
}
