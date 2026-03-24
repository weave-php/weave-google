<?php

namespace Weave\Google\Providers;

use Google\Service\Gmail;
use Google\Service\Gmail\Message;
use Weave\Core\AutomationContext;
use Weave\Core\AutomationResult;
use Weave\Core\ConnectionTestResult;
use Weave\Google\GoogleClient;
use Weave\Providers\AbstractProvider;

class GmailSendProvider extends AbstractProvider
{
    public static function id(): string
    {
        return 'google.gmail.send';
    }

    public static function label(): string
    {
        return 'Gmail — Send Email';
    }

    public function credentials(): array
    {
        return [
            'GOOGLE_SERVICE_ACCOUNT_JSON' => ['label' => 'Service Account JSON', 'secret' => true, 'required' => false],
            'GOOGLE_CLIENT_ID' => ['label' => 'OAuth Client ID', 'secret' => false, 'required' => false],
            'GOOGLE_CLIENT_SECRET' => ['label' => 'OAuth Client Secret', 'secret' => true, 'required' => false],
            'GOOGLE_ACCESS_TOKEN' => ['label' => 'Access Token', 'secret' => true, 'required' => false],
            'GOOGLE_REFRESH_TOKEN' => ['label' => 'Refresh Token', 'secret' => true, 'required' => false],
        ];
    }

    protected function debugFakeData(AutomationContext $context): AutomationResult
    {
        return AutomationResult::success([
            'message_id' => 'debug-fake-message-id',
            'thread_id' => 'debug-fake-thread-id',
        ]);
    }

    protected function execute(AutomationContext $context): AutomationResult
    {
        $to = $this->resolve($context, 'to');
        $subject = $this->resolve($context, 'subject');
        $body = $this->resolve($context, 'body');
        $from = $this->resolve($context, 'from', 'me');

        if (! is_string($to) || trim($to) === '') {
            return AutomationResult::failure('Gmail Send requires a non-empty `to`.');
        }
        if (! is_string($subject) || trim($subject) === '') {
            return AutomationResult::failure('Gmail Send requires a non-empty `subject`.');
        }
        if (! is_string($body)) {
            return AutomationResult::failure('Gmail Send requires `body`.');
        }

        try {
            $client = GoogleClient::make([Gmail::GMAIL_SEND]);
            $service = new Gmail($client);

            $raw = $this->buildRawEmail((string) $from, $to, $subject, $body);
            $message = new Message;
            $message->setRaw($raw);

            $sent = $service->users_messages->send('me', $message);
        } catch (\Throwable $e) {
            return AutomationResult::failure("Gmail Send error: {$e->getMessage()}");
        }

        return AutomationResult::success([
            'message_id' => $sent->getId(),
            'thread_id' => $sent->getThreadId(),
        ]);
    }

    public function testConnection(): ConnectionTestResult
    {
        try {
            $client = GoogleClient::make([Gmail::GMAIL_READONLY]);
            $service = new Gmail($client);
            $service->users->getProfile('me');

            return ConnectionTestResult::ok();
        } catch (\Throwable $e) {
            return ConnectionTestResult::failed($e->getMessage());
        }
    }

    private function buildRawEmail(string $from, string $to, string $subject, string $body): string
    {
        $mime = implode("\r\n", [
            "From: {$from}",
            "To: {$to}",
            "Subject: {$subject}",
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8',
            '',
            $body,
        ]);

        return rtrim(strtr(base64_encode($mime), '+/', '-_'), '=');
    }
}
