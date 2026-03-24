<?php

namespace Weave\Google\Providers;

use Google\Service\Gmail;
use Weave\Core\AutomationContext;
use Weave\Core\AutomationResult;
use Weave\Core\ConnectionTestResult;
use Weave\Google\GoogleClient;
use Weave\Providers\AbstractProvider;

class GmailReadProvider extends AbstractProvider
{
    public static function id(): string
    {
        return 'google.gmail.read';
    }

    public static function label(): string
    {
        return 'Gmail — Read Messages';
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
            'messages' => [],
            'count' => 0,
        ]);
    }

    protected function execute(AutomationContext $context): AutomationResult
    {
        try {
            $client = GoogleClient::make([Gmail::GMAIL_READONLY]);
            $service = new Gmail($client);

            $messageId = $this->resolve($context, 'message_id');

            if (is_string($messageId) && $messageId !== '') {
                return $this->fetchSingle($service, $messageId);
            }

            return $this->fetchList($service, $context);
        } catch (\Throwable $e) {
            return AutomationResult::failure("Gmail Read error: {$e->getMessage()}");
        }
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

    private function fetchSingle(Gmail $service, string $messageId): AutomationResult
    {
        $message = $service->users_messages->get('me', $messageId, ['format' => 'full']);
        $headers = $this->parseHeaders($message->getPayload()->getHeaders());

        return AutomationResult::success([
            'id' => $message->getId(),
            'subject' => $headers['Subject'] ?? '',
            'from' => $headers['From'] ?? '',
            'to' => $headers['To'] ?? '',
            'date' => $headers['Date'] ?? '',
            'body' => $this->decodeBody($message->getPayload()),
            'snippet' => $message->getSnippet(),
        ]);
    }

    private function fetchList(Gmail $service, AutomationContext $context): AutomationResult
    {
        $query = $this->resolve($context, 'query', 'is:unread');
        $limit = (int) $this->get('limit', 10);

        $list = $service->users_messages->listUsersMessages('me', ['q' => $query, 'maxResults' => $limit]);
        $messages = $list->getMessages() ?? [];

        $results = array_map(function ($msg) use ($service): array {
            $full = $service->users_messages->get('me', $msg->getId(), ['format' => 'metadata']);
            $headers = $this->parseHeaders($full->getPayload()->getHeaders());

            return [
                'id' => $msg->getId(),
                'subject' => $headers['Subject'] ?? '',
                'from' => $headers['From'] ?? '',
                'date' => $headers['Date'] ?? '',
                'snippet' => $full->getSnippet(),
            ];
        }, $messages);

        return AutomationResult::success([
            'messages' => $results,
            'count' => count($results),
        ]);
    }

    private function parseHeaders(array $headers): array
    {
        $parsed = [];
        foreach ($headers as $header) {
            $parsed[$header->getName()] = $header->getValue();
        }

        return $parsed;
    }

    private function decodeBody(\Google\Service\Gmail\MessagePart $payload): string
    {
        if ($payload->getBody()->getData()) {
            return base64_decode(strtr($payload->getBody()->getData(), '-_', '+/'), true) ?: '';
        }

        foreach ($payload->getParts() ?? [] as $part) {
            if (in_array($part->getMimeType(), ['text/html', 'text/plain'], true)) {
                return base64_decode(strtr($part->getBody()->getData(), '-_', '+/'), true) ?: '';
            }
        }

        return '';
    }
}
