<?php

namespace App\Filament\Resources\Payments\Pages;

use App\Filament\Resources\Contracts\ContractResource;
use App\Filament\Resources\Payments\PaymentResource;
use App\Models\Payment;
use Filament\Actions\EditAction;
use Filament\Infolists\Components\ImageEntry;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Pages\ViewRecord;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;

/**
 * Native Filament infolist (no custom blade): the payment facts in a
 * two-column section, the proof files as clickable image thumbnails and
 * PDF links fed by the same signed temporary URLs the Telegram flow uses.
 */
class ViewPayment extends ViewRecord
{
    protected static string $resource = PaymentResource::class;

    public function getHeading(): string
    {
        return format_percent((float) $this->record->percent)
            .'% · '.($this->record->contract?->number ?? __('app.label.payment_single'));
    }

    public function getSubheading(): ?string
    {
        return null;
    }

    protected function getHeaderActions(): array
    {
        return [
            EditAction::make(),
        ];
    }

    public function infolist(Schema $schema): Schema
    {
        return $schema->components([
            Section::make(__('app.label.basic_information'))
                ->icon('heroicon-o-clipboard-document-list')
                ->columns(2)
                ->schema([
                    TextEntry::make('contract_link')
                        ->label(__('app.label.contract'))
                        ->state(fn (Payment $record): ?string => $record->contract
                            ? trim($record->contract->number.' · '.($record->contract->title ?? ''), ' ·')
                            : null)
                        ->url(fn (Payment $record): ?string => $record->contract
                            ? ContractResource::getUrl('view', ['record' => $record->contract])
                            : null)
                        ->color('primary')
                        ->icon('heroicon-o-document-text')
                        ->placeholder(__('app.label.not_set')),

                    TextEntry::make('percent')
                        ->label(__('app.label.percent'))
                        ->badge()
                        ->color('success')
                        ->formatStateUsing(fn ($state): string => format_percent((float) $state).'%'),

                    TextEntry::make('paid_at')
                        ->label(__('app.label.paid_at'))
                        ->date('d.m.Y')
                        ->placeholder(__('app.label.not_set')),

                    TextEntry::make('creator.name')
                        ->label(__('app.label.created_by'))
                        ->placeholder(__('app.label.not_set')),

                    TextEntry::make('created_at')
                        ->label(__('app.label.created_at'))
                        ->dateTime('d.m.Y H:i'),
                ]),

            Section::make(__('app.label.screenshot'))
                ->icon('heroicon-o-photo')
                ->visible(fn (Payment $record): bool => $record->screenshotFiles() !== [])
                ->schema([
                    // Image proofs — clickable thumbnails on their signed URLs.
                    RepeatableEntry::make('image_files')
                        ->hiddenLabel()
                        ->state(fn (Payment $record): array => array_values(
                            array_filter($record->screenshotFiles(), fn (array $f): bool => ! $f['pdf'])
                        ))
                        ->grid(3)
                        ->contained(false)
                        ->visible(fn (Payment $record): bool => collect($record->screenshotFiles())->contains('pdf', false))
                        ->schema([
                            ImageEntry::make('url')
                                ->hiddenLabel()
                                ->height(200)
                                ->extraImgAttributes(['style' => 'object-fit:cover;border-radius:.5rem'])
                                ->url(fn (?string $state): ?string => $state, shouldOpenInNewTab: true),
                        ]),

                    // PDF payment orders — plain document links.
                    RepeatableEntry::make('pdf_files')
                        ->hiddenLabel()
                        ->state(fn (Payment $record): array => array_values(
                            array_filter($record->screenshotFiles(), fn (array $f): bool => $f['pdf'])
                        ))
                        ->contained(false)
                        ->visible(fn (Payment $record): bool => collect($record->screenshotFiles())->contains('pdf', true))
                        ->schema([
                            TextEntry::make('name')
                                ->hiddenLabel()
                                ->icon('heroicon-o-document-text')
                                ->url(fn (TextEntry $component): ?string => data_get($component->getContainer()->getState(), 'url'), shouldOpenInNewTab: true),
                        ]),
                ]),
        ]);
    }
}
