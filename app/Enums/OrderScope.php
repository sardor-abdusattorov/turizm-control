<?php

namespace App\Enums;

enum OrderScope: string
{
    case Internal = 'internal';
    case External = 'external';

    public function label(): string
    {
        return __('app.order.scope.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Internal => 'info',
            self::External => 'success',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            function (array $options, self $scope): array {
                $options[$scope->value] = $scope->label();

                return $options;
            },
            [],
        );
    }
}
