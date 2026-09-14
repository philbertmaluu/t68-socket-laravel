<?php

declare(strict_types=1);

namespace App\Domains\SelfService\Services;

use App\Domains\Device\Models\Device;
use App\Domains\Notification\Services\NotificationTemplateService;
use App\Domains\SelfService\Models\SelfServiceOtpChallenge;
use App\Domains\SelfService\Support\OtpCodeGenerator;
use App\Domains\SelfService\Support\SelfServiceLog;
use App\Domains\SelfService\Support\TanzaniaPhone;
use App\Services\NotificationService;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class SelfServiceOtpService
{
    public function __construct(
        private MemberDirectoryService $memberDirectory,
        private OtpCodeGenerator $otpCodeGenerator,
        private NotificationService $notificationService,
        private NotificationTemplateService $notificationTemplateService,
    ) {
    }

    /**
     * @return array{
     *     challenge_id: string,
     *     member_number: string,
     *     member_name: string,
     *     phone: string,
     *     masked_phone: string
     * }
     */
    public function verifyMember(string $memberNumber, ?Device $device = null): array
    {
        SelfServiceLog::step('verify_member.start', [
            'member_number' => $memberNumber,
            'device_id' => $device?->id,
            'tenant_id' => $device?->tenant_id,
            'office_id' => $device?->office_id,
        ]);

        $member = $this->memberDirectory->findByMemberNumber(
            $memberNumber,
            $device?->tenant_id
        );

        $challenge = SelfServiceOtpChallenge::create([
            'id' => (string) Str::uuid(),
            'tenant_id' => $device?->tenant_id,
            'device_id' => $device?->id,
            'member_number' => $member['member_number'],
            'member_name' => $member['member_name'],
            'phone' => $this->normalizePhone($member['phone']),
        ]);

        SelfServiceLog::step('verify_member.challenge_created', [
            'challenge_id' => $challenge->id,
            'member_number' => $challenge->member_number,
            'member_name' => $challenge->member_name,
            'phone' => $challenge->phone,
            'masked_phone' => $this->maskPhone($challenge->phone),
        ]);

        return $this->presentChallenge($challenge);
    }

    /**
     * @return array{
     *     challenge_id: string,
     *     member_number: string,
     *     member_name: string,
     *     phone: string,
     *     masked_phone: string
     * }
     */
    public function sendOtp(
        string $memberNumber,
        ?string $challengeId = null,
        ?string $locale = null,
        ?Device $device = null
    ): array {
        SelfServiceLog::step('send_otp.start', [
            'member_number' => $memberNumber,
            'challenge_id' => $challengeId,
            'locale' => $locale,
            'device_id' => $device?->id,
        ]);

        $challenge = $this->resolveChallenge($memberNumber, $challengeId, $device);

        if ($challenge->isVerified()) {
            throw new \RuntimeException('This verification code has already been used');
        }

        $member = $this->memberDirectory->findByMemberNumber(
            $memberNumber,
            $device?->tenant_id
        );
        $challenge->fill([
            'member_name' => $member['member_name'],
            'phone' => $this->normalizePhone($member['phone']),
        ]);
        $challenge->save();

        $resendAfter = (int) config('self_service.otp_resend_seconds', 45);
        if (
            $challenge->last_sent_at !== null
            && $challenge->last_sent_at->gt(now()->subSeconds($resendAfter))
        ) {
            throw new \RuntimeException('Please wait before requesting another OTP');
        }

        $length = (int) config('self_service.otp_length', 6);
        $ttl = (int) config('self_service.otp_ttl_minutes', 5);
        $otp = $this->otpCodeGenerator->generate($length);

        SelfServiceLog::step('send_otp.generated', [
            'challenge_id' => $challenge->id,
            'otp' => $otp,
            'ttl_minutes' => $ttl,
            'phone' => $challenge->phone,
        ]);

        $challenge->update([
            'otp_hash' => Hash::make($otp),
            'attempts' => 0,
            'expires_at' => now()->addMinutes($ttl),
            'last_sent_at' => now(),
            'verified_at' => null,
        ]);

        $this->dispatchSms($challenge, $otp, $ttl, $locale);

        SelfServiceLog::step('send_otp.sms_ok', [
            'challenge_id' => $challenge->id,
            'phone' => $challenge->phone,
        ]);

        return $this->presentChallenge($challenge->fresh() ?? $challenge);
    }

    /**
     * @return array{verified: bool, challenge_id: string, member_number: string, member_name: string}
     */
    public function verifyOtp(
        string $memberNumber,
        string $otp,
        ?string $challengeId = null,
        ?Device $device = null
    ): array {
        SelfServiceLog::step('verify_otp.start', [
            'member_number' => $memberNumber,
            'challenge_id' => $challengeId,
            'otp' => $otp,
            'device_id' => $device?->id,
        ]);

        $challenge = $this->resolveChallenge($memberNumber, $challengeId, $device);
        $code = preg_replace('/\D/', '', $otp) ?? '';

        if (strlen($code) !== (int) config('self_service.otp_length', 6)) {
            throw new \RuntimeException('Invalid OTP');
        }

        if ($challenge->isVerified()) {
            throw new \RuntimeException('This verification code has already been used');
        }

        if ($challenge->otp_hash === null || $challenge->otp_hash === '') {
            throw new \RuntimeException('OTP has not been sent yet');
        }

        if ($challenge->isExpired()) {
            throw new \RuntimeException('OTP has expired');
        }

        $maxAttempts = (int) config('self_service.otp_max_attempts', 5);
        if ($challenge->attempts >= $maxAttempts) {
            throw new \RuntimeException('Too many invalid OTP attempts');
        }

        if (!Hash::check($code, $challenge->otp_hash)) {
            $challenge->increment('attempts');
            SelfServiceLog::warning('verify_otp.mismatch', [
                'challenge_id' => $challenge->id,
                'attempts' => $challenge->attempts,
                'otp' => $code,
            ]);
            throw new \RuntimeException('Invalid OTP');
        }

        $challenge->update(['verified_at' => now()]);

        SelfServiceLog::step('verify_otp.ok', [
            'challenge_id' => $challenge->id,
            'member_number' => $challenge->member_number,
        ]);

        return [
            'verified' => true,
            'success' => true,
            'challenge_id' => $challenge->id,
            'member_number' => $challenge->member_number,
            'member_name' => (string) $challenge->member_name,
        ];
    }

    /**
     * @return array{member_number: string, member_name: string, name: string, phone: string, masked_phone: string}
     */
    public function memberDetails(string $memberNumber, ?Device $device = null): array
    {
        $member = $this->memberDirectory->findByMemberNumber(
            $memberNumber,
            $device?->tenant_id
        );

        $phone = $this->normalizePhone($member['phone']);

        return [
            'member_number' => $member['member_number'],
            'member_name' => $member['member_name'],
            'name' => $member['member_name'],
            'phone' => $phone,
            'masked_phone' => $this->maskPhone($phone),
        ];
    }

    private function resolveChallenge(
        string $memberNumber,
        ?string $challengeId,
        ?Device $device
    ): SelfServiceOtpChallenge {
        $normalized = $this->memberDirectory->normalizeMemberNumber($memberNumber);

        if (is_string($challengeId) && $challengeId !== '') {
            $challenge = SelfServiceOtpChallenge::query()->find($challengeId);
            if (
                !$challenge
                || $this->memberDirectory->normalizeMemberNumber($challenge->member_number) !== $normalized
            ) {
                throw new \RuntimeException('OTP challenge not found');
            }

            return $challenge;
        }

        $query = SelfServiceOtpChallenge::query()
            ->where('member_number', $normalized)
            ->whereNull('verified_at')
            ->orderByDesc('created_at');

        if ($device?->id) {
            $query->where('device_id', $device->id);
        }

        $challenge = $query->first();
        if (!$challenge) {
            throw new \RuntimeException('OTP challenge not found');
        }

        return $challenge;
    }

    private function dispatchSms(
        SelfServiceOtpChallenge $challenge,
        string $otp,
        int $ttlMinutes,
        ?string $locale
    ): void {
        if (!config('services.ictms.enabled', true)) {
            SelfServiceLog::step('send_otp.sms_skipped', [
                'reason' => 'ICTMS_SMS_ENABLED=false',
                'challenge_id' => $challenge->id,
                'otp' => $otp,
            ]);
            return;
        }

        $template = $this->notificationTemplateService->findActiveByKeyAndLocale(
            'self_service_otp_sms',
            $locale ?: 'sw',
            'sms',
            $challenge->tenant_id
        );

        $message = $template
            ? strtr($template->body, [
                '{otp}' => $otp,
                '{minutes}' => (string) $ttlMinutes,
                '{memberName}' => (string) ($challenge->member_name ?: 'Mwanachama'),
            ])
            : "NSSF: Your OTP is {$otp}. It expires in {$ttlMinutes} minutes.";

        $result = $this->notificationService->sendSms(
            recipient: $challenge->phone,
            message: $message,
            process: 'SELF SERVICE OTP',
            expiryHours: max(1, (int) ceil($ttlMinutes / 60))
        );

        SelfServiceLog::step('send_otp.ictms_response', [
            'challenge_id' => $challenge->id,
            'success' => $result['success'],
            'message' => $result['message'],
            'data' => $result['data'],
            'sms_body' => $message,
        ]);

        if (!$result['success']) {
            throw new \RuntimeException($result['message'] ?: 'Failed to send OTP SMS');
        }
    }

    /**
     * @return array{
     *     challenge_id: string,
     *     member_number: string,
     *     member_name: string,
     *     phone: string,
     *     masked_phone: string
     * }
     */
    private function presentChallenge(SelfServiceOtpChallenge $challenge): array
    {
        return [
            'challenge_id' => $challenge->id,
            'otp_challenge_id' => $challenge->id,
            'member_number' => $challenge->member_number,
            'member_name' => (string) $challenge->member_name,
            'name' => (string) $challenge->member_name,
            'phone' => $challenge->phone,
            'phone_number' => $challenge->phone,
            'masked_phone' => $this->maskPhone($challenge->phone),
        ];
    }

    private function normalizePhone(string $phone): string
    {
        $normalized = TanzaniaPhone::normalize($phone);
        if ($normalized === null) {
            throw new \RuntimeException('Member has no valid registered phone number');
        }

        return $normalized;
    }

    private function maskPhone(string $phone): string
    {
        $digits = TanzaniaPhone::normalize($phone) ?? (preg_replace('/\D/', '', $phone) ?? '');
        if (strlen($digits) < 4) {
            return '07******';
        }

        $prefix = substr($digits, 0, 2);
        $suffix = substr($digits, -2);

        return $prefix.'******'.$suffix;
    }
}
