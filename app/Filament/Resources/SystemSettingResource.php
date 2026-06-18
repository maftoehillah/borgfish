<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SystemSettingResource\Pages;
use App\Models\SystemSetting;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use UnitEnum;

class SystemSettingResource extends Resource
{
    protected static ?string $model = SystemSetting::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $modelLabel = 'Setting Sistem';

    protected static ?string $pluralModelLabel = 'Setting Sistem';

    protected static bool $shouldRegisterNavigation = false;

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('group')->label('Group')->default('general')->required()->maxLength(60),
                TextInput::make('key')->label('Key')->required()->unique(ignoreRecord: true)->maxLength(120),
                Select::make('type')
                    ->label('Tipe')
                    ->options([
                        'string' => 'String',
                        'integer' => 'Integer',
                        'boolean' => 'Boolean',
                        'text' => 'Text',
                        'longtext' => 'Long Text',
                    ])
                    ->default('string')
                    ->required(),
                Textarea::make('value')->label('Value')->rows(8)->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                BadgeColumn::make('group')->label('Group')->sortable(),
                TextColumn::make('key')->label('Key')->searchable()->sortable(),
                TextColumn::make('value')->label('Value')->limit(80)->searchable(),
                BadgeColumn::make('type')->label('Tipe')->sortable(),
                TextColumn::make('updated_at')->label('Update')->dateTime('d M Y H:i')->sortable(),
            ])
            ->actions([
                Actions\EditAction::make(),
            ])
            ->defaultSort('group');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSystemSettings::route('/'),
            'create' => Pages\CreateSystemSetting::route('/create'),
            'edit' => Pages\EditSystemSetting::route('/{record}/edit'),
        ];
    }
}
