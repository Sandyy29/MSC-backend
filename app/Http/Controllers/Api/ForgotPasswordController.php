<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\PasswordResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ForgotPasswordController extends Controller
{
    protected PasswordResetService $passwordResetService;

    public function __construct(PasswordResetService $passwordResetService)
    {
        $this->passwordResetService = $passwordResetService;
    }

    /**
     * @OA\Post(
     *     path="/api/forgot-password/send-otp",
     *     summary="Send Password Reset OTP",
     *     tags={"Forgot Password"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="If an account exists for this email, an OTP has been sent.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=422,
     *         description="Validation Error"
     *     )
     * )
     */
    public function sendOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $this->passwordResetService->sendOtp($request->email);

        return response()->json([
            'success' => true,
            'message' => 'If an account exists for this email, an OTP has been sent.',
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/forgot-password/verify-otp",
     *     summary="Verify Password Reset OTP",
     *     tags={"Forgot Password"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email", "otp"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="otp", type="string", example="123456")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="OTP Verified",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="OTP verified successfully."),
     *             @OA\Property(property="reset_token", type="string", example="generated_token_here")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid or expired OTP"
     *     )
     * )
     */
    public function verifyOtp(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'otp' => 'required|string|size:6',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $result = $this->passwordResetService->verifyOtp($request->email, $request->otp);

        if ($result === null) {
            return response()->json([
                'success' => false,
                'message' => 'No active password reset request found.',
            ], 400);
        }

        if ($result === 'expired') {
            return response()->json([
                'success' => false,
                'message' => 'OTP has expired. Please request a new OTP.',
            ], 400);
        }

        if ($result === 'blocked') {
            return response()->json([
                'success' => false,
                'message' => 'Too many failed attempts. Please request a new OTP.',
            ], 400);
        }

        if ($result === 'invalid') {
            return response()->json([
                'success' => false,
                'message' => 'Invalid OTP.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'OTP verified successfully.',
            'reset_token' => $result,
        ]);
    }

    /**
     * @OA\Post(
     *     path="/api/forgot-password/resend-otp",
     *     summary="Resend Password Reset OTP",
     *     tags={"Forgot Password"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Successful operation",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="If an account exists for this email, an OTP has been sent.")
     *         )
     *     )
     * )
     */
    public function resendOtp(Request $request)
    {
        return $this->sendOtp($request); // Reuse sendOtp logic which includes cooldown
    }

    /**
     * @OA\Post(
     *     path="/api/forgot-password/reset",
     *     summary="Reset Password",
     *     tags={"Forgot Password"},
     *
     *     @OA\RequestBody(
     *         required=true,
     *
     *         @OA\JsonContent(
     *             required={"email", "reset_token", "password", "password_confirmation"},
     *
     *             @OA\Property(property="email", type="string", format="email", example="user@example.com"),
     *             @OA\Property(property="reset_token", type="string", example="token_from_verify_otp"),
     *             @OA\Property(property="password", type="string", example="NewPassword123!"),
     *             @OA\Property(property="password_confirmation", type="string", example="NewPassword123!")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=200,
     *         description="Password Reset Successful",
     *
     *         @OA\JsonContent(
     *
     *             @OA\Property(property="success", type="boolean", example=true),
     *             @OA\Property(property="message", type="string", example="Password reset successfully.")
     *         )
     *     ),
     *
     *     @OA\Response(
     *         response=400,
     *         description="Invalid token or expired"
     *     )
     * )
     */
    public function reset(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'reset_token' => 'required|string',
            'password' => 'required|string|min:8|confirmed',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $success = $this->passwordResetService->resetPassword(
            $request->email,
            $request->reset_token,
            $request->password
        );

        if (! $success) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired reset token.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Password reset successfully.',
        ]);
    }
}
