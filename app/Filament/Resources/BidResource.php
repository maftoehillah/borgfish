<?php

namespace App\Filament\Resources;

use App\Filament\Resources\BidResource\Pages;
use App\Models\Bid;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class BidResource extends Resource
{
    protected static ?string $model = Bid::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-hand-raised';

    protected static string|UnitEnum|null $navigationGroup = 'Lelang';

    protected static ?string $modelLabel = 'Bid';

    protected static ?string $pluralModelLabel = 'Bid';

    public static function canViewAny(): bool
    {
        return (bool) auth()->user()?->canAdmin('support');
    }

    public static function canCreate(): bool
    {
        return (bool) auth()->user()?->canAdmin('ops');
    }

    public static function canEdit(Model $record): bool
    {
        return (bool) auth()->user()?->canAdmin('ops');
    }

    public static function canDelete(Model $record): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Select::make('user_id')
                    ->label('Pembeli')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Select::make('ikan_id')
                    ->label('Ikan')
                    ->relationship('ikan', 'nama_ikan')
                    ->searchable()
                    ->required(),
                TextInput::make('jumlah_bid')
                    ->label('Jumlah Bid')
                    ->numeric()
                    ->prefix('Rp')
                    ->required(),
                TextInput::make('bidder_ip')->label('IP Bidder')->maxLength(45),
                TextInput::make('bidder_user_agent')->label('User Agent')->maxLength(255),
                Toggle::make('is_suspicious')->label('Bid Mencurigakan'),
                TextInput::make('suspicion_reason')->label('Alasan Anomali')->maxLength(191),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('Pembeli')->searchable()->sortable(),
                TextColumn::make('ikan.nama_ikan')->label('Ikan')->searchable(),
                TextColumn::make('jumlah_bid')
                    ->label('Jumlah Bid')
                    ->formatStateUsing(fn ($state) => formatRupiah($state))
                    ->sortable(),
                BadgeColumn::make('is_suspicious')
                    ->label('Anomali')
                    ->formatStateUsing(fn ($state) => $state ? 'YA' : 'Tidak')
                    ->colors([
                        'danger' => fn ($state) => (bool) $state,
                        'success' => fn ($state) => ! (bool) $state,
                    ]),
                TextColumn::make('suspicion_reason')->label('Alasan')->toggleable(),
                TextColumn::make('bidder_ip')->label('IP')->toggleable(),
                TextColumn::make('created_at')->label('Waktu')->dateTime('d M Y H:i')->sortable(),
            ])
            ->defaultSort('created_at', 'desc')
            ->actions([
                Actions\ViewAction::make(),
                Actions\EditAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canAdmin('ops')),
                Actions\DeleteAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
            ])
            ->headerActions([]);
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListBids::route('/'),
            'create' => Pages\CreateBid::route('/create'),
            'view' => Pages\ViewBid::route('/{record}'),
            'edit' => Pages\EditBid::route('/{record}/edit'),
        ];
    }
}
