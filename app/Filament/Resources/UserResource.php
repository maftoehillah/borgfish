<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use App\Models\Violation;
use App\Services\AuditService;
use BackedEnum;
use Filament\Actions;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables;
use Filament\Tables\Columns\BadgeColumn;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use UnitEnum;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static bool $shouldRegisterNavigation = false;

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-users';

    protected static string|UnitEnum|null $navigationGroup = 'Pengguna';

    protected static ?string $modelLabel = 'Pengguna';

    protected static ?string $pluralModelLabel = 'Pengguna';

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
                Select::make('role')->options([
                    'penjual' => 'Penjual',
                    'pembeli' => 'Pembeli',
                ])->required(),
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
                TextInput::make('google_id')->label('Google ID')->maxLength(191),
                TextInput::make('whatsapp_number')->label('Nomor WhatsApp')->maxLength(32),
                DateTimePicker::make('whatsapp_verified_at')->label('WA Terverifikasi'),
                Select::make('user_status')
                    ->label('Status User')
                    ->options([
                        'active' => 'Aktif',
                        'suspend' => 'Suspend',
                        'banned' => 'Banned',
                        'deleted' => 'Dihapus',
                    ])
                    ->default('active')
                    ->required(),
                DateTimePicker::make('suspended_until')->label('Suspend Sampai'),
                Textarea::make('status_reason')->label('Alasan Status')->maxLength(1000)->columnSpanFull(),
                DateTimePicker::make('last_login_at')->label('Login Terakhir')->disabled()->dehydrated(false),
                DateTimePicker::make('last_otp_verified_at')->label('OTP Terakhir')->disabled()->dehydrated(false),
                DateTimePicker::make('onboarding_completed_at')->label('Onboarding Selesai'),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('Nama')->searchable()->sortable(),
                TextColumn::make('email')->searchable(),
                BadgeColumn::make('role')->colors([
                    'warning' => 'penjual',
                    'info' => 'pembeli',
                ]),
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
                TextColumn::make('whatsapp_number')->label('WhatsApp')->searchable()->placeholder('-'),
                TextColumn::make('whatsapp_verified_at')->label('WA Verified')->dateTime('d M Y H:i')->placeholder('-')->toggleable(),
                TextColumn::make('suspended_until')->label('Suspend Sampai')->dateTime('d M Y H:i')->placeholder('-')->toggleable(),
                TextColumn::make('last_login_at')->label('Login Terakhir')->dateTime('d M Y H:i')->placeholder('-')->toggleable(),
                TextColumn::make('ikans_count')->label('Ikan')->counts('ikans'),
                TextColumn::make('bids_count')->label('Bid')->counts('bids'),
                TextColumn::make('created_at')->label('Daftar')->date('d M Y')->sortable(),
            ])
            ->filters([
                SelectFilter::make('role')->options([
                    'penjual' => 'Penjual',
                    'pembeli' => 'Pembeli',
                ]),
            ])
            ->actions([
                Actions\EditAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->canAdmin('ops')),
                Actions\Action::make('suspend_user')
                    ->label('Suspend')
                    ->icon('heroicon-o-pause-circle')
                    ->color('warning')
                    ->visible(fn (User $record): bool => (bool) auth()->user()?->canAdmin('ops')
                        && ! $record->isAdminUser()
                        && ! in_array((string) $record->user_status, ['banned', 'deleted'], true))
                    ->form([
                        TextInput::make('duration_hours')
                            ->label('Durasi Jam')
                            ->numeric()
                            ->minValue(1)
                            ->default(24)
                            ->required(),
                        Textarea::make('reason')
                            ->label('Alasan')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (User $record, array $data): void {
                        $durationHours = max(1, (int) ($data['duration_hours'] ?? 24));
                        $until = now()->addHours($durationHours);
                        $reason = (string) ($data['reason'] ?? 'Suspend manual admin.');

                        $record->update([
                            'user_status' => 'suspend',
                            'suspended_until' => $until,
                            'status_reason' => $reason,
                        ]);

                        Violation::create([
                            'user_id' => $record->id,
                            'admin_executor_id' => auth()->id(),
                            'role' => (string) $record->role,
                            'type' => 'manual_admin',
                            'status' => 'active',
                            'action' => 'suspend',
                            'reason' => $reason,
                            'duration_hours' => $durationHours,
                            'effective_from' => now(),
                            'effective_until' => $until,
                        ]);

                        AuditService::log('admin', auth()->id(), 'user.suspended', 'users', (int) $record->id, [
                            'duration_hours' => $durationHours,
                            'reason' => $reason,
                        ]);
                    }),
                Actions\Action::make('ban_user')
                    ->label('Ban')
                    ->icon('heroicon-o-no-symbol')
                    ->color('danger')
                    ->visible(fn (User $record): bool => (bool) auth()->user()?->canAdmin('ops')
                        && ! $record->isAdminUser()
                        && ! in_array((string) $record->user_status, ['banned', 'deleted'], true))
                    ->form([
                        Textarea::make('reason')
                            ->label('Alasan')
                            ->required()
                            ->maxLength(1000),
                    ])
                    ->requiresConfirmation()
                    ->action(function (User $record, array $data): void {
                        $reason = (string) ($data['reason'] ?? 'Ban permanen oleh admin.');

                        $record->update([
                            'user_status' => 'banned',
                            'suspended_until' => null,
                            'status_reason' => $reason,
                        ]);

                        Violation::create([
                            'user_id' => $record->id,
                            'admin_executor_id' => auth()->id(),
                            'role' => (string) $record->role,
                            'type' => 'manual_admin',
                            'status' => 'active',
                            'action' => 'ban',
                            'reason' => $reason,
                            'effective_from' => now(),
                        ]);

                        AuditService::log('admin', auth()->id(), 'user.banned', 'users', (int) $record->id, [
                            'reason' => $reason,
                        ]);
                    }),
                Actions\Action::make('activate_user')
                    ->label('Aktifkan')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (User $record): bool => (bool) auth()->user()?->canAdmin('ops')
                        && ! $record->isAdminUser()
                        && (string) $record->user_status !== 'active')
                    ->requiresConfirmation()
                    ->action(function (User $record): void {
                        $record->update([
                            'user_status' => 'active',
                            'suspended_until' => null,
                            'status_reason' => null,
                        ]);

                        Violation::query()
                            ->where('user_id', $record->id)
                            ->where('status', 'active')
                            ->update([
                                'status' => 'resolved',
                                'resolved_at' => now(),
                                'updated_at' => now(),
                            ]);

                        AuditService::log('admin', auth()->id(), 'user.activated', 'users', (int) $record->id);
                    }),
                Actions\DeleteAction::make()
                    ->visible(fn (): bool => (bool) auth()->user()?->isSuperAdmin()),
            ])
            ->defaultSort('created_at', 'desc');
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'create' => Pages\CreateUser::route('/create'),
            'edit' => Pages\EditUser::route('/{record}/edit'),
        ];
    }
}
