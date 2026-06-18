<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ViolationResource\Pages;
use App\Models\User;
use App\Models\Violation;
use App\Services\AuditService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class ViolationResource extends Resource
{
    protected static ?string $model = Violation::class;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-shield-exclamation';

    protected static string|UnitEnum|null $navigationGroup = 'Pengguna';

    protected static ?string $modelLabel = 'Pelanggaran';

    protected static ?string $pluralModelLabel = 'Pelanggaran';

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
                    ->label('User')
                    ->relationship('user', 'name')
                    ->searchable()
                    ->required(),
                Select::make('role')
                    ->label('Role')
                    ->options([
                        'pembeli' => 'Pembeli',
                        'penjual' => 'Penjual',
                    ])
                    ->required(),
                Select::make('type')
                    ->label('Tipe')
                    ->options([
                        'buyer_no_payment' => 'Menang Bid Tidak Bayar',
                        'fake_item' => 'Barang Fiktif',
                        'counterfeit_item' => 'Barang Palsu',
                        'rule_violation' => 'Tidak Sesuai Aturan',
                        'fraud' => 'Menipu',
                        'manual_admin' => 'Catatan Manual Admin',
                    ])
                    ->required(),
                Select::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'resolved' => 'Resolved',
                    ])
                    ->default('active')
                    ->required(),
                Select::make('action')
                    ->label('Aksi')
                    ->options([
                        'warning' => 'Catatan',
                        'suspend' => 'Suspend',
                        'ban' => 'Ban',
                    ])
                    ->default('warning')
                    ->required(),
                TextInput::make('duration_hours')->label('Durasi Jam')->numeric()->minValue(1),
                DateTimePicker::make('effective_from')->label('Mulai Berlaku'),
                DateTimePicker::make('effective_until')->label('Berlaku Sampai'),
                DateTimePicker::make('resolved_at')->label('Resolved At'),
                Textarea::make('reason')->label('Alasan')->required()->maxLength(1000)->columnSpanFull(),
                Textarea::make('notes')->label('Catatan Admin')->maxLength(2000)->columnSpanFull(),
            ])
            ->columns(2);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('user.name')->label('User')->searchable()->sortable(),
                TextColumn::make('user.email')->label('Email')->searchable()->toggleable(),
                BadgeColumn::make('role')
                    ->label('Role')
                    ->colors([
                        'info' => 'pembeli',
                        'warning' => 'penjual',
                    ]),
                BadgeColumn::make('type')->label('Tipe')->sortable(),
                BadgeColumn::make('action')
                    ->label('Aksi')
                    ->colors([
                        'gray' => 'warning',
                        'warning' => 'suspend',
                        'danger' => 'ban',
                    ]),
                BadgeColumn::make('status')
                    ->label('Status')
                    ->colors([
                        'danger' => 'active',
                        'success' => 'resolved',
                    ]),
                TextColumn::make('effective_until')->label('Berlaku Sampai')->dateTime('d M Y H:i')->placeholder('-')->sortable(),
                TextColumn::make('adminExecutor.name')->label('Executor')->placeholder('-')->toggleable(),
                TextColumn::make('created_at')->label('Tanggal')->dateTime('d M Y H:i')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')
                    ->label('Role')
                    ->options([
                        'pembeli' => 'Pembeli',
                        'penjual' => 'Penjual',
                    ]),
                SelectFilter::make('status')
                    ->label('Status')
                    ->options([
                        'active' => 'Aktif',
                        'resolved' => 'Resolved',
                    ]),
                SelectFilter::make('action')
                    ->label('Aksi')
                    ->options([
                        'warning' => 'Catatan',
                        'suspend' => 'Suspend',
                        'ban' => 'Ban',
                    ]),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canAdmin('ops')),
                Actions\Action::make('apply_suspend')
                    ->label('Suspend User')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->visible(fn (Violation $record): bool => (bool) auth()->user()?->canAdmin('ops')
                        && $record->user !== null
                        && (string) $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function (Violation $record): void {
                        $user = $record->user;
                        if (! $user instanceof User || $user->isAdminUser()) {
                            return;
                        }

                        $until = $record->effective_until ?: now()->addHours((int) ($record->duration_hours ?: 24));
                        $user->update([
                            'user_status' => 'suspend',
                            'suspended_until' => $until,
                            'status_reason' => $record->reason,
                        ]);

                        $record->update([
                            'action' => 'suspend',
                            'admin_executor_id' => auth()->id(),
                            'effective_from' => $record->effective_from ?: now(),
                            'effective_until' => $until,
                        ]);

                        AuditService::log('admin', auth()->id(), 'violation.suspend_applied', 'violations', (int) $record->id);
                    }),
                Actions\Action::make('apply_ban')
                    ->label('Ban User')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (Violation $record): bool => (bool) auth()->user()?->canAdmin('ops')
                        && $record->user !== null
                        && (string) $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function (Violation $record): void {
                        $user = $record->user;
                        if (! $user instanceof User || $user->isAdminUser()) {
                            return;
                        }

                        $user->update([
                            'user_status' => 'banned',
                            'suspended_until' => null,
                            'status_reason' => $record->reason,
                        ]);

                        $record->update([
                            'action' => 'ban',
                            'admin_executor_id' => auth()->id(),
                            'effective_from' => $record->effective_from ?: now(),
                        ]);

                        AuditService::log('admin', auth()->id(), 'violation.ban_applied', 'violations', (int) $record->id);
                    }),
                Actions\Action::make('resolve')
                    ->label('Selesaikan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (Violation $record): bool => (bool) auth()->user()?->canAdmin('ops')
                        && (string) $record->status === 'active')
                    ->requiresConfirmation()
                    ->action(function (Violation $record): void {
                        $record->update([
                            'status' => 'resolved',
                            'resolved_at' => now(),
                            'admin_executor_id' => $record->admin_executor_id ?: auth()->id(),
                        ]);

                        AuditService::log('admin', auth()->id(), 'violation.resolved', 'violations', (int) $record->id);
                    }),
                Actions\DeleteAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListViolations::route('/'),
            'create' => Pages\CreateViolation::route('/create'),
            'edit' => Pages\EditViolation::route('/{record}/edit'),
        ];
    }
}
