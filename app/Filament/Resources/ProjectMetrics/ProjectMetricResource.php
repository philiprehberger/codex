<?php

namespace App\Filament\Resources\ProjectMetrics;

use App\Filament\Resources\ProjectMetrics\Pages\ManageProjectMetrics;
use App\Models\ProjectMetric;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class ProjectMetricResource extends Resource
{
    protected static ?string $model = ProjectMetric::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('project_id')
                    ->relationship('project', 'name')
                    ->required(),
                DatePicker::make('recorded_at')
                    ->required(),
                TextInput::make('duration_days')
                    ->numeric(),
                TextInput::make('api_integrations')
                    ->numeric(),
                TextInput::make('database_tables')
                    ->numeric(),
                TextInput::make('test_count')
                    ->numeric(),
                TextInput::make('lighthouse_perf')
                    ->numeric(),
                TextInput::make('lighthouse_a11y')
                    ->numeric(),
                TextInput::make('lighthouse_best')
                    ->numeric(),
                TextInput::make('lighthouse_seo')
                    ->numeric(),
                TextInput::make('loc_total')
                    ->numeric(),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('id')
                    ->label('ID')
                    ->searchable(),
                TextColumn::make('project.name')
                    ->searchable(),
                TextColumn::make('recorded_at')
                    ->date()
                    ->sortable(),
                TextColumn::make('duration_days')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('api_integrations')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('database_tables')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('test_count')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lighthouse_perf')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lighthouse_a11y')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lighthouse_best')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('lighthouse_seo')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('loc_total')
                    ->numeric()
                    ->sortable(),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                //
            ])
            ->recordActions([
                EditAction::make(),
                DeleteAction::make(),
            ])
            ->toolbarActions([
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getPages(): array
    {
        return [
            'index' => ManageProjectMetrics::route('/'),
        ];
    }
}
