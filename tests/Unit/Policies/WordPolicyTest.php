<?php

namespace Tests\Unit\Policies;

use App\Models\User;
use App\Models\Word\Word;
use App\Policies\Word\WordPolicy;
use Tests\TestCase;

class WordPolicyTest extends TestCase
{
    private WordPolicy $policy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->policy = new WordPolicy;
    }

    private function createUser(int $id, bool $isAdmin = false): User
    {
        $user = new User;
        $user->id = $id;
        $user->is_admin = $isAdmin;

        return $user;
    }

    private function createWord(int $userId): Word
    {
        $word = new Word;
        $word->user_id = $userId;

        return $word;
    }

    public function test_view_any_returns_true(): void
    {
        $user = $this->createUser(1);
        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_view_returns_true(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord(999);

        $this->assertTrue($this->policy->view($user, $word));
    }

    public function test_create_returns_true(): void
    {
        $user = $this->createUser(1);
        $this->assertTrue($this->policy->create($user));
    }

    public function test_update_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord(1);

        $this->assertTrue($this->policy->update($user, $word));
    }

    public function test_update_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord(999);

        $this->assertFalse($this->policy->update($user, $word));
    }

    public function test_delete_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord(1);

        $this->assertTrue($this->policy->delete($user, $word));
    }

    public function test_delete_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord(999);

        $this->assertFalse($this->policy->delete($user, $word));
    }

    public function test_review_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord(1);

        $this->assertTrue($this->policy->review($user, $word));
    }

    public function test_review_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord(999);

        $this->assertFalse($this->policy->review($user, $word));
    }

    public function test_mark_learned_returns_true_for_owner(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord(1);

        $this->assertTrue($this->policy->markLearned($user, $word));
    }

    public function test_mark_learned_returns_false_for_non_owner(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord(999);

        $this->assertFalse($this->policy->markLearned($user, $word));
    }

    public function test_update_returns_true_for_admin(): void
    {
        $admin = $this->createUser(1, true);
        $word = $this->createWord(999);

        $this->assertTrue($this->policy->update($admin, $word));
    }

    public function test_delete_returns_true_for_admin(): void
    {
        $admin = $this->createUser(1, true);
        $word = $this->createWord(999);

        $this->assertTrue($this->policy->delete($admin, $word));
    }

    public function test_review_returns_true_for_admin(): void
    {
        $admin = $this->createUser(1, true);
        $word = $this->createWord(999);

        $this->assertTrue($this->policy->review($admin, $word));
    }

    public function test_mark_learned_returns_true_for_admin(): void
    {
        $admin = $this->createUser(1, true);
        $word = $this->createWord(999);

        $this->assertTrue($this->policy->markLearned($admin, $word));
    }
}
