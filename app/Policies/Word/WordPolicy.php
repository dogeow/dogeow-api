<?php

namespace App\Policies\Word;

use App\Models\User;
use App\Models\Word\Word;
use Illuminate\Auth\Access\HandlesAuthorization;

class WordPolicy
{
    use HandlesAuthorization;

    /**
     * Determine whether the user can view any words.
     */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can view the word.
     */
    public function view(User $user, Word $word): bool
    {
        return true;
    }

    /**
     * Determine whether the user can create words.
     */
    public function create(User $user): bool
    {
        return true;
    }

    /**
     * Determine whether the user can update the word.
     * Words are shared dictionary entries — all authenticated users can update them.
     */
    public function update(User $user, Word $word): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can delete the word.
     * Words are shared dictionary entries — only admins can delete them.
     */
    public function delete(User $user, Word $word): bool
    {
        return $user->hasRole('admin');
    }

    /**
     * Determine whether the user can review the word.
     * All authenticated users can review words.
     */
    public function review(User $user, Word $word): bool
    {
        return true;
    }

    /**
     * Determine whether the user can mark word as learned.
     * All authenticated users can mark words as learned for themselves.
     */
    public function markLearned(User $user, Word $word): bool
    {
        return true;
    }
}
