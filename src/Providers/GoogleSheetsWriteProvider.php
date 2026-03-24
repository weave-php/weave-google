<?php

namespace Weave\Google\Providers;

use Google\Service\Sheets;
use Google\Service\Sheets\ValueRange;
use Weave\Core\AutomationContext;
use Weave\Core\AutomationResult;
use Weave\Core\ConnectionTestResult;
use Weave\Google\GoogleClient;
use Weave\Providers\AbstractProvider;

class GoogleSheetsWriteProvider extends AbstractProvider
{
    private const SAMPLE_SPREADSHEET_ID = '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms';

    public static function id(): string
    {
        return 'google.sheets.write';
    }

    public static function label(): string
    {
        return 'Google Sheets — Write/Append';
    }

    public function credentials(): array
    {
        return [
            'GOOGLE_SERVICE_ACCOUNT_JSON' => ['label' => 'Service Account JSON', 'secret' => true, 'required' => false],
            'GOOGLE_ACCESS_TOKEN' => ['label' => 'Access Token', 'secret' => true, 'required' => false],
            'GOOGLE_REFRESH_TOKEN' => ['label' => 'Refresh Token', 'secret' => true, 'required' => false],
        ];
    }

    protected function debugFakeData(AutomationContext $context): AutomationResult
    {
        return AutomationResult::success([
            'updated_range' => 'Sheet1!A1',
            'updated_rows' => 1,
            'updated_cells' => 1,
        ]);
    }

    protected function execute(AutomationContext $context): AutomationResult
    {
        $spreadsheetId = $this->resolve($context, 'spreadsheet_id');

        if (! is_string($spreadsheetId) || trim($spreadsheetId) === '') {
            return AutomationResult::failure('Google Sheets Write requires a non-empty `spreadsheet_id`.');
        }

        $range = $this->resolve($context, 'range', 'Sheet1');
        $rows = $this->resolve($context, 'rows');
        $mode = (string) $this->get('mode', 'append');

        if (! in_array($mode, ['append', 'overwrite'], true)) {
            return AutomationResult::failure('Google Sheets Write `mode` must be `append` or `overwrite`.');
        }

        if (! is_array($rows)) {
            $rows = [$rows];
        }

        if (! is_array($rows[0] ?? null)) {
            $rows = [$rows];
        }

        try {
            $client = GoogleClient::make([Sheets::SPREADSHEETS]);
            $service = new Sheets($client);

            $body = new ValueRange(['values' => $rows]);
            $params = ['valueInputOption' => (string) $this->get('value_input_option', 'USER_ENTERED')];

            if ($mode === 'append') {
                $result = $service->spreadsheets_values->append($spreadsheetId, $range, $body, $params);
                $updates = $result->getUpdates();
            } else {
                $result = $service->spreadsheets_values->update($spreadsheetId, $range, $body, $params);
                $updates = $result;
            }
        } catch (\Throwable $e) {
            return AutomationResult::failure("Google Sheets Write error: {$e->getMessage()}");
        }

        return AutomationResult::success([
            'updated_range' => $updates->getUpdatedRange(),
            'updated_rows' => $updates->getUpdatedRows(),
            'updated_cells' => $updates->getUpdatedCells(),
        ]);
    }

    public function testConnection(): ConnectionTestResult
    {
        try {
            $client = GoogleClient::make([Sheets::SPREADSHEETS]);
            $service = new Sheets($client);
            $service->spreadsheets->get(self::SAMPLE_SPREADSHEET_ID, ['fields' => 'spreadsheetId']);

            return ConnectionTestResult::ok();
        } catch (\Throwable $e) {
            return ConnectionTestResult::failed($e->getMessage());
        }
    }
}
