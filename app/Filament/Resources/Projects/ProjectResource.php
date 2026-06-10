<?php

namespace App\Filament\Resources\Projects;

use App\Filament\Resources\Projects\Pages\CreateProject;
use App\Filament\Resources\Projects\Pages\EditProject;
use App\Filament\Resources\Projects\Pages\ListProjects;
use App\Models\Capability;
use App\Models\Industry;
use App\Models\Project;
use App\Models\ProjectTag;
use App\Models\Scopes\RedactedScope;
use App\Models\Technology;
use App\Rules\SlugRule;
use BackedEnum;
use Filament\Actions\BulkAction;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ForceDeleteBulkAction;
use Filament\Actions\RestoreBulkAction;
use Filament\Notifications\Notification;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Tabs;
use Filament\Schemas\Components\Tabs\Tab;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class ProjectResource extends Resource
{
    protected static ?string $model = Project::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?string $recordTitleAttribute = 'name';

    /**
     * Default queries in Filament must see redacted rows in full — the
     * admin IS the redaction owner. RedactedScope strips for public API
     * reads, not for admin curation.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->withoutGlobalScope(RedactedScope::class);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            Tabs::make('Project sections')
                ->columnSpanFull()
                ->tabs([
                    Tab::make('Identity')
                        ->icon(Heroicon::OutlinedIdentification)
                        ->schema([
                            TextInput::make('name')->required()->maxLength(255),
                            TextInput::make('slug')->required()->rule(new SlugRule())->maxLength(120),
                            Select::make('project_type')
                                ->options([
                                    'demo' => 'Demo',
                                    'client' => 'Client',
                                    'personal' => 'Personal',
                                    'open_source' => 'Open source',
                                    'package' => 'Package',
                                ])
                                ->required(),
                            Select::make('status')
                                ->options([
                                    'idea' => 'Idea',
                                    'active' => 'Active',
                                    'shipped' => 'Shipped',
                                    'archived' => 'Archived',
                                ])
                                ->required()
                                ->live(),
                            Select::make('visibility')
                                ->options([
                                    'public' => 'Public',
                                    'redacted' => 'Redacted',
                                    'private' => 'Private',
                                ])
                                ->required()
                                ->live(),
                            DatePicker::make('shipped_date')
                                ->requiredIf('status', 'shipped'),
                            TextInput::make('hours_estimated')->numeric()->minValue(0),
                            TextInput::make('hours_actual')
                                ->numeric()
                                ->minValue(0)
                                ->requiredIf('status', 'shipped'),
                            TextInput::make('team_size')->numeric()->minValue(1),
                        ])->columns(2),

                    Tab::make('Description')
                        ->icon(Heroicon::OutlinedDocumentText)
                        ->schema([
                            TextInput::make('short_description')->required()->maxLength(280)
                                ->helperText('≤ 280 chars — grid card text.'),
                            Textarea::make('long_description')
                                ->rows(12)
                                ->columnSpanFull(),
                            Toggle::make('long_description_reviewed')
                                ->helperText('Off for LLM-drafted text; flip after a human pass.'),
                        ]),

                    Tab::make('URLs')
                        ->icon(Heroicon::OutlinedLink)
                        ->schema([
                            TextInput::make('repo_url')->url()->maxLength(500),
                            TextInput::make('live_url')->url()->maxLength(500),
                            TextInput::make('docs_url')->url()->maxLength(500),
                        ]),

                    Tab::make('Tags')
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([
                            Select::make('technologies')
                                ->relationship('technologies', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable(),
                            Select::make('capabilities')
                                ->relationship('capabilities', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable(),
                            Select::make('industries')
                                ->relationship('industries', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable(),
                            Select::make('architectures')
                                ->relationship('architectures', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable(),
                            Select::make('deliverables')
                                ->relationship('deliverables', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable(),
                            Select::make('designStyles')
                                ->relationship('designStyles', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable(),
                            Select::make('tags')
                                ->relationship('tags', 'name')
                                ->multiple()
                                ->preload()
                                ->searchable(),
                        ]),

                    Tab::make('Client')
                        ->icon(Heroicon::OutlinedBuildingOffice)
                        ->visible(fn (callable $get) => in_array($get('visibility'), ['redacted', 'private'], true))
                        ->schema([
                            TextInput::make('client_name')
                                ->helperText('Stripped from public reads by RedactedScope.'),
                            TextInput::make('client_industry')
                                ->helperText('Visible on public reads even when client name is redacted.'),
                            Textarea::make('internal_notes')
                                ->rows(6)
                                ->helperText('Never exposed publicly. Hidden from toArray + RedactedScope.')
                                ->columnSpanFull(),
                        ]),
                ]),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('project_type')->badge()->sortable(),
                TextColumn::make('status')->badge()->sortable()->color(fn ($state) => match ($state) {
                    'idea' => 'gray',
                    'active' => 'info',
                    'shipped' => 'success',
                    'archived' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('visibility')->badge()->sortable()->color(fn ($state) => match ($state) {
                    'public' => 'success',
                    'redacted' => 'warning',
                    'private' => 'danger',
                    default => 'gray',
                }),
                TextColumn::make('capabilities_count')->label('Caps')->counts('capabilities')->sortable(),
                TextColumn::make('technologies_count')->label('Tech')->counts('technologies')->sortable(),
                TextColumn::make('shipped_date')->date()->sortable()->toggleable(),
                IconColumn::make('long_description_reviewed')->label('Reviewed')->boolean()->toggleable(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('project_type')->options([
                    'demo' => 'Demo', 'client' => 'Client', 'personal' => 'Personal',
                    'open_source' => 'Open source', 'package' => 'Package',
                ]),
                SelectFilter::make('status')->options([
                    'idea' => 'Idea', 'active' => 'Active', 'shipped' => 'Shipped', 'archived' => 'Archived',
                ]),
                SelectFilter::make('visibility')->options([
                    'public' => 'Public', 'redacted' => 'Redacted', 'private' => 'Private',
                ]),
                TrashedFilter::make(),
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    BulkAction::make('add_capability')
                        ->label('Add capability…')
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([
                            \Filament\Forms\Components\Select::make('capability_id')
                                ->label('Capability')
                                ->options(fn () => Capability::query()
                                    ->whereNull('canonical_id')
                                    ->orderBy('name')
                                    ->pluck('name', 'id')
                                    ->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            self::bulkAttach($records, 'capabilities', $data['capability_id']);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('add_technology')
                        ->label('Add technology…')
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([
                            \Filament\Forms\Components\Select::make('technology_id')
                                ->label('Technology')
                                ->options(fn () => Technology::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            self::bulkAttach($records, 'technologies', $data['technology_id']);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('add_industry')
                        ->label('Add industry…')
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([
                            \Filament\Forms\Components\Select::make('industry_id')
                                ->label('Industry')
                                ->options(fn () => Industry::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            self::bulkAttach($records, 'industries', $data['industry_id']);
                        })
                        ->deselectRecordsAfterCompletion(),
                    BulkAction::make('add_tag')
                        ->label('Add tag…')
                        ->icon(Heroicon::OutlinedTag)
                        ->schema([
                            \Filament\Forms\Components\Select::make('tag_id')
                                ->label('Tag')
                                ->options(fn () => ProjectTag::query()->orderBy('name')->pluck('name', 'id')->all())
                                ->searchable()
                                ->required(),
                        ])
                        ->action(function (Collection $records, array $data) {
                            self::bulkAttach($records, 'tags', $data['tag_id']);
                        })
                        ->deselectRecordsAfterCompletion(),
                    DeleteBulkAction::make(),
                    ForceDeleteBulkAction::make(),
                    RestoreBulkAction::make(),
                ]),
            ]);
    }

    /**
     * Apply one tag id across N selected projects atomically. The
     * relation's attach() emits a single observer firing per pivot row,
     * which all land in the same RevalidationBuffer. With > 10 rows the
     * terminating() flush pushes the work onto the database queue (one
     * RevalidateCacheJob coalescing the 4 cache tags) instead of firing
     * inline — admin returns immediately.
     */
    private static function bulkAttach(Collection $records, string $relation, string $tagId): void
    {
        $applied = 0;
        DB::transaction(function () use ($records, $relation, $tagId, &$applied) {
            foreach ($records as $project) {
                /** @var Project $project */
                if (! $project->{$relation}()->where("{$relation}.id", $tagId)->exists()) {
                    $project->{$relation}()->attach($tagId);
                    $applied++;
                }
            }
        });

        Notification::make()
            ->title("Tagged {$applied} project(s).")
            ->success()
            ->send();
    }

    public static function getPages(): array
    {
        return [
            'index' => ListProjects::route('/'),
            'create' => CreateProject::route('/create'),
            'edit' => EditProject::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
                RedactedScope::class,
            ]);
    }
}
