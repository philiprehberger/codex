<?php

namespace App\Filament\Resources\AuditLogs;

use App\Filament\Resources\AuditLogs\Pages\ManageAuditLogs;
use App\Models\AuditLog;
use App\Models\Capability;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Read-only resource. The unmerge action on merge_capability rows is the
 * one write surface — it restores diff.before.canonical_id and writes a
 * fresh action=unmerge_capability row so the moderation chain stays
 * traceable.
 */
class AuditLogResource extends Resource
{
    protected static ?string $model = AuditLog::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedShieldCheck;

    protected static \UnitEnum|string|null $navigationGroup = 'System';

    protected static ?string $recordTitleAttribute = 'action';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('action')->disabled(),
            TextInput::make('subject_type')->disabled(),
            TextInput::make('subject_id')->disabled(),
            TextInput::make('actor_id')->disabled(),
            TextInput::make('actor_ip')->disabled(),
            TextInput::make('actor_user_agent')->disabled(),
            Textarea::make('reason')->disabled()->columnSpanFull(),
            Textarea::make('diff_pretty')
                ->label('Diff (formatted)')
                ->disabled()
                ->rows(10)
                ->columnSpanFull()
                ->afterStateHydrated(function ($component, $record) {
                    $component->state($record ? json_encode($record->diff, JSON_PRETTY_PRINT) : '');
                }),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->dateTime()->sortable(),
                TextColumn::make('action')->badge()->searchable()->sortable(),
                TextColumn::make('subject_type')->searchable()->toggleable(),
                TextColumn::make('subject_id')->searchable()->toggleable()->limit(12),
                TextColumn::make('actor_id')->label('Actor')->placeholder('system')->toggleable(),
                TextColumn::make('actor_ip')->label('IP')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reason')->limit(60)->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('action')
                    ->options(fn () => AuditLog::query()
                        ->select('action')
                        ->distinct()
                        ->orderBy('action')
                        ->pluck('action', 'action')
                        ->all()),
                Filter::make('merges')
                    ->label('Merges only')
                    ->query(fn (Builder $q) => $q->where('action', 'merge_capability')),
            ])
            ->recordActions([
                ViewAction::make(),
                Action::make('unmerge')
                    ->label('Unmerge')
                    ->icon(Heroicon::OutlinedArrowUturnLeft)
                    ->color('warning')
                    ->requiresConfirmation()
                    ->visible(fn (AuditLog $record) => $record->action === 'merge_capability'
                        && $record->subject_type === 'capabilities')
                    ->action(function (AuditLog $record) {
                        $source = Capability::find($record->subject_id);
                        if (! $source) {
                            Notification::make()
                                ->title('Source capability not found.')
                                ->danger()
                                ->send();

                            return;
                        }

                        $before = $record->diff['before']['canonical_id'] ?? null;

                        DB::transaction(function () use ($source, $before, $record) {
                            $source->canonical_id = $before;
                            $source->save();

                            AuditLog::create([
                                'actor_id' => auth()->id(),
                                'actor_ip' => request()->ip(),
                                'actor_user_agent' => request()->userAgent(),
                                'action' => 'unmerge_capability',
                                'subject_type' => 'capabilities',
                                'subject_id' => $source->id,
                                'reason' => "Unmerge of audit row {$record->id}.",
                                'diff' => [
                                    'before' => $record->diff['after'] ?? [],
                                    'after' => $record->diff['before'] ?? [],
                                    'affected_pivots' => [],
                                    'truncated' => false,
                                ],
                                'created_at' => now(),
                            ]);
                        });

                        Notification::make()
                            ->title("Unmerged {$source->name} — canonical_id restored.")
                            ->success()
                            ->send();
                    }),
            ])
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageAuditLogs::route('/'),
        ];
    }
}
