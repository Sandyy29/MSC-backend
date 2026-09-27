<?php

namespace Tests\Feature;

use App\Mail\ResetPasswordOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class ForgotPasswordApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_send_otp_to_registered_email()
    {
        Mail::fake();
        $user = User::factory()->create(['email' => 'user@example.com']);

        $response = $this->postJson('/api/forgot-password/send-otp', [
            'email' => 'user@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('password_reset_otps', [
            'email' => 'user@example.com',
        ]);

        Mail::assertSent(ResetPasswordOtpMail::class);
    }

    public function test_send_otp_unregistered_email_safe_response()
    {
        Mail::fake();

        $response = $this->postJson('/api/forgot-password/send-otp', [
            'email' => 'notfound@example.com',
        ]);

        $response->assertStatus(200)
            ->assertJson(['success' => true]);

        Mail::assertNothingSent();
    }

    public function test_verify_wrong_otp()
    {
        PasswordResetOtp::create([
            'email' => 'user@example.com',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/forgot-password/verify-otp', [
            'email' => 'user@example.com',
            'otp' => '654321',
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Invalid OTP.']);
    }

    public function test_verify_correct_otp()
    {
        PasswordResetOtp::create([
            'email' => 'user@example.com',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
        ]);

        $response = $this->postJson('/api/forgot-password/verify-otp', [
            'email' => 'user@example.com',
            'otp' => '123456',
        ]);

        $response->assertStatus(200)
            ->assertJsonStructure(['success', 'message', 'reset_token']);
    }

    public function test_verify_expired_otp()
    {
        PasswordResetOtp::create([
            'email' => 'user@example.com',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->subMinutes(1),
        ]);

        $response = $this->postJson('/api/forgot-password/verify-otp', [
            'email' => 'user@example.com',
            'otp' => '123456',
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'OTP has expired. Please request a new OTP.']);
    }

    public function test_too_many_attempts()
    {
        $otp = PasswordResetOtp::create([
            'email' => 'user@example.com',
            'otp_hash' => Hash::make('123456'),
            'expires_at' => now()->addMinutes(5),
            'attempts' => 5,
        ]);

        $response = $this->postJson('/api/forgot-password/verify-otp', [
            'email' => 'user@example.com',
            'otp' => '654321',
        ]);

        $response->assertStatus(400)
            ->assertJson(['message' => 'Too many failed attempts. Please request a new OTP.']);
    }

    public function test_correct_otp_valid_new_password()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        $resetToken = 'test-token';
        PasswordResetOtp::create([
            'email' => 'user@example.com',
            'otp_hash' => Hash::make('123456'),
            'reset_token' => hash('sha256', $resetToken),
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/forgot-password/reset', [
            'email' => 'user@example.com',
            'reset_token' => $resetToken,
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(200);

        $this->assertTrue(Hash::check('NewPassword123!', $user->fresh()->password));
        $this->assertDatabaseMissing('password_reset_otps', ['email' => 'user@example.com']);
    }

    public function test_wrong_reset_token()
    {
        $user = User::factory()->create([
            'email' => 'user@example.com',
            'password' => Hash::make('oldpassword'),
        ]);

        PasswordResetOtp::create([
            'email' => 'user@example.com',
            'otp_hash' => Hash::make('123456'),
            'reset_token' => hash('sha256', 'correct-token'),
            'verified_at' => now(),
            'expires_at' => now()->addMinutes(15),
        ]);

        $response = $this->postJson('/api/forgot-password/reset', [
            'email' => 'user@example.com',
            'reset_token' => 'wrong-token',
            'password' => 'NewPassword123!',
            'password_confirmation' => 'NewPassword123!',
        ]);

        $response->assertStatus(400);
        $this->assertTrue(Hash::check('oldpassword', $user->fresh()->password));
    }
}
