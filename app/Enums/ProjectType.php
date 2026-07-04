<?php

namespace App\Enums;

enum ProjectType: string
{
    case Internal = 'internal';
    case International = 'international';

    public function label(): string
    {
        return __('app.project.type.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Internal => 'info',
            self::International => 'success',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            function (array $options, self $type): array {
                $options[$type->value] = $type->label();

                return $options;
            },
            [],
        );
    }
}
