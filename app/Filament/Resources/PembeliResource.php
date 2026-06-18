<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PembeliResource\Pages;
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

class PembeliResource extends Resource
{
    protected static ?string $model = User::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Pengguna';

    protected static ?string $modelLabel = 'Pembeli';

    protected static ?string $pluralModelLabel = 'Pembeli';

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
            ->where('role', 'pembeli')
            ->where('email', '!=', User::SUPERADMIN_EMAIL)
            ->where('role', '!=', 'superadmin');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextInput::make('name')->label('Nama')->required(),
                TextInput::make('email')->label('Email')->email()->required()->unique(ignoreRecord: true),
                // Password field dihapus agar tidak bisa diedit admin
                Hidden::make('role')->default('pembeli')->dehydrated(true),
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
                TextColumn::make('bids_count')->label('Bid')->counts('bids'),
                TextColumn::make('transaksis_count')->label('Transaksi Menang')->counts('transaksis'),
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
            'index' => Pages\ListPembeli::route('/'),
            'create' => Pages\CreatePembeli::route('/create'),
            'edit' => Pages\EditPembeli::route('/{record}/edit'),
        ];
    }
}
