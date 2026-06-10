<?php

namespace App\Filament\Resources\Packages;

use App\Filament\Resources\Packages\Pages\CreatePackage;
use App\Filament\Resources\Packages\Pages\EditPackage;
use App\Filament\Resources\Packages\Pages\ListPackages;
use App\Models\Package;
use App\Rules\SlugRule;
use BackedEnum;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteAction;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

class PackageResource extends Resource
{
    protected static ?string $model = Package::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedCube;

    protected static \UnitEnum|string|null $navigationGroup = 'Catalog';

    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')->required()->maxLength(255),
            TextInput::make('slug')->required()->rule(new SlugRule)->maxLength(120),
            Select::make('language')->required()->options([
                'typescript' => 'TypeScript',
                'php' => 'PHP',
                'python' => 'Python',
                'ruby' => 'Ruby',
                'go' => 'Go',
                'rust' => 'Rust',
                'dotnet' => '.NET',
                'kotlin' => 'Kotlin',
                'swift' => 'Swift',
                'dart' => 'Dart',
                'elixir' => 'Elixir',
            ]),
            Select::make('registry')->required()->options([
                'npm' => 'npm',
                'packagist' => 'Packagist',
                'pypi' => 'PyPI',
                'rubygems' => 'RubyGems',
                'go' => 'Go modules',
                'cargo' => 'crates.io',
                'pub' => 'pub.dev',
                'hex' => 'hex.pm',
                'nuget' => 'NuGet',
                'maven' => 'Maven Central',
                'swiftpm' => 'Swift PM',
            ]),
            Select::make('status')
                ->options(['active' => 'Active', 'archived' => 'Archived'])
                ->default('active')
                ->required(),
            TextInput::make('short_description')->required()->maxLength(280)
                ->columnSpanFull()->helperText('≤ 280 chars — grid card text.'),
            Textarea::make('long_description')->rows(8)->columnSpanFull(),
            Toggle::make('long_description_reviewed')
                ->helperText('Off for ingestion-pulled text; flip after a human pass.'),
            TextInput::make('repo_url')->url()->maxLength(500),
            TextInput::make('registry_url')->url()->maxLength(500),
            TextInput::make('docs_url')->url()->maxLength(500),
            DatePicker::make('shipped_date'),
            Select::make('capabilities')
                ->relationship('capabilities', 'name')
                ->multiple()
                ->preload()
                ->searchable(),
        ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('updated_at', 'desc')
            ->columns([
                TextColumn::make('name')->searchable()->sortable(),
                TextColumn::make('language')->badge()->sortable(),
                TextColumn::make('registry')->badge()->toggleable(),
                TextColumn::make('status')->badge()->color(fn ($state) => match ($state) {
                    'active' => 'success',
                    'archived' => 'warning',
                    default => 'gray',
                }),
                TextColumn::make('capabilities_count')->label('Caps')->counts('capabilities')->sortable(),
                TextColumn::make('updated_at')->dateTime()->sortable()->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('language')->options([
                    'typescript' => 'TypeScript', 'php' => 'PHP', 'python' => 'Python',
                    'ruby' => 'Ruby', 'go' => 'Go', 'rust' => 'Rust', 'dotnet' => '.NET',
                    'kotlin' => 'Kotlin', 'swift' => 'Swift', 'dart' => 'Dart', 'elixir' => 'Elixir',
                ]),
                SelectFilter::make('registry')->options([
                    'npm' => 'npm', 'packagist' => 'Packagist', 'pypi' => 'PyPI',
                    'rubygems' => 'RubyGems', 'cargo' => 'crates.io', 'go' => 'Go',
                    'nuget' => 'NuGet', 'maven' => 'Maven', 'pub' => 'pub.dev',
                    'hex' => 'hex.pm', 'swiftpm' => 'Swift PM',
                ]),
                SelectFilter::make('status')->options(['active' => 'Active', 'archived' => 'Archived']),
                TrashedFilter::make(),
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
            'index' => ListPackages::route('/'),
            'create' => CreatePackage::route('/create'),
            'edit' => EditPackage::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([SoftDeletingScope::class]);
    }
}
