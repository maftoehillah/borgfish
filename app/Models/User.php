<?php

namespace App\Models;

use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable implements FilamentUser
{
    use HasFactory, Notifiable;

    public const SUPERADMIN_EMAIL = 'sabiqmaftu@gmail.com';

    public const ADMIN_ROLE_SUPERADMIN = 'superadmin';

    public const ADMIN_ROLE_FINANCE = 'finance';

    public const ADMIN_ROLE_OPS = 'ops';

    public const ADMIN_ROLE_SUPPORT = 'support';

    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'is_admin',
        'admin_role',
        'google_id',
        'auth_provider',
        'google_avatar',
        'whatsapp_number',
        'whatsapp_verified_at',
        'user_status',
        'suspended_until',
        'status_reason',
        'last_login_at',
        'last_otp_verified_at',
        'onboarding_completed_at',
    ];

    protected $hidden = ['password', 'remember_token'];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'is_admin' => 'boolean',
        'whatsapp_verified_at' => 'datetime',
        'suspended_until' => 'datetime',
        'last_login_at' => 'datetime',
        'last_otp_verified_at' => 'datetime',
        'onboarding_completed_at' => 'datetime',
    ];

    public function canAccessPanel(Panel $panel): bool
    {
        return $this->isPanelAdmin();
    }

    public function isWhitelistedAdminEmail(): bool
    {
        $email = strtolower(trim((string) $this->email));

        if ($email === '') {
            return false;
        }

        $superadminEmail = strtolower((string) config('app.superadmin_email', self::SUPERADMIN_EMAIL));
        $adminEmails = collect(config('marketplace.admin_whitelist', []))
            ->push($superadminEmail)
            ->map(fn (string $value): string => strtolower(trim($value)))
            ->filter()
            ->unique();

        return $adminEmails->contains($email);
    }

    public function isSuperAdmin(): bool
    {
        $superadminEmail = strtolower((string) config('app.superadmin_email', self::SUPERADMIN_EMAIL));
        $emailMatch = strtolower((string) $this->email) === $superadminEmail;

        return $emailMatch && $this->is_admin === true;
    }

    public function adminRole(): string
    {
        if ($this->isSuperAdmin()) {
            return self::ADMIN_ROLE_SUPERADMIN;
        }

        $role = strtolower(trim((string) ($this->admin_role ?? '')));

        return in_array($role, [
            self::ADMIN_ROLE_FINANCE,
            self::ADMIN_ROLE_OPS,
            self::ADMIN_ROLE_SUPPORT,
        ], true) ? $role : self::ADMIN_ROLE_SUPPORT;
    }

    public function adminRoleLabel(): string
    {
        return match ($this->adminRole()) {
            self::ADMIN_ROLE_SUPERADMIN => 'Superadmin',
            self::ADMIN_ROLE_FINANCE => 'Finance',
            self::ADMIN_ROLE_OPS => 'Operasional',
            self::ADMIN_ROLE_SUPPORT => 'Support',
            default => 'Support',
        };
    }

    public function canAdmin(string $scope): bool
    {
        if (! $this->isPanelAdmin()) {
            return false;
        }

        if ($this->isSuperAdmin()) {
            return true;
        }

        $role = $this->adminRole();

        return match ($scope) {
            'finance' => $role === self::ADMIN_ROLE_FINANCE,
            'ops' => $role === self::ADMIN_ROLE_OPS,
            'support' => in_array($role, [self::ADMIN_ROLE_SUPPORT, self::ADMIN_ROLE_OPS, self::ADMIN_ROLE_FINANCE], true),
            'settings', 'admin_users' => false,
            default => false,
        };
    }

    public function isPanelAdmin(): bool
    {
        return $this->is_admin === true && $this->isWhitelistedAdminEmail();
    }

    public function isPenjual(): bool
    {
        return $this->role === 'penjual';
    }

    public function isPembeli(): bool
    {
        return $this->role === 'pembeli';
    }

    public function superAdminViewMode(): string
    {
        $mode = strtoupper((string) session('superadmin_view_mode', 'PEMBELI'));

        return in_array($mode, ['PEMBELI', 'PENJUAL'], true) ? $mode : 'PEMBELI';
    }

    public function canActAsPembeli(): bool
    {
        if ($this->isSuperAdmin()) {
            return $this->superAdminViewMode() === 'PEMBELI';
        }

        return $this->isPembeli();
    }

    public function canActAsPenjual(): bool
    {
        if ($this->isSuperAdmin()) {
            return $this->superAdminViewMode() === 'PENJUAL';
        }

        return $this->isPenjual();
    }

    public function isAdminUser(): bool
    {
        return $this->isPanelAdmin();
    }

    public function hasVerifiedWhatsapp(): bool
    {
        return $this->whatsapp_verified_at !== null
            && filled($this->whatsapp_number);
    }

    public function hasCompletedRequiredAccountData(): bool
    {
        if ($this->isAdminUser()) {
            return true;
        }

        if (blank($this->whatsapp_number)) {
            return false;
        }

        if ($this->isPenjual()) {
            $profile = $this->sellerProfile;

            return $profile
                && filled($profile->store_name)
                && filled($profile->full_address)
                && filled($profile->store_latitude)
                && filled($profile->store_longitude)
                && filled($profile->store_photo_path)
                && filled($profile->bank_name)
                && filled($profile->bank_account_number)
                && filled($profile->bank_account_name);
        }

        return true;
    }

    public function isActive(): bool
    {
        if ((string) $this->user_status === 'active') {
            return true;
        }

        if ((string) $this->user_status === 'suspend' && $this->suspended_until && now()->gte($this->suspended_until)) {
            return true;
        }

        return false;
    }

    public function isSuspended(): bool
    {
        return (string) $this->user_status === 'suspend'
            && (! $this->suspended_until || now()->lt($this->suspended_until));
    }

    public function isBanned(): bool
    {
        return (string) $this->user_status === 'banned';
    }

    public function isDeleted(): bool
    {
        return (string) $this->user_status === 'deleted';
    }

    public function needsOnboarding(): bool
    {
        if ($this->isAdminUser()) {
            return false;
        }

        if (! $this->hasCompletedRequiredAccountData()) {
            return true;
        }

        if (! $this->hasVerifiedWhatsapp()) {
            return true;
        }

        return false;
    }

    public function displayRoleLabel(): string
    {
        return match (true) {
            $this->isAdminUser() => 'Admin ' . $this->adminRoleLabel(),
            $this->isPenjual() => 'Penjual',
            default => 'Pembeli',
        };
    }

    public function initials(): string
    {
        return Str::of((string) $this->name)
            ->explode(' ')
            ->filter()
            ->take(2)
            ->map(fn (string $part) => Str::upper(Str::substr($part, 0, 1)))
            ->implode('');
    }

    public function ikans(): HasMany
    {
        return $this->hasMany(Ikan::class);
    }

    public function bids()
    {
        return $this->hasMany(Bid::class);
    }

    public function sellerProfile(): HasOne
    {
        return $this->hasOne(SellerProfile::class);
    }

    public function transaksis()
    {
        return $this->hasMany(Transaksi::class, 'pemenang_id');
    }

    public function inAppNotifications()
    {
        return $this->hasMany(InAppNotification::class)->orderByDesc('id');
    }

    public function unreadInAppNotifications()
    {
        return $this->inAppNotifications()->whereNull('read_at');
    }

    public function receivedOutboxNotifications()
    {
        return $this->hasMany(NotificationOutbox::class, 'recipient_user_id')->orderByDesc('id');
    }

    public function buyerDisputes()
    {
        return $this->hasMany(TransactionDispute::class, 'buyer_id')->orderByDesc('id');
    }

    public function sellerDisputes()
    {
        return $this->hasMany(TransactionDispute::class, 'seller_id')->orderByDesc('id');
    }

    public function otpChallenges(): HasMany
    {
        return $this->hasMany(WhatsappOtpChallenge::class)->orderByDesc('id');
    }

    public function violations(): HasMany
    {
        return $this->hasMany(Violation::class)->orderByDesc('id');
    }

    public function sellerSettlements(): HasMany
    {
        return $this->hasMany(SellerSettlement::class, 'seller_id')->orderByDesc('id');
    }

    public function createdSettlementBatches(): HasMany
    {
        return $this->hasMany(SellerSettlementBatch::class, 'created_by_id')->orderByDesc('id');
    }
}
