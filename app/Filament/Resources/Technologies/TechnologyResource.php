<?php

namespace App\Filament\Resources\Technologies;

use App\Filament\Resources\Technologies\Pages\ManageTechnologies;
use App\Models\Technology;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class TechnologyResource extends Resource
{
    protected static ?string $model = Technology::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static \UnitEnum|string|null $navigationGroup = 'Vocabulary';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->required(),
                TextInput::make('slug')->required()->rule(new \App\Rules\SlugRule()),
                \Filament\Forms\Components\Select::make('category')
                    ->options([
                        'language' => 'Language',
                        'framework' => 'Framework',
                        'cms' => 'CMS',
                        'database' => 'Database',
                        'infrastructure' => 'Infrastructure',
                        'cloud' => 'Cloud',
                        'tooling' => 'Tooling',
                        'api' => 'API',
                        'library' => 'Library',
                    ])
                    ->required(),
                TextInput::make('icon_url')->url(),
                TextInput::make('vendor_url')->url(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('slug')->searchable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('category')->searchable()->sortable()->badge(),
                TextColumn::make('projects_count')
                    ->label('Projects')
                    ->counts('projects')
                    ->sortable(),
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
            'index' => ManageTechnologies::route('/'),
        ];
    }
}
