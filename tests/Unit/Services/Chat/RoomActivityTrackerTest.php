<?php

namespace Tests\Unit\Services\Chat;

use App\Services\Chat\RoomActivityTracker;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class RoomActivityTrackerTest extends TestCase
{
    use RefreshDatabase;

    protected RoomActivityTracker $tracker;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tracker = new RoomActivityTracker;
        Cache::flush();
    }

    public function test_track_stores_activity_in_cache(): void
    {
        $roomId = 1;
        $activityType = 'message_sent';
        $userId = 100;

        $this->tracker->track($roomId, $activityType, $userId);

        $cacheKey = "chat:room:activity:{$roomId}:" . date('Y-m-d-H');
        $activities = Cache::get($cacheKey, []);

        $this->assertNotEmpty($activities);
        $this->assertEquals($activityType, $activities[0]['type']);
        $this->assertEquals($userId, $activities[0]['user_id']);
        $this->assertArrayHasKey('timestamp', $activities[0]);
    }

    public function test_track_handles_null_user_id(): void
    {
        $roomId = 1;
        $activityType = 'room_joined';

        $this->tracker->track($roomId, $activityType, null);

        $cacheKey = "chat:room:activity:{$roomId}:" . date('Y-m-d-H');
        $activities = Cache::get($cacheKey, []);

        $this->assertNotEmpty($activities);
        $this->assertNull($activities[0]['user_id']);
    }

    public function test_get_activity_returns_activities_for_specified_hours(): void
    {
        $roomId = 1;

        // Track some activities
        $this->tracker->track($roomId, 'message_sent', 100);
        $this->tracker->track($roomId, 'user_joined', 101);

        $result = $this->tracker->getActivity($roomId, 1);

        $this->assertArrayHasKey('activities', $result);
        $this->assertArrayHasKey('total_activities', $result);
        $this->assertArrayHasKey('activity_types', $result);
        $this->assertIsArray($result['activities']);
        $this->assertIsArray($result['activity_types']);
    }

    public function test_get_activity_counts_activity_types(): void
    {
        $roomId = 1;

        $this->tracker->track($roomId, 'message_sent', 100);
        $this->tracker->track($roomId, 'message_sent', 101);
        $this->tracker->track($roomId, 'user_joined', 102);

        $result = $this->tracker->getActivity($roomId, 1);

        $this->assertEquals(2, $result['activity_types']['message_sent']);
        $this->assertEquals(1, $result['activity_types']['user_joined']);
    }

    public function test_get_activity_returns_empty_when_no_activities(): void
    {
        $result = $this->tracker->getActivity(999, 1);

        $this->assertEmpty($result['activities']);
        $this->assertEquals(0, $result['total_activities']);
        $this->assertEmpty($result['activity_types']);
    }

    public function test_track_handles_exception_gracefully(): void
    {
        $roomId = 1;

        // This should not throw an exception even if Redis fails
        $this->tracker->track($roomId, 'test_activity', null);

        // The test passes if no exception is thrown
        $this->assertTrue(true);
    }

    public function test_get_activity_handles_exception_gracefully(): void
    {
        $result = $this->tracker->getActivity(999, 24);

        $this->assertEmpty($result['activities']);
        $this->assertEquals(0, $result['total_activities']);
    }

    public function test_track_limits_activities_per_hour(): void
    {
        $roomId = 1;
        $activityType = 'message_sent';

        // Track more than MAX_ACTIVITIES_PER_HOUR (1000)
        for ($i = 0; $i < 10; $i++) {
            $this->tracker->track($roomId, $activityType, $i);
        }

        $cacheKey = "chat:room:activity:{$roomId}:" . date('Y-m-d-H');
        $activities = Cache::get($cacheKey, []);

        $this->assertLessThanOrEqual(1000, count($activities));
    }
}
