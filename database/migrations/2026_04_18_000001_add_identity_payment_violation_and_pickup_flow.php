<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $userColumns = [
            'google_id' => ! Schema::hasColumn('users', 'google_id'),
            'auth_provider' => ! Schema::hasColumn('users', 'auth_provider'),
            'google_avatar' => ! Schema::hasColumn('users', 'google_avatar'),
            'whatsapp_number' => ! Schema::hasColumn('users', 'whatsapp_number'),
            'whatsapp_verified_at' => ! Schema::hasColumn('users', 'whatsapp_verified_at'),
            'user_status' => ! Schema::hasColumn('users', 'user_status'),
            'suspended_until' => ! Schema::hasColumn('users', 'suspended_until'),
            'status_reason' => ! Schema::hasColumn('users', 'status_reason'),
            'last_login_at' => ! Schema::hasColumn('users', 'last_login_at'),
            'last_otp_verified_at' => ! Schema::hasColumn('users', 'last_otp_verified_at'),
            'onboarding_completed_at' => ! Schema::hasColumn('users', 'onboarding_completed_at'),
        ];

        Schema::table('users', function (Blueprint $table) use ($userColumns): void {
            if ($userColumns['google_id']) {
                $table->string('google_id')->nullable()->after('email');
            }

            if ($userColumns['auth_provider']) {
                $table->string('auth_provider', 32)->default('google')->after('google_id');
            }

            if ($userColumns['google_avatar']) {
                $table->text('google_avatar')->nullable()->after('auth_provider');
            }

            if ($userColumns['whatsapp_number']) {
                $table->string('whatsapp_number', 32)->nullable()->after('google_avatar');
            }

            if ($userColumns['whatsapp_verified_at']) {
                $table->timestamp('whatsapp_verified_at')->nullable()->after('whatsapp_number');
            }

            if ($userColumns['user_status']) {
                $table->string('user_status', 20)->default('active')->after('whatsapp_verified_at');
            }

            if ($userColumns['suspended_until']) {
                $table->timestamp('suspended_until')->nullable()->after('user_status');
            }

            if ($userColumns['status_reason']) {
                $table->text('status_reason')->nullable()->after('suspended_until');
            }

            if ($userColumns['last_login_at']) {
                $table->timestamp('last_login_at')->nullable()->after('status_reason');
            }

            if ($userColumns['last_otp_verified_at']) {
                $table->timestamp('last_otp_verified_at')->nullable()->after('last_login_at');
            }

            if ($userColumns['onboarding_completed_at']) {
                $table->timestamp('onboarding_completed_at')->nullable()->after('last_otp_verified_at');
            }
        });

        try {
            if (! $this->indexExists('users', 'users_google_id_unique')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->unique('google_id');
                });
            }
        } catch (\Throwable) {
            // Ignore index creation issues when database already contains legacy duplicates.
        }

        try {
            if (! $this->indexExists('users', 'users_whatsapp_number_unique')) {
                Schema::table('users', function (Blueprint $table): void {
                    $table->unique('whatsapp_number');
                });
            }
        } catch (\Throwable) {
            // Ignore index creation issues when database already contains legacy duplicates.
        }

        if (! Schema::hasTable('seller_profiles')) {
            Schema::create('seller_profiles', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
                $table->string('store_name', 150);
                $table->string('store_location', 191);
                $table->text('full_address');
                $table->text('supporting_information')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('whatsapp_otp_challenges')) {
            Schema::create('whatsapp_otp_challenges', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->string('phone_number', 32);
                $table->string('purpose', 40)->default('login');
                $table->string('otp_hash', 255);
                $table->string('session_token', 64);
                $table->unsignedTinyInteger('attempts')->default(0);
                $table->unsignedTinyInteger('resend_count')->default(0);
                $table->string('status', 20)->default('pending');
                $table->timestamp('expires_at');
                $table->timestamp('sent_at')->nullable();
                $table->timestamp('verified_at')->nullable();
                $table->timestamp('cancelled_at')->nullable();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['phone_number', 'status']);
                $table->index(['session_token', 'status']);
            });
        }

        if (! Schema::hasTable('violations')) {
            Schema::create('violations', function (Blueprint $table): void {
                $table->id();
                $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
                $table->foreignId('ikan_id')->nullable()->constrained('ikans')->nullOnDelete();
                $table->foreignId('transaksi_id')->nullable()->constrained('transaksis')->nullOnDelete();
                $table->foreignId('admin_executor_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('role', 20);
                $table->string('type', 60);
                $table->string('status', 20)->default('active');
                $table->string('action', 20)->default('warning');
                $table->text('reason');
                $table->text('notes')->nullable();
                $table->unsignedSmallInteger('duration_hours')->nullable();
                $table->timestamp('effective_from')->nullable();
                $table->timestamp('effective_until')->nullable();
                $table->timestamp('resolved_at')->nullable();
                $table->softDeletes();
                $table->timestamps();

                $table->index(['user_id', 'status']);
                $table->index(['role', 'type']);
                $table->index(['action', 'effective_until']);
            });
        }

        if (! Schema::hasTable('system_settings')) {
            Schema::create('system_settings', function (Blueprint $table): void {
                $table->id();
                $table->string('group', 60)->default('general');
                $table->string('key', 120)->unique();
                $table->text('value')->nullable();
                $table->string('type', 30)->default('string');
                $table->timestamps();
            });
        }

        $transaksiColumns = [
            'order_code' => ! Schema::hasColumn('transaksis', 'order_code'),
            'payment_status' => ! Schema::hasColumn('transaksis', 'payment_status'),
            'winner_rank' => ! Schema::hasColumn('transaksis', 'winner_rank'),
            'assigned_at' => ! Schema::hasColumn('transaksis', 'assigned_at'),
            'payment_expired_at' => ! Schema::hasColumn('transaksis', 'payment_expired_at'),
            'seller_process_deadline_at' => ! Schema::hasColumn('transaksis', 'seller_process_deadline_at'),
            'pickup_status' => ! Schema::hasColumn('transaksis', 'pickup_status'),
            'packing_location' => ! Schema::hasColumn('transaksis', 'packing_location'),
            'packing_recorded_at' => ! Schema::hasColumn('transaksis', 'packing_recorded_at'),
            'packing_description' => ! Schema::hasColumn('transaksis', 'packing_description'),
            'buyer_pickup_name' => ! Schema::hasColumn('transaksis', 'buyer_pickup_name'),
            'buyer_pickup_plate_number' => ! Schema::hasColumn('transaksis', 'buyer_pickup_plate_number'),
            'buyer_pickup_photo' => ! Schema::hasColumn('transaksis', 'buyer_pickup_photo'),
            'buyer_pickup_notes' => ! Schema::hasColumn('transaksis', 'buyer_pickup_notes'),
            'buyer_pickup_submitted_at' => ! Schema::hasColumn('transaksis', 'buyer_pickup_submitted_at'),
            'buyer_pickup_departed_at' => ! Schema::hasColumn('transaksis', 'buyer_pickup_departed_at'),
            'seller_pickup_driver_name' => ! Schema::hasColumn('transaksis', 'seller_pickup_driver_name'),
            'seller_pickup_driver_photo' => ! Schema::hasColumn('transaksis', 'seller_pickup_driver_photo'),
            'seller_pickup_vehicle_photo' => ! Schema::hasColumn('transaksis', 'seller_pickup_vehicle_photo'),
            'seller_pickup_plate_number' => ! Schema::hasColumn('transaksis', 'seller_pickup_plate_number'),
            'seller_pickup_recorded_at' => ! Schema::hasColumn('transaksis', 'seller_pickup_recorded_at'),
            'pickup_match_status' => ! Schema::hasColumn('transaksis', 'pickup_match_status'),
            'pickup_verified_at' => ! Schema::hasColumn('transaksis', 'pickup_verified_at'),
            'buyer_rating' => ! Schema::hasColumn('transaksis', 'buyer_rating'),
            'buyer_review' => ! Schema::hasColumn('transaksis', 'buyer_review'),
            'buyer_reviewed_at' => ! Schema::hasColumn('transaksis', 'buyer_reviewed_at'),
            'completed_by_buyer_at' => ! Schema::hasColumn('transaksis', 'completed_by_buyer_at'),
        ];

        Schema::table('transaksis', function (Blueprint $table) use ($transaksiColumns): void {
            if ($transaksiColumns['order_code']) {
                $table->string('order_code', 32)->nullable()->after('id');
            }

            if ($transaksiColumns['payment_status']) {
                $table->string('payment_status', 20)->default('pending')->after('status');
            }

            if ($transaksiColumns['winner_rank']) {
                $table->unsignedSmallInteger('winner_rank')->nullable()->after('pemenang_id');
            }

            if ($transaksiColumns['assigned_at']) {
                $table->timestamp('assigned_at')->nullable()->after('winner_rank');
            }

            if ($transaksiColumns['payment_expired_at']) {
                $table->timestamp('payment_expired_at')->nullable()->after('bayar_sebelum');
            }

            if ($transaksiColumns['seller_process_deadline_at']) {
                $table->timestamp('seller_process_deadline_at')->nullable()->after('seller_ack_deadline_at');
            }

            if ($transaksiColumns['pickup_status']) {
                $table->string('pickup_status', 30)->default('waiting_payment')->after('payment_expired_at');
            }

            if ($transaksiColumns['packing_location']) {
                $table->string('packing_location', 191)->nullable()->after('packing_proof');
            }

            if ($transaksiColumns['packing_recorded_at']) {
                $table->timestamp('packing_recorded_at')->nullable()->after('packing_location');
            }

            if ($transaksiColumns['packing_description']) {
                $table->text('packing_description')->nullable()->after('packing_recorded_at');
            }

            if ($transaksiColumns['buyer_pickup_name']) {
                $table->string('buyer_pickup_name', 191)->nullable()->after('packing_description');
            }

            if ($transaksiColumns['buyer_pickup_plate_number']) {
                $table->string('buyer_pickup_plate_number', 40)->nullable()->after('buyer_pickup_name');
            }

            if ($transaksiColumns['buyer_pickup_photo']) {
                $table->string('buyer_pickup_photo')->nullable()->after('buyer_pickup_plate_number');
            }

            if ($transaksiColumns['buyer_pickup_notes']) {
                $table->text('buyer_pickup_notes')->nullable()->after('buyer_pickup_photo');
            }

            if ($transaksiColumns['buyer_pickup_submitted_at']) {
                $table->timestamp('buyer_pickup_submitted_at')->nullable()->after('buyer_pickup_notes');
            }

            if ($transaksiColumns['buyer_pickup_departed_at']) {
                $table->timestamp('buyer_pickup_departed_at')->nullable()->after('buyer_pickup_submitted_at');
            }

            if ($transaksiColumns['seller_pickup_driver_name']) {
                $table->string('seller_pickup_driver_name', 191)->nullable()->after('buyer_pickup_departed_at');
            }

            if ($transaksiColumns['seller_pickup_driver_photo']) {
                $table->string('seller_pickup_driver_photo')->nullable()->after('seller_pickup_driver_name');
            }

            if ($transaksiColumns['seller_pickup_vehicle_photo']) {
                $table->string('seller_pickup_vehicle_photo')->nullable()->after('seller_pickup_driver_photo');
            }

            if ($transaksiColumns['seller_pickup_plate_number']) {
                $table->string('seller_pickup_plate_number', 40)->nullable()->after('seller_pickup_vehicle_photo');
            }

            if ($transaksiColumns['seller_pickup_recorded_at']) {
                $table->timestamp('seller_pickup_recorded_at')->nullable()->after('seller_pickup_plate_number');
            }

            if ($transaksiColumns['pickup_match_status']) {
                $table->string('pickup_match_status', 20)->default('pending')->after('seller_pickup_recorded_at');
            }

            if ($transaksiColumns['pickup_verified_at']) {
                $table->timestamp('pickup_verified_at')->nullable()->after('pickup_match_status');
            }

            if ($transaksiColumns['buyer_rating']) {
                $table->unsignedTinyInteger('buyer_rating')->nullable()->after('pickup_verified_at');
            }

            if ($transaksiColumns['buyer_review']) {
                $table->text('buyer_review')->nullable()->after('buyer_rating');
            }

            if ($transaksiColumns['buyer_reviewed_at']) {
                $table->timestamp('buyer_reviewed_at')->nullable()->after('buyer_review');
            }

            if ($transaksiColumns['completed_by_buyer_at']) {
                $table->timestamp('completed_by_buyer_at')->nullable()->after('buyer_reviewed_at');
            }
        });

        try {
            if (! $this->indexExists('transaksis', 'transaksis_order_code_unique')) {
                Schema::table('transaksis', function (Blueprint $table): void {
                    $table->unique('order_code');
                });
            }
        } catch (\Throwable) {
            // Ignore when legacy data prevents unique backfill.
        }

        if (
            Schema::hasColumn('transaksis', 'seller_process_deadline_at')
            && Schema::hasColumn('transaksis', 'ship_deadline_at')
        ) {
            DB::table('transaksis')
                ->whereNull('seller_process_deadline_at')
                ->update(['seller_process_deadline_at' => DB::raw('ship_deadline_at')]);
        }

        $paymentColumns = [
            'payment_code' => ! Schema::hasColumn('payment_attempts', 'payment_code'),
            'provider' => ! Schema::hasColumn('payment_attempts', 'provider'),
            'status_code' => ! Schema::hasColumn('payment_attempts', 'status_code'),
            'provider_transaction_id' => ! Schema::hasColumn('payment_attempts', 'provider_transaction_id'),
            'provider_status' => ! Schema::hasColumn('payment_attempts', 'provider_status'),
            'payment_method_code' => ! Schema::hasColumn('payment_attempts', 'payment_method_code'),
            'payment_method_name' => ! Schema::hasColumn('payment_attempts', 'payment_method_name'),
            'checkout_url' => ! Schema::hasColumn('payment_attempts', 'checkout_url'),
            'checkout_expires_at' => ! Schema::hasColumn('payment_attempts', 'checkout_expires_at'),
            'callback_signature' => ! Schema::hasColumn('payment_attempts', 'callback_signature'),
            'callback_idempotency_key' => ! Schema::hasColumn('payment_attempts', 'callback_idempotency_key'),
            'callback_processed_at' => ! Schema::hasColumn('payment_attempts', 'callback_processed_at'),
            'request_payload' => ! Schema::hasColumn('payment_attempts', 'request_payload'),
            'callback_payload' => ! Schema::hasColumn('payment_attempts', 'callback_payload'),
            'failed_at' => ! Schema::hasColumn('payment_attempts', 'failed_at'),
            'cancelled_at' => ! Schema::hasColumn('payment_attempts', 'cancelled_at'),
            'refunded_at' => ! Schema::hasColumn('payment_attempts', 'refunded_at'),
            'retry_of_payment_id' => ! Schema::hasColumn('payment_attempts', 'retry_of_payment_id'),
        ];

        Schema::table('payment_attempts', function (Blueprint $table) use ($paymentColumns): void {
            if ($paymentColumns['payment_code']) {
                $table->string('payment_code', 32)->nullable()->after('id');
            }

            if ($paymentColumns['provider']) {
                $table->string('provider', 40)->default('tripay')->after('payment_code');
            }

            if ($paymentColumns['status_code']) {
                $table->string('status_code', 20)->default('pending')->after('status');
            }

            if ($paymentColumns['provider_transaction_id']) {
                $table->string('provider_transaction_id', 120)->nullable()->after('payment_provider_ref');
            }

            if ($paymentColumns['provider_status']) {
                $table->string('provider_status', 40)->nullable()->after('provider_transaction_id');
            }

            if ($paymentColumns['payment_method_code']) {
                $table->string('payment_method_code', 60)->nullable()->after('provider_status');
            }

            if ($paymentColumns['payment_method_name']) {
                $table->string('payment_method_name', 120)->nullable()->after('payment_method_code');
            }

            if ($paymentColumns['checkout_url']) {
                $table->text('checkout_url')->nullable()->after('payment_method_name');
            }

            if ($paymentColumns['checkout_expires_at']) {
                $table->timestamp('checkout_expires_at')->nullable()->after('checkout_url');
            }

            if ($paymentColumns['callback_signature']) {
                $table->string('callback_signature', 191)->nullable()->after('checkout_expires_at');
            }

            if ($paymentColumns['callback_idempotency_key']) {
                $table->string('callback_idempotency_key', 191)->nullable()->after('callback_signature');
            }

            if ($paymentColumns['callback_processed_at']) {
                $table->timestamp('callback_processed_at')->nullable()->after('callback_idempotency_key');
            }

            if ($paymentColumns['request_payload']) {
                $table->json('request_payload')->nullable()->after('callback_processed_at');
            }

            if ($paymentColumns['callback_payload']) {
                $table->json('callback_payload')->nullable()->after('request_payload');
            }

            if ($paymentColumns['failed_at']) {
                $table->timestamp('failed_at')->nullable()->after('expired_at');
            }

            if ($paymentColumns['cancelled_at']) {
                $table->timestamp('cancelled_at')->nullable()->after('failed_at');
            }

            if ($paymentColumns['refunded_at']) {
                $table->timestamp('refunded_at')->nullable()->after('cancelled_at');
            }

            if ($paymentColumns['retry_of_payment_id']) {
                $table->foreignId('retry_of_payment_id')->nullable()->after('refunded_at')->constrained('payment_attempts')->nullOnDelete();
            }
        });

        try {
            if (! $this->indexExists('payment_attempts', 'payment_attempts_payment_code_unique')) {
                Schema::table('payment_attempts', function (Blueprint $table): void {
                    $table->unique('payment_code');
                });
            }
        } catch (\Throwable) {
            // Ignore when legacy rows conflict.
        }

        try {
            if (! $this->indexExists('payment_attempts', 'payment_attempts_provider_transaction_id_unique')) {
                Schema::table('payment_attempts', function (Blueprint $table): void {
                    $table->unique('provider_transaction_id');
                });
            }
        } catch (\Throwable) {
            // Ignore when provider reference duplicates exist on legacy rows.
        }

        $defaults = [
            ['group' => 'site', 'key' => 'site_name', 'value' => 'Borgfish', 'type' => 'string'],
            ['group' => 'site', 'key' => 'site_address', 'value' => 'Alamat Borgfish belum diatur', 'type' => 'text'],
            ['group' => 'site', 'key' => 'site_email', 'value' => 'hello@borgfish.test', 'type' => 'string'],
            ['group' => 'site', 'key' => 'admin_contact', 'value' => '+62', 'type' => 'string'],
            ['group' => 'site', 'key' => 'about_text', 'value' => 'Borgfish adalah marketplace lelang ikan untuk transaksi nyata antara penjual dan pembeli.', 'type' => 'text'],
            ['group' => 'legal', 'key' => 'privacy_policy', 'value' => 'Kebijakan privasi belum diatur.', 'type' => 'longtext'],
            ['group' => 'legal', 'key' => 'terms_conditions', 'value' => 'Syarat & ketentuan belum diatur.', 'type' => 'longtext'],
            ['group' => 'legal', 'key' => 'contact_page', 'value' => 'Hubungi kami melalui kontak admin yang tersedia.', 'type' => 'longtext'],
            ['group' => 'payment', 'key' => 'default_payment_method', 'value' => 'QRIS', 'type' => 'string'],
            ['group' => 'payment', 'key' => 'payment_deadline_minutes', 'value' => '30', 'type' => 'integer'],
        ];

        foreach ($defaults as $row) {
            DB::table('system_settings')->updateOrInsert(
                ['key' => $row['key']],
                [
                    'group' => $row['group'],
                    'value' => $row['value'],
                    'type' => $row['type'],
                    'updated_at' => now(),
                    'created_at' => now(),
                ]
            );
        }

        if (Schema::hasTable('in_app_notifications')) {
            DB::table('in_app_notifications')
                ->where('category', 'pengiriman')
                ->update(['category' => 'penjemputan']);
        }

        if (Schema::hasTable('notification_outboxes')) {
            DB::table('notification_outboxes')
                ->where('category', 'pengiriman')
                ->update(['category' => 'penjemputan']);
        }

        if (Schema::hasTable('transaction_disputes')) {
            DB::table('transaction_disputes')
                ->where('complaint_reason', 'barang_belum_tiba')
                ->update(['complaint_reason' => 'penjemput_belum_datang']);

            DB::table('transaction_disputes')
                ->where('complaint_reason', 'resi_tidak_valid')
                ->update(['complaint_reason' => 'data_penjemput_bermasalah']);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('system_settings');
        Schema::dropIfExists('violations');
        Schema::dropIfExists('whatsapp_otp_challenges');
        Schema::dropIfExists('seller_profiles');

        Schema::table('payment_attempts', function (Blueprint $table): void {
            $columns = [
                'retry_of_payment_id',
                'refunded_at',
                'cancelled_at',
                'failed_at',
                'callback_payload',
                'request_payload',
                'callback_processed_at',
                'callback_idempotency_key',
                'callback_signature',
                'checkout_expires_at',
                'checkout_url',
                'payment_method_name',
                'payment_method_code',
                'provider_status',
                'provider_transaction_id',
                'status_code',
                'provider',
                'payment_code',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('payment_attempts', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('transaksis', function (Blueprint $table): void {
            $columns = [
                'completed_by_buyer_at',
                'buyer_reviewed_at',
                'buyer_review',
                'buyer_rating',
                'pickup_verified_at',
                'pickup_match_status',
                'seller_pickup_recorded_at',
                'seller_pickup_plate_number',
                'seller_pickup_vehicle_photo',
                'seller_pickup_driver_photo',
                'seller_pickup_driver_name',
                'buyer_pickup_departed_at',
                'buyer_pickup_submitted_at',
                'buyer_pickup_notes',
                'buyer_pickup_photo',
                'buyer_pickup_plate_number',
                'buyer_pickup_name',
                'packing_description',
                'packing_recorded_at',
                'packing_location',
                'pickup_status',
                'seller_process_deadline_at',
                'payment_expired_at',
                'assigned_at',
                'winner_rank',
                'payment_status',
                'order_code',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('transaksis', $column)) {
                    $table->dropColumn($column);
                }
            }
        });

        Schema::table('users', function (Blueprint $table): void {
            $columns = [
                'onboarding_completed_at',
                'last_otp_verified_at',
                'last_login_at',
                'status_reason',
                'suspended_until',
                'user_status',
                'whatsapp_verified_at',
                'whatsapp_number',
                'google_avatar',
                'auth_provider',
                'google_id',
            ];

            foreach ($columns as $column) {
                if (Schema::hasColumn('users', $column)) {
                    $table->dropColumn($column);
                }
            }
        });
    }

    private function indexExists(string $table, string $index): bool
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        return match ($driver) {
            'sqlite' => collect(DB::select("PRAGMA index_list('{$table}')"))
                ->contains(fn ($row) => isset($row->name) && $row->name === $index),
            'mysql', 'mariadb' => collect(DB::select("SHOW INDEX FROM `{$table}` WHERE Key_name = ?", [$index]))->isNotEmpty(),
            'pgsql' => collect(DB::select('SELECT indexname FROM pg_indexes WHERE tablename = ? AND indexname = ?', [$table, $index]))->isNotEmpty(),
            default => false,
        };
    }
};
