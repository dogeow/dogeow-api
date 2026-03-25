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

    private function createWord(): Word
    {
        // Words are shared dictionary entries without an owner
        return new Word;
    }

    public function test_view_any_returns_true(): void
    {
        $user = $this->createUser(1);
        $this->assertTrue($this->policy->viewAny($user));
    }

    public function test_view_returns_true(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord();

        $this->assertTrue($this->policy->view($user, $word));
    }

    public function test_create_returns_true(): void
    {
        $user = $this->createUser(1);
        $this->assertTrue($this->policy->create($user));
    }

    public function test_update_returns_false_for_regular_user(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord();

        // Only admins can update shared dictionary words
        $this->assertFalse($this->policy->update($user, $word));
    }

    public function test_update_returns_true_for_admin(): void
    {
        $admin = $this->createUser(1, true);
        $word = $this->createWord();

        $this->assertTrue($this->policy->update($admin, $word));
    }

    public function test_delete_returns_false_for_regular_user(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord();

        // Only admins can delete shared dictionary words
        $this->assertFalse($this->policy->delete($user, $word));
    }

    public function test_delete_returns_true_for_admin(): void
    {
        $admin = $this->createUser(1, true);
        $word = $this->createWord();

        $this->assertTrue($this->policy->delete($admin, $word));
    }

    public function test_review_returns_true_for_any_authenticated_user(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord();

        // All authenticated users can review words
        $this->assertTrue($this->policy->review($user, $word));
    }

    public function test_mark_learned_returns_true_for_any_authenticated_user(): void
    {
        $user = $this->createUser(1);
        $word = $this->createWord();

        // All authenticated users can mark words as learned for themselves
        $this->assertTrue($this->policy->markLearned($user, $word));
    }
}
