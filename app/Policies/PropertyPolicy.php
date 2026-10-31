<?php

namespace App\Policies;

use App\Enums\PropertyStatus;
use App\Models\Property;
use App\Models\User;

class PropertyPolicy
{
    public function create(User $user): bool
    {
        return $user->isDealer() && $user->isActive();
    }

    public function update(User $user, Property $property): bool
    {
        return $user->isDealer()
            && $property->user_id === $user->id
            && $property->status !== PropertyStatus::Archived;
    }

    public function delete(User $user, Property $property): bool
    {
        return $user->isDealer() && $property->user_id === $user->id;
    }

    public function moderate(User $user): bool
    {
        return $user->isAdmin();
    }
}
