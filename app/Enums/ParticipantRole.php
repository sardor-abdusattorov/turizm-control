<?php

namespace App\Enums;

enum ParticipantRole: string
{
    case Participant = 'participant';
    case Sponsor = 'sponsor';

    public function label(): string
    {
        return __('app.project.participant_role.'.$this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Participant => 'info',
            self::Sponsor => 'warning',
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        return array_reduce(
            self::cases(),
            function (array $options, self $role): array {
                $options[$role->value] = $role->label();

                return $options;
            },
            [],
        );
    }
}
