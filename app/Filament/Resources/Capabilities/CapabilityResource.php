<?php

namespace App\Filament\Resources\Capabilities;

use App\Actions\MergeCapability;
use App\Filament\Resources\Capabilities\Pages\ManageCapabilities;
use App\Models\Capability;
use App\Rules\SlugRule;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Validation\ValidationException;

class CapabilityResource extends Resource
{
    protected static ?string $model = Capability::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Vocabulary';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required()->maxLength(120),
                TextInput::make('slug')->required()->rule(new SlugRule())->maxLength(120),
                Select::make('category')
                    ->required()
                    ->options([
                        'UserMgmt' => 'User Management',
                        'Commerce' => 'Commerce',
                        'Marketing' => 'Marketing',
                        'Content' => 'Content',
                        'Analytics' => 'Analytics',
                        'Integrations' => 'Integrations',
                        'Automation' => 'Automation',
                        'AI' => 'AI',
                        'Infrastructure' => 'Infrastructure',
                    ]),
                Textarea::make('description')
                    ->required()
                    ->rows(6)
                    ->columnSpanFull(),
                Toggle::make('description_reviewed')
                    ->helperText('LLM-seeded descriptions ship as off; flip after a hand-edit pass.'),
                TextInput::make('icon')->helperText('lucide-react icon name')->maxLength(60),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(function (Builder $query) {
                $count = static::vocabularyCount();
                $cap = (int) config('codex.vocabulary.capabilities.cap', 80);
                $warn = (int) config('codex.vocabulary.capabilities.warn', 60);
                if ($count >= $cap) {
                    Notification::make()
                        ->title("Capability vocabulary at hard cap ({$count}/{$cap})")
                        ->body('Merge before adding new capabilities — the heatmap is the load-bearing UI.')
                        ->danger()
                        ->persistent()
                        ->send();
                } elseif ($count >= $warn) {
                    Notification::make()
                        ->title("Capability vocabulary at warn threshold ({$count}/{$cap})")
                        ->body('Approaching the scannable-in-3s heatmap ceiling.')
                        ->warning()
                        ->send();
                }
                return $query;
            })
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('category')->badge()->sortable(),
                TextColumn::make('projects_count')
                    ->label('Projects')
                    ->counts('projects')
                    ->sortable(),
                TextColumn::make('canonical.name')
                    ->label('Merged into')
                    ->placeholder('—')
                    ->toggleable(),
                IconColumn::make('description_reviewed')
                    ->label('Reviewed')
                    ->boolean(),
                TextColumn::make('slug')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Filter::make('unreviewed')
                    ->label('Unreviewed descriptions')
                    ->query(fn (Builder $q) => $q->where('description_reviewed', false)),
                Filter::make('canonical_only')
                    ->label('Canonical (not aliases)')
                    ->query(fn (Builder $q) => $q->whereNull('canonical_id')),
            ])
            ->recordActions([
                EditAction::make(),
                Action::make('merge')
                    ->label('Merge into…')
                    ->icon(Heroicon::OutlinedArrowsRightLeft)
                    ->color('warning')
                    ->visible(fn (Capability $record) => $record->canonical_id === null)
                    ->schema([
                        Select::make('target_id')
                            ->label('Merge into capability')
                            ->options(fn (Capability $record) => Capability::query()
                                ->whereNull('canonical_id')
                                ->where('id', '!=', $record->id)
                                ->orderBy('name')
                                ->pluck('name', 'id')
                                ->all())
                            ->searchable()
                            ->required(),
                        Textarea::make('reason')
                            ->label('Reason')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Documented in audit_log; without it, six months later you can\'t tell whether a merge was correct or a slip.'),
                    ])
                    ->action(function (Capability $record, array $data) {
                        $target = Capability::findOrFail($data['target_id']);
                        try {
                            app(MergeCapability::class)->execute($record, $target, $data['reason']);
                            Notification::make()
                                ->title("Merged {$record->name} into {$target->name}.")
                                ->success()
                                ->send();
                        } catch (ValidationException $e) {
                            Notification::make()
                                ->title('Merge rejected')
                                ->body(collect($e->errors())->flatten()->implode(' '))
                                ->danger()
                                ->send();
                        }
                    }),
            ])
            // Capabilities are never deleted — merge is the only removal
            // path. The pivot's ON DELETE RESTRICT is the defence-in-depth.
            ->toolbarActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageCapabilities::route('/'),
        ];
    }

    private static function vocabularyCount(): int
    {
        return Capability::count();
    }
}
