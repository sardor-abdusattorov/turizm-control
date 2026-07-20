<?php

namespace App\Support;

/**
 * Presentation helpers for the contract activity feed — the stored
 * description is a raw English event string ("Contract Awaiting Director"),
 * which reads as gibberish to a ru/uz user. Shared by the history timeline
 * widget and anything else that renders activity rows.
 */
class ContractActivity
{
    /** @return array{icon: string, color: string} */
    public static function visual(string $event): array
    {
        return match ($event) {
            'Contract Submitted' => ['icon' => 'heroicon-o-paper-airplane', 'color' => 'info'],
            'Contract Sent To Director' => ['icon' => 'heroicon-o-arrow-up-circle', 'color' => 'primary'],
            'Contract Step Approved', 'Contract Approved', 'Contract Awaiting Director' => ['icon' => 'heroicon-o-check-circle', 'color' => 'success'],
            'Contract Rejected' => ['icon' => 'heroicon-o-x-circle', 'color' => 'danger'],
            'Contract Document Saved', 'Contract Document Forcesave' => ['icon' => 'heroicon-o-document-text', 'color' => 'info'],
            'Contract Edit Invalidated' => ['icon' => 'heroicon-o-no-symbol', 'color' => 'warning'],
            default => match (strtolower($event)) {
                'created' => ['icon' => 'heroicon-o-sparkles', 'color' => 'info'],
                'updated' => ['icon' => 'heroicon-o-pencil-square', 'color' => 'gray'],
                'deleted' => ['icon' => 'heroicon-o-trash', 'color' => 'danger'],
                default => ['icon' => 'heroicon-o-information-circle', 'color' => 'gray'],
            },
        };
    }

    /** Workflow milestones vs plain edits — powers the history filter. */
    public static function group(string $event): string
    {
        return in_array($event, self::workflowEvents(), true) ? 'workflow' : 'edit';
    }

    /** @return list<string> */
    public static function workflowEvents(): array
    {
        return [
            'Contract Submitted', 'Contract Sent To Director', 'Contract Step Approved',
            'Contract Approved', 'Contract Rejected', 'Contract Awaiting Director',
        ];
    }

    /** Human, localized label for a timeline row. */
    public static function label(string $event, ?string $description = null): string
    {
        $key = match ($event) {
            'Contract Submitted' => 'submitted',
            'Contract Step Approved' => 'step_approved',
            'Contract Awaiting Director' => 'awaiting_director',
            'Contract Approved' => 'approved',
            'Contract Sent To Director' => 'sent_to_director',
            'Contract Rejected' => 'rejected',
            'Contract Document Saved', 'Contract Document Forcesave' => 'document_saved',
            'Contract Edit Invalidated' => 'edit_invalidated',
            default => match (strtolower($event)) {
                'created' => 'created',
                'updated' => 'updated',
                'deleted' => 'deleted',
                default => null,
            },
        };

        return $key !== null ? __("app.activity.{$key}") : ($description ?: $event);
    }
}
