<?php

namespace Weave\Google;

use Illuminate\Support\ServiceProvider;
use Weave\Core\AutomationRegistry;
use Weave\Google\Providers\GmailReadProvider;
use Weave\Google\Providers\GmailSendProvider;
use Weave\Google\Providers\GoogleCalendarProvider;
use Weave\Google\Providers\GoogleSheetsReadProvider;
use Weave\Google\Providers\GoogleSheetsWriteProvider;

class GoogleServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        static::registerAutomationProviders();
    }

    public static function registerAutomationProviders(): void
    {
        AutomationRegistry::registerProvider(GmailSendProvider::class);
        AutomationRegistry::registerProvider(GmailReadProvider::class);
        AutomationRegistry::registerProvider(GoogleSheetsReadProvider::class);
        AutomationRegistry::registerProvider(GoogleSheetsWriteProvider::class);
        AutomationRegistry::registerProvider(GoogleCalendarProvider::class);
    }
}
