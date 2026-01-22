<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Http;

class EmailHelper
{
    public static string $API_MAIL_URL = 'api/mail-sender';

    public static function sendMigrationEmail($email, $token, $first_name, $title = null): bool
    {
        $source = 'NSSF PORTAL';
        $body = self::buildMigrationEmailBody($first_name);
        return self::sendEmail([$email], $body, 'Welcome to the Improved Version of the NSSF Portal', $source);
    }

    private static function buildMigrationEmailBody($first_name): string
    {
        return "
        <body style='font-family: Arial, sans-serif; line-height: 1.8; color: #333; margin: 0; padding: 20px; background-color: #f9f9f9; font-size: 14px'>
            <div style='max-width: 600px; margin: 0 auto; background: #ffffff; border-radius: 8px; padding: 20px; box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);'>
                <h3 style='color: #0056b3;'>Dear {$first_name},</h3>
                <p style='margin-bottom: 20px;'>We are excited to announce the launch of the improved version of the <strong>NSSF Portal</strong>, which enhances the functionality of the previous Employer Portal. This upgrade reflects our ongoing commitment to providing you with seamless access to NSSF services. The improved version of the portal boasts smarter features, superior performance, and a more intuitive interface, all designed to enhance your user experience.</p>
                <p style='margin-bottom: 20px;'>Your account has already been migrated to the improved version of the portal. Please note that the old version of the Employer Portal will be officially decommissioned on <strong>1st March 2025</strong>. To ensure uninterrupted access, kindly log in to the improved version using the following link:</p>
                <p style='text-align: center; margin-bottom: 10px;'>
                    <a href='https://portal.nssf.go.tz' style='display: inline-block; padding: 10px 20px; color: #ffffff; background-color: #0056b3; text-decoration: none; border-radius: 5px;'>Access the NSSF Portal</a>
                </p>
                <a  style='text-align: center; margin-bottom: 20px;font-size: 12px' href='https://portal.nssf.go.tz'>https://portal.nssf.go.tz</a>
                <p style='margin-bottom: 20px;'>Use your existing credentials, and you will be prompted to set a new password for added security.</p>
                <p style='margin-bottom: 20px;'>For your convenience, the attached user manual will guide you through the improved version of the portal:</p>
                <p style='margin-bottom: 20px;'>
                    <a href='https://portal.nssf.go.tz/user-manual-employer-services.pdf' style='color: #0056b3; text-decoration: underline;'>User Manual: Employer Services</a>
                </p>
                <p style='margin-bottom: 20px;'>Should you require any assistance, please feel free to contact our support team:</p>
                <ul style='list-style: none; padding: 0; margin: 0;'>
                    <li style='margin-bottom: 5px;'><strong>Phone:</strong> 0756 140 140</li>
                    <li style='margin-bottom: 5px;'><strong>Toll-Free:</strong> 0800 116 773</li>
                    <li style='margin-top: 10px;'>Or your <strong>Area Inspector</strong></li>
                </ul>
                <p style='margin-top: 20px;'>Thank you for your continued cooperation as we work to improve your experience with our upgraded platform.</p>
                <p style='margin-top: 20px;'>Best regards,<br><strong>NSSF Tanzania</strong></p>
            </div>
        </body>
    ";
    }


    public static function sendEmail($recipients, $body, $title, $source, $attachments = []): bool
    {
        $response = Http::post(DBHelper::getICTMSLink() . self::$API_MAIL_URL, [
            'recipients' => $recipients,
            'body' => $body,
            'title' => $title,
            'source' => $source,
            "from_name" => "NSSF Tanzania",
            'attachments' => $attachments
        ]);

        Log::info('sendEmail JSON', (array)$response->json());
        Log::info('sendEmail BODY', (array)$response->body());
        Log::info($response->successful());

        return $response->successful();
    }

    public static function sendEmailVerification($email, $token, $first_name, $title = null): bool
    {
        $source = 'NSSF PORTAL';
        $verificationUrl = DBHelper::getNSSFPortalLink() . 'email-confirmation/' . base64_encode($email) . '/' . $token;

        $body = self::buildVerificationEmailBody(
            $first_name,
            $verificationUrl,
            $token
        );

        return self::sendEmail([$email], $body, is_numeric($token) ? 'Password Reset' : 'Email Confirmation', $source);
    }

    private static function buildVerificationEmailBody($first_name, $verificationUrl, $token): string
    {
        // <img src='https://www.nssf.go.tz/site/images/logo.png' alt='NSSF Tanzania Logo' style='display: block; height: 100px; margin: 0 auto 20px;'>
        $footer = EmailHelper::footer();
        return is_numeric($token) ? "
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='max-width: 600px; margin: auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px;'>
                <h2>Hello {$first_name},</h2>
                <p>We have received request to reset password of your NSSF Account. Use the One Time Password (OTP) code below to continue.</p>
                <p style='text-align: center;'>
                    <div style='display: inline-block; padding: 10px 20px; font-size: 30px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px;'>{$token}</div>
                </p>

                <p>If you did not initiate this request, it is possible that your email address was entered by mistake. Please disregard this email.</p>
                <p>Best regards,<br>NSSF Tanzania</p>
                {$footer}
            </div>
        </body>
    " : "
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='max-width: 600px; margin: auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px;'>
                <h2>Hello {$first_name},</h2>
                <p>We have received a request to create an NSSF Account with this email; please click the button below to confirm.</p>
                <p style='text-align: center;'>
                    <a href='{$verificationUrl}' style='display: inline-block; padding: 10px 20px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px;'>Confirm Email</a>
                </p>
                <p>Alternatively, you can copy and paste the following URL into your browser:</p>
                <p style='word-break: break-all;'>{$verificationUrl}</p>

                <p>If you did not sign up for this account, no further action is required.</p>
                <p>Best regards,<br>NSSF Tanzania</p>
                {$footer}
            </div>
        </body>
    ";
    }

    public static function footer(): string
    {
        return "
        <div style='margin-top: 30px; padding-top: 20px; border-top: 1px solid #e0e0e0; font-size: 12px; text-align: center; color: #888888;'>
            <p>National Social Security Fund</p>
            <p>P.O.Box 1322, Benjamin Mkapa Pension Towers, Azikiwe St, Dar es Salaam,Tanzania</p>
            <p>&copy; " . date('Y') . " NSSF Tanzania. All rights reserved.</p>
        </div>
    ";


    }

    public static function sendEmailEditVerification($email, $token, $first_name): bool
    {
        $source = 'NSSF PORTAL';

        $body = self::buildEmailEditVerificationBody(
            $first_name,
            $token
        );

        return self::sendEmail([$email], $body, 'Email Verification', $source);
    }

    public static function buildEmailEditVerificationBody($first_name, $token): string
    {
        $footer = EmailHelper::footer();

        return "
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='max-width: 600px; margin: auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px;'>
                <h2>Hello {$first_name},</h2>
                <p>We have received a request to edit your email address of your NSSF Account.</p>
                <p style='text-align: center;'>
                    <div style='display: inline-block; padding: 10px 20px; font-size: 30px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px;'>{$token}</div>
                </p>
                <p>If you did not initiate this request, no further action is required.</p>
                <p>Best regards,<br>NSSF Tanzania</p>
                {$footer}
            </div>
        </body>
        ";

    }

    public static function sendEmailOTP($email, $token, $first_name): bool
    {
        $source = 'NSSF PORTAL';
        $body = self::buildEmailOTPBody(
            $first_name,
            $token
        );

        return self::sendEmail([$email], $body, 'NSSF Account Verification', $source);
    }

    public static function buildEmailOTPBody($first_name, $token): string
    {
        $footer = EmailHelper::footer();

        return "
        <body style='font-family: Arial, sans-serif; line-height: 1.6;'>
            <div style='max-width: 600px; margin: auto; padding: 20px; border: 1px solid #e0e0e0; border-radius: 10px;'>
                <h2>Hello {$first_name},</h2>
                <p>We have received a request to create an NSSF Account; please use the token below to verify.</p>
                <p style='text-align: center;'>
                    <div style='display: inline-block; padding: 10px 20px; font-size: 30px; background-color: #007bff; color: #ffffff; text-decoration: none; border-radius: 5px;'>{$token}</div>
                </p>
                <p>If you did not initiate this request, no further action is required.</p>
                <p>Best regards,<br>NSSF Tanzania</p>
                {$footer}
            </div>
        </body>
        ";

    }

    public static function sendEmployerRegistrationInstructions($email, $employer): bool
    {
        $source = 'EMPLOYER REGISTRATION';
        $body = "
            <body>
                <h1>Welcome, {$employer->employer_name}!</h1>
                <p>Thank you for registering with us. Below are the instructions on how to pay your contributions:</p>
                <h2>Payment Instructions</h2>
                <ul>
                    <li>Visit our payment portal at <a href='https://portal.nssf.go.tz'>portal.nssf.go.tz</a>.</li>
                    <li>Login with your employer ID and password.</li>
                    <li>Select the 'Make a Payment' option and follow the prompts.</li>
                    <li>Ensure you have your bank details and contribution amount ready.</li>
                </ul>
                <h2>Repercussions of Nonpayment</h2>
                <p>Please note that failure to pay your contributions on time can result in:</p>
                <ul>
                    <li>Penalties and interest on the outstanding amount.</li>
                    <li>Legal action to recover the unpaid contributions.</li>
                    <li>Possible suspension of benefits for your employees.</li>
                </ul>
                <p>If you have any questions, please contact our support team at support@example.com.</p>
                <p>Thank you for your cooperation.</p>
                <p>Best regards,<br>NSSF Tanzania</p>
            </body>
        ";

        return self::sendEmail([$email], $body, 'Employer Registration Instructions', $source);
    }

    public static function sendMemberRegistrationNotification($email, $member): bool
    {
        $source = 'MEMBER REGISTRATION';

        $body = "
            <body>
                <h1>New Member Registration</h1>
                <p>A new member has registered through the portal. Below are the details:</p>
                <ul>
                    <li>Name: {{ $member->first_name }} {{ $member->last_name }}</li>
                    <li>Email: {{ $member->email }}</li>
                    <li>Member ID: {{ $member->member_id }}</li>
                    <li>Registration Date: {{ $member->created_at->format('d-M-Y') }}</li>
                </ul>
                <p>Please follow up accordingly.</p>
                <p>Thank you,</p>
                <p>NSSF Tanzania</p>
                <p>Best regards,<br>NSSF Tanzania</p>
            </body>
        ";

        return self::sendEmail([$email], $body, 'Employer Registration Instructions', $source);
    }
}
