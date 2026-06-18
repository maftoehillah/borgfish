<?php

namespace App\Filament\Resources;

use App\Filament\Resources\IkanResource\Pages;
use App\Models\Ikan;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class IkanResource extends Resource
{
    protected static ?string $model = Ikan::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-archive-box';

    protected static string|UnitEnum|null $navigationGroup = 'Lelang';

    protected static ?string $modelLabel = 'Ikan';

    protected static ?string $pluralModelLabel = 'Ikan';

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
                    ->label('Penjual')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                TextInput::make('nama_ikan')->label('Nama Ikan')->required()->maxLength(191),
                TextInput::make('berat')->label('Berat (kg)')->numeric()->required()->suffix('kg'),
                TextInput::make('estimasi_jumlah_ekor')->label('Estimasi Jumlah Ekor')->numeric()->minValue(1),
                Select::make('jenis_kemasan')->label('Jenis Kemasan')->options([
                    'keranjang' => 'Keranjang',
                    'besek' => 'Besek',
                    'styrofoam' => 'Styrofoam',
                ]),
                Select::make('kondisi')->options([
                    'segar' => 'Segar',
                    'beku' => 'Frozen',
                ])->required(),
                DatePicker::make('tanggal_tangkap')->label('Tanggal Tangkap'),
                TextInput::make('metode_tangkap')->label('Metode Tangkap')->maxLength(191),
                Select::make('tipe_lelang')->label('Jenis Lelang')->options([
                    'naik' => 'Lelang Naik',
                    'turun' => 'Lelang Turun',
                ])->required(),
                TextInput::make('harga_awal')->label('Harga Awal')->numeric()->required()->prefix('Rp'),
                TextInput::make('minimal_increment')->label('Min. Increment')->numeric()->required()->prefix('Rp'),
                Toggle::make('buy_now_enabled')->label('Aktifkan Beli Sekarang'),
                TextInput::make('buy_now_price')->label('Harga Beli Sekarang')->numeric()->prefix('Rp'),
                Toggle::make('anti_sniping_enabled')->label('Aktifkan Anti-Sniping')->default(true),
                TextInput::make('anti_sniping_window_seconds')->label('Window Anti-Sniping (detik)')->numeric()->default(120),
                TextInput::make('anti_sniping_extend_seconds')->label('Durasi Extend (detik)')->numeric()->default(120),
                TextInput::make('anti_sniping_max_extensions')->label('Maks Extend')->numeric()->default(3),
                DateTimePicker::make('waktu_mulai')->required(),
                DateTimePicker::make('waktu_selesai')->required(),
                Select::make('status')->options([
                    'menunggu' => 'Menunggu',
                    'aktif' => 'Aktif',
                    'selesai' => 'Selesai',
                    'terbayar' => 'Terbayar',
                ])->required(),
                TextInput::make('state_version')->label('State Version')->numeric()->readOnly(),
                Textarea::make('deskripsi')->columnSpanFull(),
                FileUpload::make('foto')->image()->disk('public')->directory('ikans')->columnSpanFull(),
                FileUpload::make('video')->disk('public')->directory('ikans-videos')->columnSpanFull(),
                DateTimePicker::make('foto_diambil_pada')->label('Foto Diambil Pada'),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                ImageColumn::make('foto')
                    ->getStateUsing(fn (Ikan $record): ?string => $record->foto ? url(publicStorageUrl($record->foto)) : null)
                    ->circular(),
                TextColumn::make('nama_ikan')->label('Nama')->searchable()->sortable(),
                TextColumn::make('user.name')->label('Penjual')->searchable(),
                TextColumn::make('tipe_lelang')->label('Jenis Lelang')->formatStateUsing(fn ($state) => $state === 'turun' ? 'Lelang Turun' : 'Lelang Naik')->badge(),
                TextColumn::make('estimasi_jumlah_ekor')->label('Ekor')->sortable(),
                TextColumn::make('jenis_kemasan')->label('Kemasan')->badge(),
                BadgeColumn::make('kondisi')
                    ->formatStateUsing(fn (?string $state): string => $state === 'beku' ? 'Frozen' : 'Segar')
                    ->colors([
                        'success' => 'segar',
                        'info' => 'beku',
                    ]),
                TextColumn::make('harga_tertinggi')
                    ->label('Harga Saat Ini')
                    ->formatStateUsing(fn ($state) => formatRupiah($state))
                    ->sortable(),
                BadgeColumn::make('status')->colors([
                    'success' => 'aktif',
                    'warning' => 'menunggu',
                    'gray' => 'selesai',
                    'info' => 'terbayar',
                ]),
                TextColumn::make('bids_count')->label('Bid')->counts('bids')->sortable(),
                TextColumn::make('anomali_bid')
                    ->label('Anomali')
                    ->state(fn (Ikan $record): int => $record->bids()->where('is_suspicious', true)->count()),
                TextColumn::make('buy_now_price')
                    ->label('Beli Sekarang')
                    ->formatStateUsing(fn ($state) => $state ? formatRupiah($state) : '-')
                    ->sortable(),
                TextColumn::make('waktu_selesai')->label('Selesai')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('status')->options([
                    'menunggu' => 'Menunggu',
                    'aktif' => 'Aktif',
                    'selesai' => 'Selesai',
                    'terbayar' => 'Terbayar',
                ]),
                SelectFilter::make('kondisi')->options([
                    'segar' => 'Segar',
                    'beku' => 'Frozen',
                ]),
                SelectFilter::make('tipe_lelang')->options([
                    'naik' => 'Lelang Naik',
                    'turun' => 'Lelang Turun',
                ]),
                SelectFilter::make('buy_now_enabled')->options([
                    1 => 'Beli Sekarang Aktif',
                    0 => 'Beli Sekarang Nonaktif',
                ]),
            ])
            ->actions([
                Actions\ViewAction::make(),
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
            'index' => Pages\ListIkans::route('/'),
            'create' => Pages\CreateIkan::route('/create'),
            'edit' => Pages\EditIkan::route('/{record}/edit'),
            'view' => Pages\ViewIkan::route('/{record}'),
        ];
    }
}
