<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class IctmsRegisterCommand extends Command
{
    protected $signature = 'ictms:register
                            {--dry-run : Show payloads without sending}
                            {--token= : ICTMS access token (if not set, you will be prompted)}';

    protected $description = 'Register QMS with ICTMS (access management + system monitoring)';

    public function handle(): int
    {
        $qmsBaseUrl = rtrim(config('services.ictms.qms_base_url', config('app.url')), '/');
        $qmsShortCode = config('services.ictms.qms_short_code', 'QMS');
        $ictmsApiBase = rtrim(config('services.ictms.api_base', 'https://ictmspre-api.nssf.go.tz'), '/');

        $this->info('Register QMS with ICTMS');
        $this->line("QMS base URL: {$qmsBaseUrl}");
        $this->line("QMS short code: {$qmsShortCode}");
        $this->line("ICTMS API base: {$ictmsApiBase}");
        $this->newLine();

        $accessPayload = [
            'SHORT_CODE' => $qmsShortCode,
            'MODULE_ENDPOINT' => $qmsBaseUrl . '/api/modules',
            'ROLE_ENDPOINT' => $qmsBaseUrl . '/api/module/roles',
            'ASSIGN_ENDPOINT' => $qmsBaseUrl . '/api/assign-role',
            'USER_ENDPOINT' => $qmsBaseUrl . '/api/user/roles',
            'USER_MODULE_ENDPOINT' => $qmsBaseUrl . '/api/module/users',
            'REVOKE_ENDPOINT' => $qmsBaseUrl . '/api/access/revoke',
            'ASSIGN_STATUS' => 'A',
        ];

        $systemPayload = [
            'SYSTEM_CODE' => $qmsShortCode,
            'SYSTEM_SERVICE' => $qmsBaseUrl . '/api/ictms/service',
            'SYSTEM_TYPE' => 'Internal',
            'SYSTEM_INTERFACE' => $qmsBaseUrl . '/api/ictms/interface',
            'SYSTEM_STATUS' => 'Active',
        ];

        if ($this->option('dry-run')) {
            $this->warn('Dry run - no requests sent.');
            $this->line('Access payload: ' . json_encode($accessPayload, JSON_PRETTY_PRINT));
            $this->newLine();
            $this->line('System payload: ' . json_encode($systemPayload, JSON_PRETTY_PRINT));
            return Command::SUCCESS;
        }

        $accessToken = $this->option('token');
        if ($accessToken === null || $accessToken === '') {
            $this->line('ICTMS may require an access token to register.');
            $accessToken = $this->secret('Enter ICTMS access token (or leave empty if not required)') ?? '';
        }
        $accessToken = (string) $accessToken;

        $headers = array_filter([
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
            'Authorization' => $accessToken !== '' ? 'Bearer ' . $accessToken : null,
        ]);

        $accessUrl = $ictmsApiBase . '/api/access/add-software-access';
        $this->info("POST {$accessUrl}");
        $accessResponse = Http::withHeaders($headers)
            ->timeout(30)
            ->post($accessUrl, $accessPayload);

        if ($accessResponse->successful()) {
            $this->info('  Access registration: OK');
        } else {
            $this->error('  Access registration failed: ' . $accessResponse->status() . ' - ' . $accessResponse->body());
        }

        $systemUrl = $ictmsApiBase . '/api/add-system';
        $this->info("POST {$systemUrl}");
        $systemResponse = Http::withHeaders($headers)
            ->timeout(30)
            ->post($systemUrl, $systemPayload);

        if ($systemResponse->successful()) {
            $this->info('  System monitoring registration: OK');
        } else {
            $this->error('  System monitoring registration failed: ' . $systemResponse->status() . ' - ' . $systemResponse->body());
        }

        $this->newLine();
        if ($accessResponse->successful() && $systemResponse->successful()) {
            $this->info('ICTMS registration completed.');
            return Command::SUCCESS;
        }

        $this->warn('One or more registration steps failed. Check output above.');
        return Command::FAILURE;
    }
}
