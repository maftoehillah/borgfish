<?php

namespace App\Filament\Pages;

use App\Services\PembayaranService;
use App\Services\SystemSettingService;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions as SchemaActions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Enums\Alignment;
use Illuminate\Contracts\Support\Htmlable;
use UnitEnum;

/**
 * @property-read Schema $form
 */
class SystemSettings extends Page
{
    protected static ?string $slug = 'setting-sistem';

    protected static string|BackedEnum|null $navigationIcon = 'heroicon-o-cog-6-tooth';

    protected static string|UnitEnum|null $navigationGroup = 'Sistem';

    protected static ?string $navigationLabel = 'Setting Sistem';

    protected static ?int $navigationSort = 100;

    /**
     * @var array<string, mixed>
     */
    public ?array $data = [];

    public static function canAccess(): bool
    {
        return (bool) auth()->user()?->isSuperAdmin();
    }

    public function mount(): void
    {
        $settings = app(SystemSettingService::class)->all();
        $paymentService = app(PembayaranService::class);
        $paymentMethods = $paymentService->availableMethods();

        $this->form->fill([
            'site_name' => $settings['site_name'] ?? 'Borgfish',
            'site_address' => $settings['site_address'] ?? '',
            'site_email' => $settings['site_email'] ?? '',
            'site_admin_contact' => $settings['site_admin_contact'] ?? $settings['admin_contact'] ?? '',
            'about_text' => $settings['about_text'] ?? '',
            'contact_page' => $settings['contact_page'] ?? '',
            'privacy_policy' => $settings['privacy_policy'] ?? '',
            'terms_conditions' => $settings['terms_conditions'] ?? '',
            'payment_policy' => $settings['payment_policy'] ?? '',
            'social_facebook_url' => $settings['social_facebook_url'] ?? '',
            'social_instagram_url' => $settings['social_instagram_url'] ?? '',
            'social_tiktok_url' => $settings['social_tiktok_url'] ?? '',
            'default_payment_method' => $paymentService->defaultMethod($paymentMethods),
            'payment_deadline_minutes' => app(SystemSettingService::class)->paymentDeadlineMinutes(),
            'anti_sniping_enabled_default' => app(SystemSettingService::class)->antiSnipingEnabledByDefault(),
            'anti_sniping_window_seconds' => app(SystemSettingService::class)->antiSnipingWindowSeconds(),
            'anti_sniping_extend_seconds' => app(SystemSettingService::class)->antiSnipingExtendSeconds(),
            'anti_sniping_max_extensions' => app(SystemSettingService::class)->antiSnipingMaxExtensions(),
            'settlement_auto_create_enabled' => app(SystemSettingService::class)->settlementAutoCreateEnabled(),
            'settlement_payout_delay_days' => app(SystemSettingService::class)->settlementPayoutDelayDays(),
            'settlement_min_payout_amount' => app(SystemSettingService::class)->settlementMinPayoutAmount(),
            'settlement_requires_admin_review' => app(SystemSettingService::class)->settlementRequiresAdminReview(),
            'settlement_hold_on_dispute' => app(SystemSettingService::class)->settlementHoldOnDispute(),
            'settlement_hold_on_violation' => app(SystemSettingService::class)->settlementHoldOnViolation(),
            'whatsapp_sender_name' => $settings['whatsapp_sender_name'] ?? config('whatsapp.sender_name', 'Borgfish'),
            'whatsapp_fail_silently' => (bool) ($settings['whatsapp_fail_silently'] ?? config('whatsapp.fail_silently', false)),
            'whatsapp_admin_contact_template' => $settings['whatsapp_admin_contact_template'] ?? app(SystemSettingService::class)->whatsappAdminContactTemplate(),
            'otp_ttl_minutes' => (int) ($settings['otp_ttl_minutes'] ?? config('marketplace.otp.ttl_minutes', 5)),
            'otp_max_attempts' => (int) ($settings['otp_max_attempts'] ?? config('marketplace.otp.max_attempts', 5)),
            'otp_max_resend' => (int) ($settings['otp_max_resend'] ?? config('marketplace.otp.max_resend', 3)),
            'otp_rate_limit_per_hour' => (int) ($settings['otp_rate_limit_per_hour'] ?? config('marketplace.otp.rate_limit_per_hour', 6)),
            'otp_rate_limit_per_number_per_hour' => (int) ($settings['otp_rate_limit_per_number_per_hour'] ?? config('marketplace.otp.rate_limit_per_number_per_hour', config('marketplace.otp.rate_limit_per_hour', 6))),
            'otp_resend_cooldown_seconds' => (int) ($settings['otp_resend_cooldown_seconds'] ?? config('marketplace.otp.resend_cooldown_seconds', 60)),
            'maintenance_enabled' => (bool) ($settings['maintenance_enabled'] ?? config('maintenance-page.enabled', false)),
            'maintenance_status' => (int) ($settings['maintenance_status'] ?? config('maintenance-page.status', 503)),
            'maintenance_html_path' => $settings['maintenance_html_path'] ?? config('maintenance-page.html_path', public_path('maintenance.html')),
            'maintenance_except' => is_array($settings['maintenance_except'] ?? null)
                ? implode(PHP_EOL, $settings['maintenance_except'])
                : (string) ($settings['maintenance_except'] ?? implode(PHP_EOL, (array) config('maintenance-page.except', ['up', 'api/tripay/callback']))),
            'maintenance_message' => $settings['maintenance_message'] ?? 'Situs sedang maintenance. Silakan coba lagi beberapa saat lagi.',
        ]);
    }

    public function getTitle(): string|Htmlable
    {
        return 'Setting Sistem';
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Identitas Website')
                    ->description('Data yang tampil di footer dan halaman informasi website.')
                    ->icon('heroicon-o-building-storefront')
                    ->columns(2)
                    ->components([
                        TextInput::make('site_name')
                            ->label('Nama Website')
                            ->required()
                            ->maxLength(80),
                        TextInput::make('site_email')
                            ->label('Email Website')
                            ->email()
                            ->required()
                            ->maxLength(120),
                        Textarea::make('site_address')
                            ->label('Alamat Website')
                            ->rows(3)
                            ->required()
                            ->maxLength(1000)
                            ->columnSpanFull(),
                    ]),
                Section::make('Social Media')
                    ->description('Link akun resmi Borgfish dan nomor WhatsApp admin yang tampil di halaman kontak.')
                    ->icon('heroicon-o-megaphone')
                    ->columns(2)
                    ->components([
                        TextInput::make('site_admin_contact')
                            ->label('Nomor WhatsApp Admin')
                            ->tel()
                            ->maxLength(32)
                            ->placeholder('+62 838-7304-1404')
                            ->helperText('Tombol Hubungi Admin di halaman kontak hanya tampil jika nomor ini diisi.'),
                        TextInput::make('social_facebook_url')
                            ->label('URL Facebook')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://facebook.com/username')
                            ->helperText('Kosongkan jika belum dipakai.'),
                        TextInput::make('social_instagram_url')
                            ->label('URL Instagram')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://instagram.com/username')
                            ->helperText('Kosongkan jika belum dipakai.'),
                        TextInput::make('social_tiktok_url')
                            ->label('URL TikTok')
                            ->url()
                            ->maxLength(255)
                            ->placeholder('https://www.tiktok.com/@username')
                            ->helperText('Kosongkan jika belum dipakai.'),
                    ]),
                Section::make('Halaman Informasi')
                    ->description('Isi halaman standar website. Pisahkan paragraf dengan baris baru.')
                    ->icon('heroicon-o-document-text')
                    ->components([
                        Textarea::make('about_text')
                            ->label('Tentang Kami')
                            ->rows(4)
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('contact_page')
                            ->label('Kontak')
                            ->rows(4)
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('privacy_policy')
                            ->label('Kebijakan Privasi')
                            ->rows(5)
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('terms_conditions')
                            ->label('Syarat & Ketentuan')
                            ->rows(5)
                            ->required()
                            ->columnSpanFull(),
                        Textarea::make('payment_policy')
                            ->label('Kebijakan Pembayaran')
                            ->rows(5)
                            ->required()
                            ->columnSpanFull(),
                    ]),
                Section::make('Pembayaran')
                    ->description('Pengaturan umum invoice dan metode pembayaran TriPay.')
                    ->icon('heroicon-o-credit-card')
                    ->columns(2)
                    ->components([
                        Radio::make('default_payment_method')
                            ->label('Metode Pembayaran Default')
                            ->options(fn (): array => $this->paymentMethodOptions())
                            ->required()
                            ->helperText('Metode ini akan otomatis dipilih duluan di halaman pembayaran pembeli.')
                            ->columnSpanFull(),
                        TextInput::make('payment_deadline_minutes')
                            ->label('Deadline Pembayaran')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(240)
                            ->suffix('menit')
                            ->required()
                            ->helperText('Berlaku untuk lot baru setelah lelang selesai. Rekomendasi sistem: 30 menit.'),
                    ]),
                Section::make('Aturan Lelang')
                    ->description('Aturan anti-sniping default untuk lot baru. Penjual cukup mengaktifkan atau menonaktifkan fiturnya.')
                    ->icon('heroicon-o-bolt')
                    ->columns(2)
                    ->components([
                        Toggle::make('anti_sniping_enabled_default')
                            ->label('Aktif Default untuk Lot Baru')
                            ->helperText('Jika aktif, form seller akan menyalakan anti-sniping secara default.'),
                        TextInput::make('anti_sniping_window_seconds')
                            ->label('Window Anti-Sniping')
                            ->numeric()
                            ->minValue(30)
                            ->maxValue(600)
                            ->suffix('detik')
                            ->required(),
                        TextInput::make('anti_sniping_extend_seconds')
                            ->label('Durasi Perpanjangan')
                            ->numeric()
                            ->minValue(30)
                            ->maxValue(600)
                            ->suffix('detik')
                            ->required(),
                        TextInput::make('anti_sniping_max_extensions')
                            ->label('Maksimal Perpanjangan')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->required()
                            ->helperText('Contoh yang aman: window 60 detik, perpanjangan 90 detik, maksimal 3 kali.'),
                    ]),
                Section::make('Settlement Seller')
                    ->description('Aturan global untuk pembentukan dan pencairan dana seller setelah transaksi selesai.')
                    ->icon('heroicon-o-banknotes')
                    ->columns(2)
                    ->components([
                        Toggle::make('settlement_auto_create_enabled')
                            ->label('Buat Settlement Otomatis')
                            ->helperText('Jika aktif, settlement seller dibuat otomatis saat transaksi selesai dan data rekening seller lengkap.'),
                        Toggle::make('settlement_requires_admin_review')
                            ->label('Wajib Review Admin')
                            ->helperText('Jika aktif, settlement baru akan dibuat dengan status pending review.'),
                        TextInput::make('settlement_payout_delay_days')
                            ->label('Delay Pencairan')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(30)
                            ->suffix('hari')
                            ->required()
                            ->helperText('Gunakan 0 jika settlement boleh langsung siap diproses setelah transaksi selesai.'),
                        TextInput::make('settlement_min_payout_amount')
                            ->label('Minimal Nominal Payout')
                            ->numeric()
                            ->minValue(0)
                            ->prefix('Rp')
                            ->required()
                            ->helperText('Settlement di bawah nilai ini akan ditahan untuk review manual.'),
                        Toggle::make('settlement_hold_on_dispute')
                            ->label('Tahan Jika Ada Sengketa Aktif')
                            ->helperText('Settlement otomatis ditahan jika transaksi masih punya sengketa aktif.'),
                        Toggle::make('settlement_hold_on_violation')
                            ->label('Tahan Jika Seller Punya Pelanggaran Aktif')
                            ->helperText('Settlement otomatis ditahan jika seller masih punya violation aktif.'),
                    ]),
                Section::make('Notifikasi & OTP')
                    ->description('Aturan dasar pengiriman WhatsApp dan batas keamanan OTP.')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->columns(2)
                    ->components([
                        TextInput::make('whatsapp_sender_name')
                            ->label('Nama Pengirim WhatsApp')
                            ->required()
                            ->maxLength(80),
                        Toggle::make('whatsapp_fail_silently')
                            ->label('WhatsApp Fail Silently')
                            ->helperText('Aktifkan hanya jika kegagalan provider tidak boleh memblokir alur tertentu.'),
                        Textarea::make('whatsapp_admin_contact_template')
                            ->label('Template Chat Hubungi Admin')
                            ->rows(6)
                            ->required()
                            ->helperText('Placeholder yang tersedia: {site_name}, {user_role}, {user_name}, {user_email}, {user_phone}, {transaction_id}, {current_url}.')
                            ->columnSpanFull(),
                        TextInput::make('otp_ttl_minutes')
                            ->label('OTP Berlaku')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(60)
                            ->suffix('menit')
                            ->required(),
                        TextInput::make('otp_max_attempts')
                            ->label('Maksimal Coba OTP')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(20)
                            ->required(),
                        TextInput::make('otp_max_resend')
                            ->label('Maksimal Kirim Ulang OTP')
                            ->numeric()
                            ->minValue(0)
                            ->maxValue(20)
                            ->required(),
                        TextInput::make('otp_resend_cooldown_seconds')
                            ->label('Cooldown Kirim Ulang OTP')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(3600)
                            ->suffix('detik')
                            ->required(),
                        TextInput::make('otp_rate_limit_per_hour')
                            ->label('Rate Limit OTP per Akun')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('/jam')
                            ->required(),
                        TextInput::make('otp_rate_limit_per_number_per_hour')
                            ->label('Rate Limit OTP per Nomor')
                            ->numeric()
                            ->minValue(1)
                            ->maxValue(100)
                            ->suffix('/jam')
                            ->required(),
                    ]),
                Section::make('Maintenance')
                    ->description('Kontrol emergency maintenance untuk marketplace tanpa perlu edit file env.')
                    ->icon('heroicon-o-wrench-screwdriver')
                    ->columns(2)
                    ->components([
                        Toggle::make('maintenance_enabled')
                            ->label('Aktifkan Maintenance Mode')
                            ->helperText('Jika aktif, halaman publik akan menampilkan halaman maintenance kecuali route yang dikecualikan.')
                            ->columnSpanFull(),
                        TextInput::make('maintenance_status')
                            ->label('HTTP Status')
                            ->numeric()
                            ->minValue(200)
                            ->maxValue(599)
                            ->required(),
                        TextInput::make('maintenance_html_path')
                            ->label('Path HTML Maintenance')
                            ->maxLength(255)
                            ->helperText('Kosongkan untuk memakai file default di folder public bila tersedia.'),
                        Textarea::make('maintenance_except')
                            ->label('Route Yang Dikecualikan')
                            ->rows(4)
                            ->helperText('Satu pattern per baris. Contoh: up atau api/tripay/callback')
                            ->columnSpanFull(),
                        Textarea::make('maintenance_message')
                            ->label('Pesan Fallback Maintenance')
                            ->rows(4)
                            ->required()
                            ->columnSpanFull(),
                    ]),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Form::make([
                    EmbeddedSchema::make('form'),
                ])
                    ->id('system-settings-form')
                    ->livewireSubmitHandler('save')
                    ->footer([
                        SchemaActions::make([
                            Action::make('save')
                                ->label('Simpan Setting')
                                ->submit('save')
                                ->keyBindings(['mod+s']),
                        ])
                            ->alignment(Alignment::Start),
                    ]),
            ]);
    }

    public function save(SystemSettingService $settings): void
    {
        $data = $this->form->getState();
        $adminContactValue = null;

        foreach ($this->settingDefinitions() as $key => [$group, $type]) {
            $value = $data[$key] ?? '';

            if ($type === 'boolean') {
                $value = (bool) $value;
            } elseif ($key === 'maintenance_except') {
                $value = collect(preg_split('/\R+/', (string) $value) ?: [])
                    ->map(fn (string $line): string => trim($line))
                    ->filter()
                    ->values()
                    ->all();
            } elseif ($type === 'integer') {
                $value = $this->normalizeIntegerSetting($key, (int) $value);
            } else {
                $value = trim((string) $value);
            }

            $settings->put($key, $value, $group, $type);

            if ($key === 'site_admin_contact') {
                $adminContactValue = $value;
            }
        }

        if ($adminContactValue !== null) {
            $settings->put('admin_contact', $adminContactValue, 'site', 'string');
        }

        Notification::make()
            ->title('Setting sistem tersimpan')
            ->body('Perubahan sudah disinkronkan ke tampilan website dan flow pembayaran.')
            ->success()
            ->send();
    }

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    private function settingDefinitions(): array
    {
        return [
            'site_name' => ['site', 'string'],
            'site_address' => ['site', 'text'],
            'site_email' => ['site', 'string'],
            'site_admin_contact' => ['site', 'string'],
            'about_text' => ['site', 'text'],
            'contact_page' => ['legal', 'longtext'],
            'privacy_policy' => ['legal', 'longtext'],
            'terms_conditions' => ['legal', 'longtext'],
            'payment_policy' => ['legal', 'longtext'],
            'social_facebook_url' => ['site', 'string'],
            'social_instagram_url' => ['site', 'string'],
            'social_tiktok_url' => ['site', 'string'],
            'default_payment_method' => ['payment', 'string'],
            'payment_deadline_minutes' => ['payment', 'integer'],
            'anti_sniping_enabled_default' => ['auction', 'boolean'],
            'anti_sniping_window_seconds' => ['auction', 'integer'],
            'anti_sniping_extend_seconds' => ['auction', 'integer'],
            'anti_sniping_max_extensions' => ['auction', 'integer'],
            'settlement_auto_create_enabled' => ['settlement', 'boolean'],
            'settlement_payout_delay_days' => ['settlement', 'integer'],
            'settlement_min_payout_amount' => ['settlement', 'integer'],
            'settlement_requires_admin_review' => ['settlement', 'boolean'],
            'settlement_hold_on_dispute' => ['settlement', 'boolean'],
            'settlement_hold_on_violation' => ['settlement', 'boolean'],
            'whatsapp_sender_name' => ['notification', 'string'],
            'whatsapp_fail_silently' => ['notification', 'boolean'],
            'whatsapp_admin_contact_template' => ['notification', 'longtext'],
            'otp_ttl_minutes' => ['security', 'integer'],
            'otp_max_attempts' => ['security', 'integer'],
            'otp_max_resend' => ['security', 'integer'],
            'otp_rate_limit_per_hour' => ['security', 'integer'],
            'otp_rate_limit_per_number_per_hour' => ['security', 'integer'],
            'otp_resend_cooldown_seconds' => ['security', 'integer'],
            'maintenance_enabled' => ['maintenance', 'boolean'],
            'maintenance_status' => ['maintenance', 'integer'],
            'maintenance_html_path' => ['maintenance', 'string'],
            'maintenance_except' => ['maintenance', 'json'],
            'maintenance_message' => ['maintenance', 'longtext'],
        ];
    }

    /**
     * @return array<string, string>
     */
    private function paymentMethodOptions(): array
    {
        $methods = app(PembayaranService::class)->availableMethods();

        return $methods !== [] ? $methods : ['QRIS' => 'QRIS'];
    }

    private function normalizeIntegerSetting(string $key, int $value): int
    {
        return match ($key) {
            'payment_deadline_minutes' => max(1, min(240, $value)),
            'anti_sniping_window_seconds', 'anti_sniping_extend_seconds' => max(30, min(600, $value)),
            'anti_sniping_max_extensions' => max(1, min(20, $value)),
            'settlement_payout_delay_days' => max(0, min(30, $value)),
            'settlement_min_payout_amount' => max(0, min(1000000000, $value)),
            'otp_ttl_minutes' => max(1, min(60, $value)),
            'otp_max_attempts' => max(1, min(20, $value)),
            'otp_max_resend' => max(0, min(20, $value)),
            'otp_rate_limit_per_hour', 'otp_rate_limit_per_number_per_hour' => max(1, min(100, $value)),
            'otp_resend_cooldown_seconds' => max(1, min(3600, $value)),
            'maintenance_status' => max(200, min(599, $value)),
            default => $value,
        };
    }

}
