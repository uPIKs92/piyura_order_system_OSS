<?php

namespace App\Services;

use App\Support\TenantSettings;
use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;

class GoogleSheetsService
{
    public function __construct(private GoogleOAuthService $oauth) {}

    /**
     * @return array<int, array<string, mixed>>
     */
    public function readTab(int $tenantId, string $spreadsheetId, string $tabName): array
    {
        $service = $this->sheetsService($tenantId);
        $range = $this->quoteTab($tabName);
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues() ?? [];

        if ($values === []) {
            return [];
        }

        $headers = array_map(fn ($h) => trim((string) $h), array_shift($values));

        $rows = [];
        foreach ($values as $row) {
            $assoc = [];
            foreach ($headers as $index => $header) {
                if ($header === '') {
                    continue;
                }
                $assoc[$header] = $row[$index] ?? null;
            }
            if (array_filter($assoc, fn ($v) => $v !== null && $v !== '') === []) {
                continue;
            }
            $rows[] = $assoc;
        }

        return $rows;
    }

    /**
     * @param  array<int, scalar|null>  $row
     */
    public function appendRow(int $tenantId, string $spreadsheetId, string $tabName, array $row): void
    {
        $service = $this->sheetsService($tenantId);
        $range = $this->quoteTab($tabName);
        $body = new ValueRange([
            'values' => [array_values($row)],
        ]);

        $service->spreadsheets_values->append(
            $spreadsheetId,
            $range,
            $body,
            [
                'valueInputOption' => 'USER_ENTERED',
                'insertDataOption' => 'INSERT_ROWS',
            ]
        );
    }

    /**
     * Ensure reporting tab has a header row when empty.
     *
     * @param  array<int, string>  $headers
     */
    public function ensureHeaderRow(int $tenantId, string $spreadsheetId, string $tabName, array $headers): void
    {
        $service = $this->sheetsService($tenantId);
        $range = $this->quoteTab($tabName).'!A1:Z1';
        $response = $service->spreadsheets_values->get($spreadsheetId, $range);
        $values = $response->getValues() ?? [];

        if ($values !== []) {
            return;
        }

        $body = new ValueRange([
            'values' => [$headers],
        ]);

        $service->spreadsheets_values->update(
            $spreadsheetId,
            $this->quoteTab($tabName).'!A1',
            $body,
            ['valueInputOption' => 'USER_ENTERED']
        );
    }

    public function isReadyForSync(int $tenantId): bool
    {
        $settings = TenantSettings::for($tenantId);

        return $settings->sheetsSyncEnabled()
            && $settings->googleConnected()
            && filled($settings->sheetsSpreadsheetId());
    }

    private function sheetsService(int $tenantId): Sheets
    {
        return new Sheets($this->oauth->getClientForTenant($tenantId));
    }

    private function quoteTab(string $tabName): string
    {
        $escaped = str_replace("'", "''", $tabName);

        return "'{$escaped}'";
    }
}
