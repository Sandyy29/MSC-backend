<?php

namespace App\Services;

use App\Mail\ResetPasswordOtpMail;
use App\Models\PasswordResetOtp;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

class PasswordResetService
{
    const OTP_EXPIRY_MINUTES = 5;

    const MAX_ATTEMPTS = 5;

    const COOLDOWN_MINUTES = 1;

    public function sendOtp(string $email): void
    {
        // Don't leak if email exists or not, but we only send if it does.
        $user = User::where('email', $email)->first();

        if ($user) {
            // Check for cooldown
            $recentOtp = PasswordResetOtp::where('email', $email)
                ->where('created_at', '>=', now()->subMinutes(self::COOLDOWN_MINUTES))
                ->first();

            if ($recentOtp) {
                // To avoid leaking, just return (silently fail to send new)
                return;
            }

            $otp = str_pad((string) random_int(100000, 999999), 6, '0', STR_PAD_LEFT);
            $otpHash = Hash::make($otp);

            // Invalidate existing OTPs
            PasswordResetOtp::where('email', $email)->delete();

            PasswordResetOtp::create([
                'email' => $email,
                'otp_hash' => $otpHash,
                'expires_at' => now()->addMinutes(self::OTP_EXPIRY_MINUTES),
            ]);

            Mail::to($email)->send(new ResetPasswordOtpMail($otp));
        }
    }

    public function verifyOtp(string $email, string $otp): ?string
    {
        $resetRecord = PasswordResetOtp::where('email', $email)
            ->whereNull('verified_at')
            ->first();

        if (! $resetRecord) {
            return null; // No active OTP request
        }

        if (now()->greaterThan($resetRecord->expires_at)) {
            return 'expired';
        }

        if ($resetRecord->attempts >= self::MAX_ATTEMPTS) {
            return 'blocked';
        }

        $resetRecord->increment('attempts');

        if (! Hash::check($otp, $resetRecord->otp_hash)) {
            return 'invalid';
        }

        // OTP is correct, generate reset token
        $resetToken = Str::random(64);

        $resetRecord->update([
            'verified_at' => now(),
            'reset_token' => hash('sha256', $resetToken),
            // We can extend expiry for the token phase, or just use the remaining time. Let's give them 15 mins to reset.
            'expires_at' => now()->addMinutes(15),
        ]);

        return $resetToken; // Return the raw token
    }

    public function resetPassword(string $email, string $resetToken, string $newPassword): bool
    {
        $tokenHash = hash('sha256', $resetToken);

        $resetRecord = PasswordResetOtp::where('email', $email)
            ->where('reset_token', $tokenHash)
            ->whereNotNull('verified_at')
            ->first();

        if (! $resetRecord || now()->greaterThan($resetRecord->expires_at)) {
            return false;
        }

        $user = User::where('email', $email)->first();
        if ($user) {
            $user->update([
                'password' => Hash::make($newPassword),
            ]);
        }

        // Invalidate record
        $resetRecord->delete();

        return true;
    }
}
