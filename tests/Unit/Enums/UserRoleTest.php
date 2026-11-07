<?php

use App\Enums\UserRole;

test('role values list every assignable role in declaration order', function (): void {
    expect(UserRole::values())->toBe(['admin', 'engineering']);
});
