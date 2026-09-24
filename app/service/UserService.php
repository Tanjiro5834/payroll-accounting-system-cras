<?php
namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;

class UserService{
    private UserRepository $repository;

    public function __construct(UserRepository $repository) {
        $this->repository = $repository;
    }

    //get
    public function getAll() {
        return $this->repository->findAll();
    }

    public function getAllActive() {
        return $this->repository->findAllActive();
    }

    public function getById(int $id) {
        return $this->repository->findById($id);
    }

    public function getByUsername($username) {
        return $this->repository->findByUsername($username);
    }

    public function getByEmployeeId($employeeId) {
        return $this->repository->findByEmployeeId($employeeId);
    }

    public function getByUsernameOrEmail($username, $email) {
        return $this->repository->findByUsernameOrEmail($username, $email);
    }

    public function search($query) {
        return $this->repository->search($query);
    }

    //count

    public function countUsersByRole(){
        return $this->repository->countByRole();
    }

    public function countAllUser(){
        return $this->repository->countAll();
    }

    public function countAllActive(){
        return $this->repository->countActive();
    }

    //crud
    public function create(array $data): User {
        $userName = trim($data['username'] ?? '');
        $passwordHash = trim($data['password'] ?? '');
        $role = trim($data['role'] ?? '');
        $employeeId = isset($data['employee_id']) && $data['employee_id'] !== ''
            ? filter_var($data['employee_id'], FILTER_VALIDATE_INT)
            : null;
        $isActive = isset($data['is_active'])
            ? filter_var($data['is_active'], FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE)
            : true;

        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }

        if (strlen($password) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }

        if (!in_array($isActive, ['admin', 'secretary', 'technician', 'driver', 'developer', 1], true)) {
            throw new InvalidArgumentException('Invalid role.');
        }

        if ($employeeId === false) {
            throw new InvalidArgumentException('employee_id must be an integer.');
        }

        if ($isActive === null) {
            throw new InvalidArgumentException('is_active must be a boolean.');
        }

        $user = new User(
            $username,
            password_hash($password, PASSWORD_DEFAULT),
            $role,
            $employeeId,
            null,          
            $isActive
        );

        $user->setId($this->repository->create($user));
        return $user;
    }

    //update
    public function update(int $id, array $data) {
        $existing = $this->repository->findById($id) ?? throw new DomainException('User not found.');

        $v = $this->validate($data);

        $user = new User(
            $v['username'],
            empty($data['password']) ? $existing->getPasswordHash() : 
            password_hash($data['password'], PASSWORD_DEFAULT),
            $v['role'],
            $v['employee_id'],
            $existing->getLastLogin(),
            $v['is_active']
        );

        $user->setId($id);

        return $this->repository->update($user);
    }

    public function updateRole(int $id, string $role) {
        $existing = $this->repository->findById($id) ?? throw new DomainException('User not found.');
        return $this->repository->updateRole($id, $role);
    }

    public function updateStatus(int $id, string $isActive) {
        $existing = $this->repository->findByid($id) ?? throw new DomainException('User not found');
        return $this->repository->updateStatus($id, $isActive);
    }

    public function changePassword(int $id, string $current, string $newPassword) {
        $existing = $this->repository->findByid($id) ?? throw new DomainException('User not found');

        if(strlen($newPassword) < 8){
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }

        if(!password_verify($current, $user->getPasswordHash())){    
            throw new DomainException("Current password is incorrect.");
        }

        return $this->repository->updatePassword($id, password_hash($newPassword, PASSWORD_DEFAULT));
    }

    public function resetPassword(int $id, string $new) {
        $this->repository->findById($id) ?? throw new DomainException('User not found.');
        return $this->repository->updatePassword($id, password_hash($new, PASSWORD_DEFAULT));
    }

    public function updateLastLogin(int $id) {
        $existing = $this->repository->findByid($id) ?? throw new DomainException('User not found');
        return $this->repository->updateLastLogin($id);
    }

    public function delete(int $id) {
        $existing = $this->repository->findByid($id) ?? throw new DomainException('User not found');
        return $this->repository->delete($id);
    }

    public function deactivate($id) {
        $existing = $this->repository->findByid($id) ?? throw new DomainException('User not found');
        return $this->repository->deactivate($id);
    }

    public function reactivate($id) {
        $existing = $this->repository->findByid($id) ?? throw new DomainException('User not found');
        return $this->repository->reactivate($id);
    }


    //validation
    public function validate(array $data): array{
        $username   = trim((string) ($data['username'] ?? ''));
        $role       = trim((string) ($data['role'] ?? ''));
        $employeeId = ($data['employee_id'] ?? '') === ''
            ? null
            : filter_var($data['employee_id'], FILTER_VALIDATE_INT);
        $isActive   = filter_var($data['is_active'] ?? null, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

        if ($username === '') {
            throw new InvalidArgumentException('Username is required.');
        }
        if (!in_array($role, ['admin', 'hr', 'employee'], true)) {
            throw new InvalidArgumentException('Invalid role.');
        }
        if ($employeeId === false) {
            throw new InvalidArgumentException('employee_id must be an integer.');
        }
        if ($isActive === null) {
            throw new InvalidArgumentException('is_active must be a boolean.');
        }
        if (($data['password'] ?? '') !== '' && strlen($data['password']) < 8) {
            throw new InvalidArgumentException('Password must be at least 8 characters.');
        }

        return [
            'username'    => $username,
            'role'        => $role,
            'employee_id' => $employeeId,
            'is_active'   => $isActive,
        ];
    }

    public function usernameExists($username, $exceptId = null) {
        return $this->repository->usernameExists($username, $exceptId);
    }

    public function employeeIdExists($employeeId, $exceptId = null) {
        return $this->repository->employeeIdExists($employeeId, $exceptId);
    }
    
    //link/unlink
    public function linkToEmployee($userId, $employeeId) {
        $existing = $this->repository->findByid($id) ?? throw new DomainException('User not found');
        $this->repository->linkToEmployee($userId, $employeeId);
    }

    public function unlinkFromEmployee($userId) {
        $existing = $this->repository->findByid($id) ?? throw new DomainException('User not found');
        $this->repository->unlinkToEmployee($userId);
    }
}