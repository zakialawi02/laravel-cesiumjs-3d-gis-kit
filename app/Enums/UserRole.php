<?php

namespace App\Enums;

enum UserRole: string
{
    case Admin = 'admin';
    case Engineering = 'engineering';

    /**
     * Return the human readable label for the role.
     */
    public function label(): string
    {
        return match ($this) {
            self::Admin => __('Admin'),
            self::Engineering => __('Engineering'),
        };
    }

    /**
     * Get the list of role values.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $role): string => $role->value, self::cases());
    }
}
