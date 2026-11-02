<?php

namespace App\Policies;

use App\Models\LeadAssignment;
use App\Models\User;

/**
 * Dealer CRM access: a dealer sees and changes only their own, still
 * active assignments. Staff see CRM state through the admin oversight
 * screens (stages, follow-up and appointment counts), never note text.
 */
class LeadAssignmentPolicy
{
    public function view(User $user, LeadAssignment $assignment): bool
    {
        return $user->isDealer() && $user->isActive() && $assignment->dealer_id === $user->id && $assignment->status === 'active';
    }

    public function update(User $user, LeadAssignment $assignment): bool
    {
        return $this->view($user, $assignment);
    }
}
