<?php

use App\Enums\UserRole;
use App\Traits\HasRoles;

function subjectWithRole(?UserRole $role): object
{
    return new class($role)
    {
        use HasRoles;

        public function __construct(public ?UserRole $role) {}
    };
}

test('role check matches the assigned role given as enum, value or list', function (UserRole|string|array $roles): void {
    $subject = subjectWithRole(UserRole::Admin);

    expect($subject->hasRole($roles))->toBeTrue();
})->with([
    'enum' => [UserRole::Admin],
    'string value' => ['admin'],
    'list containing the role' => [['engineering', UserRole::Admin]],
]);

test('role check rejects roles the subject does not hold', function (UserRole|string|array $roles): void {
    $subject = subjectWithRole(UserRole::Engineering);

    expect($subject->hasRole($roles))->toBeFalse();
})->with([
    'other enum' => [UserRole::Admin],
    'other string value' => ['admin'],
    'unknown role name' => ['superadmin'],
    'case mismatch' => ['Engineering'],
    'empty list' => [[]],
]);

test('role check is false for every role when no role is assigned', function (): void {
    $subject = subjectWithRole(null);

    expect($subject->hasRole(UserRole::values()))->toBeFalse();
});

test('role predicates reflect the assigned role', function (?UserRole $role, bool $isAdmin, bool $isEngineering): void {
    $subject = subjectWithRole($role);

    expect($subject->isAdmin())->toBe($isAdmin)
        ->and($subject->isEngineering())->toBe($isEngineering);
})->with([
    'admin' => [UserRole::Admin, true, false],
    'engineering' => [UserRole::Engineering, false, true],
    'no role' => [null, false, false],
]);

test('only admins can upload data, manage data and manage users', function (?UserRole $role, bool $allowed): void {
    $subject = subjectWithRole($role);

    expect([
        $subject->canUploadData(),
        $subject->canManageData(),
        $subject->canManageUsers(),
    ])->toBe([$allowed, $allowed, $allowed]);
})->with([
    'admin' => [UserRole::Admin, true],
    'engineering' => [UserRole::Engineering, false],
    'no role' => [null, false],
]);
