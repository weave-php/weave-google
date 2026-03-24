<?php

use Weave\Core\AutomationRegistry;
use Weave\Google\Providers\GmailReadProvider;
use Weave\Google\Providers\GmailSendProvider;
use Weave\Google\Providers\GoogleCalendarProvider;
use Weave\Google\Providers\GoogleSheetsReadProvider;
use Weave\Google\Providers\GoogleSheetsWriteProvider;

it('registers all Google automation providers', function (): void {
    expect(AutomationRegistry::provider('google.gmail.send'))->toBe(GmailSendProvider::class)
        ->and(AutomationRegistry::provider('google.gmail.read'))->toBe(GmailReadProvider::class)
        ->and(AutomationRegistry::provider('google.sheets.read'))->toBe(GoogleSheetsReadProvider::class)
        ->and(AutomationRegistry::provider('google.sheets.write'))->toBe(GoogleSheetsWriteProvider::class)
        ->and(AutomationRegistry::provider('google.calendar'))->toBe(GoogleCalendarProvider::class);
});
