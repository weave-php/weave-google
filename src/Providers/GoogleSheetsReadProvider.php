<?php

namespace Weave\Google\Providers;

use Google\Service\Sheets;
use Weave\Core\AutomationContext;
use Weave\Core\AutomationResult;
use Weave\Core\ConnectionTestResult;
use Weave\Google\GoogleClient;
use Weave\Providers\AbstractProvider;

class GoogleSheetsReadProvider extends AbstractProvider
{
    private const SAMPLE_SPREADSHEET_ID = '1BxiMVs0XRA5nFMdKvBdBZjgmUUqptlbs74OgvE2upms';

    public static function id(): string
    {
        return 'google.sheets.read';
    }

    public static function label(): string
    {
        return 'Google Sheets — Read';
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
            'rows' => [],
            'count' => 0,
            'range' => 'Sheet1!A1',
        ]);
    }

    protected function execute(AutomationContext $context): AutomationResult
    {
        $spreadsheetId = $this->resolve($context, 'spreadsheet_id');

        if (! is_string($spreadsheetId) || trim($spreadsheetId) === '') {
            return AutomationResult::failure('Google Sheets Read requires a non-empty `spreadsheet_id`.');
        }

        $range = $this->resolve($context, 'range', 'Sheet1');
        $firstRowKeys = (bool) $this->get('first_row_as_keys', true);

        try {
            $client = GoogleClient::make([Sheets::SPREADSHEETS_READONLY]);
            $service = new Sheets($client);

            $response = $service->spreadsheets_values->get($spreadsheetId, $range);
            $values = $response->getValues() ?? [];
        } catch (\Throwable $e) {
            return AutomationResult::failure("Google Sheets Read error: {$e->getMessage()}");
        }

        if ($firstRowKeys && count($values) > 1) {
            $keys = array_shift($values);
            if (! is_array($keys)) {
                $rows = $values;
            } else {
                $rows = [];
                foreach ($values as $row) {
                    if (! is_array($row)) {
                        $row = [];
                    }
                    $padded = array_pad($row, count($keys), null);
                    $combined = array_combine($keys, $padded);
                    if ($combined === false) {
                        return AutomationResult::failure('Google Sheets Read: header row must contain unique column names when using first_row_as_keys.');
                    }
                    $rows[] = $combined;
                }
            }
        } else {
            $rows = $values;
        }

        return AutomationResult::success([
            'rows' => $rows,
            'count' => count($rows),
            'range' => $response->getRange(),
        ]);
    }

    public function testConnection(): ConnectionTestResult
    {
        try {
            $client = GoogleClient::make([Sheets::SPREADSHEETS_READONLY]);
            $service = new Sheets($client);
            $service->spreadsheets->get(self::SAMPLE_SPREADSHEET_ID, ['fields' => 'spreadsheetId']);

            return ConnectionTestResult::ok();
        } catch (\Throwable $e) {
            return ConnectionTestResult::failed($e->getMessage());
        }
    }
}
