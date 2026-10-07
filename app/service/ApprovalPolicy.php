<?php
namespace App\Service;

use App\Repository\UserRepository;
use DomainException;

// Separation of duties: an admin can't approve their own pay. The owner can approve anyone's, including her own.
class ApprovalPolicy {
    private UserRepository $users;

    public function __construct(?UserRepository $users = null) {
        $this->users = $users ?? new UserRepository();
    }

    public function assertCanApprove(?int $actorUserId, int $employeeId, string $what): void {
        if (!$actorUserId) {
            throw new DomainException('Sign in again to approve.');
        }

        $actor = $this->users->findById($actorUserId);
        if (!$actor) {
            throw new DomainException('Sign in again to approve.');
        }

        if ($actor['role'] !== 'owner' && (int) ($actor['employee_id'] ?? 0) === $employeeId) {
            throw new DomainException("You can't approve your own {$what}. Ask the owner to approve it.");
        }
    }
}
