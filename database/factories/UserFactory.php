<?php

namespace Database\Factories;

use App\Models\SellerProfile;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    public function configure(): static
    {
        return $this->afterCreating(function (User $user): void {
            if ((string) $user->role !== 'penjual' || $user->sellerProfile()->exists()) {
                return;
            }

            SellerProfile::create([
                'user_id' => $user->id,
                'store_name' => fake()->company(),
                'store_location' => fake()->city(),
                'full_address' => fake()->address(),
                'supporting_information' => 'Profil seller default untuk kebutuhan test.',
                'store_latitude' => -6.2000000,
                'store_longitude' => 106.8166667,
                'store_gps_accuracy' => 25.00,
                'store_gps_captured_at' => now(),
                'store_photo_path' => 'seller-profiles/testing-store.jpg',
                'bank_name' => 'BCA',
                'bank_account_number' => fake()->numerify('1234567890'),
                'bank_account_name' => $user->name,
            ]);
        });
    }

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'auth_provider' => 'google',
            'whatsapp_number' => fake()->unique()->numerify('628###########'),
            'whatsapp_verified_at' => now(),
            'user_status' => 'active',
            'onboarding_completed_at' => now(),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
