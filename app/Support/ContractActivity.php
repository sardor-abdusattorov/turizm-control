<?php

namespace App\Support;

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
            'Contract Returned To Work' => ['icon' => 'heroicon-o-arrow-uturn-left', 'color' => 'warning'],
            'Contract Approver Reassigned' => ['icon' => 'heroicon-o-arrows-right-left', 'color' => 'primary'],
            default => match (strtolower($event)) {
                'created' => ['icon' => 'heroicon-o-sparkles', 'color' => 'info'],
                'updated' => ['icon' => 'heroicon-o-pencil-square', 'color' => 'gray'],
                'deleted' => ['icon' => 'heroicon-o-trash', 'color' => 'danger'],
                default => ['icon' => 'heroicon-o-information-circle', 'color' => 'gray'],
            },
        };
    }

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
            'Contract Returned To Work', 'Contract Approver Reassigned',
        ];
    }

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
            'Contract Returned To Work' => 'returned_to_work',
            'Contract Approver Reassigned' => 'approver_reassigned',
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
