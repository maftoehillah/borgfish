<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PenjualResource\Pages;
use App\Models\User;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class PenjualResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|UnitEnum|null $navigationGroup = 'Pengguna';

    protected static ?string $modelLabel = 'Penjual';

    protected static ?string $pluralModelLabel = 'Penjual';

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

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->where('role', 'penjual')
            ->where('email', '!=', User::SUPERADMIN_EMAIL)
            ->where('role', '!=', 'superadmin')
            ->with('sellerProfile');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nama')->required(),
                TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
                Hidden::make('role')->default('penjual')->dehydrated(true),
                Hidden::make('password')
                    ->default(fn () => \Illuminate\Support\Facades\Hash::make(\Illuminate\Support\Str::random(32)))
                    ->dehydrated(true)
                    ->hiddenOn('edit'),
                Toggle::make('is_admin')
                    ->label('Akses Admin')
                    ->disabled(fn (): bool => ! auth()->user()?->isSuperAdmin())
                    ->dehydrated(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                Select::make('admin_role')
                    ->label('Role Admin')
                    ->options([
                        User::ADMIN_ROLE_FINANCE => 'Finance',
                        User::ADMIN_ROLE_OPS => 'Operasional',
                        User::ADMIN_ROLE_SUPPORT => 'Support',
                    ])
                    ->default(User::ADMIN_ROLE_OPS)
                    ->visible(fn ($get): bool => (bool) $get('is_admin') || (bool) auth()->user()?->isSuperAdmin())
                    ->disabled(fn (): bool => ! auth()->user()?->isSuperAdmin())
                    ->dehydrated(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
                TextInput::make('whatsapp_number')->label('Nomor WhatsApp')->maxLength(32),
                Select::make('user_status')
                    ->label('Status User')
                    ->options([
                        'active' => 'Aktif',
                        'suspend' => 'Suspend',
                        'banned' => 'Banned',
                        'deleted' => 'Dihapus',
                    ])
                    ->default('active'),

                // Data Toko - hanya tampil saat edit
                TextInput::make('sellerProfile.store_name')
                    ->label('Nama Toko')
                    ->hiddenOn('create')
                    ->formatStateUsing(fn ($record) => $record?->sellerProfile?->store_name),
                TextInput::make('sellerProfile.full_address')
                    ->label('Alamat Toko')
                    ->hiddenOn('create')
                    ->formatStateUsing(fn ($record) => $record?->sellerProfile?->full_address),
                TextInput::make('sellerProfile.store_location')
                    ->label('Koordinat GPS')
                    ->disabled()
                    ->hiddenOn('create')
                    ->formatStateUsing(fn ($record) => $record?->sellerProfile?->store_location),

                // Rekening Bank - hanya tampil saat edit
                TextInput::make('sellerProfile.bank_name')
                    ->label('Nama Bank')
                    ->hiddenOn('create')
                    ->formatStateUsing(fn ($record) => $record?->sellerProfile?->bank_name),
                TextInput::make('sellerProfile.bank_account_number')
                    ->label('Nomor Rekening')
                    ->hiddenOn('create')
                    ->formatStateUsing(fn ($record) => $record?->sellerProfile?->bank_account_number),
                TextInput::make('sellerProfile.bank_account_name')
                    ->label('Nama Pemilik Rekening')
                    ->hiddenOn('create')
                    ->formatStateUsing(fn ($record) => $record?->sellerProfile?->bank_account_name),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                IconColumn::make('is_admin')->label('Admin')->boolean(),
                BadgeColumn::make('admin_role')
                    ->label('Role Admin')
                    ->state(fn (User $record): string => $record->isPanelAdmin() ? $record->adminRoleLabel() : '-')
                    ->colors([
                        'danger' => 'Superadmin',
                        'success' => 'Finance',
                        'info' => 'Operasional',
                        'gray' => 'Support',
                    ])
                    ->toggleable(),
                BadgeColumn::make('user_status')
                    ->label('Status')
                    ->formatStateUsing(fn (?string $state): string => match ($state) {
                        'suspend' => 'Suspend',
                        'banned' => 'Banned',
                        'deleted' => 'Dihapus',
                        default => 'Aktif',
                    })
                    ->colors([
                        'success' => 'active',
                        'warning' => 'suspend',
                        'danger' => 'banned',
                        'gray' => 'deleted',
                    ]),
                TextColumn::make('whatsapp_number')->label('WhatsApp')->placeholder('-')->searchable(),
                TextColumn::make('sellerProfile.store_name')->label('Nama Toko')->placeholder('-')->searchable(),
                TextColumn::make('ikans_count')->label('Ikan')->counts('ikans'),
                TextColumn::make('bids_count')->label('Bid')->counts('bids'),
                TextColumn::make('created_at')->label('Daftar')->date('d M Y')->sortable(),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canAdmin('ops')),
                Actions\DeleteAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListPenjual::route('/'),
            'create' => Pages\CreatePenjual::route('/create'),
            'edit' => Pages\EditPenjual::route('/{record}/edit'),
        ];
    }
}
