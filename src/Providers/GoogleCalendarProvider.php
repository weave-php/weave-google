<?php

namespace Weave\Google\Providers;

use Google\Service\Calendar;
use Google\Service\Calendar\Event;
use Google\Service\Calendar\EventDateTime;
use Weave\Core\AutomationContext;
use Weave\Core\AutomationResult;
use Weave\Core\ConnectionTestResult;
use Weave\Google\GoogleClient;
use Weave\Providers\AbstractProvider;

class GoogleCalendarProvider extends AbstractProvider
{
    public static function id(): string
    {
        return 'google.calendar';
    }

    public static function label(): string
    {
        return 'Google Calendar — Create / List Events';
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
            'event_id' => 'debug-fake-event-id',
            'html_link' => null,
            'summary' => 'Debug fake event',
            'events' => [],
            'count' => 0,
        ]);
    }

    protected function execute(AutomationContext $context): AutomationResult
    {
        $calendarId = $this->resolve($context, 'calendar_id', 'primary');
        $action = (string) $this->get('action', 'create');

        if (! in_array($action, ['create', 'list'], true)) {
            return AutomationResult::failure('Google Calendar `action` must be `create` or `list`.');
        }

        try {
            $client = GoogleClient::make([Calendar::CALENDAR]);
            $service = new Calendar($client);

            return match ($action) {
                'list' => $this->listEvents($service, $context, $calendarId),
                default => $this->createEvent($service, $context, $calendarId),
            };
        } catch (\Throwable $e) {
            return AutomationResult::failure("Google Calendar error: {$e->getMessage()}");
        }
    }

    public function testConnection(): ConnectionTestResult
    {
        try {
            $client = GoogleClient::make([Calendar::CALENDAR]);
            $service = new Calendar($client);
            $service->events->listEvents('primary', ['maxResults' => 1]);

            return ConnectionTestResult::ok();
        } catch (\Throwable $e) {
            return ConnectionTestResult::failed($e->getMessage());
        }
    }

    private function createEvent(Calendar $service, AutomationContext $context, string $calendarId): AutomationResult
    {
        $summary = $this->resolve($context, 'summary');
        if (! is_string($summary) || trim($summary) === '') {
            return AutomationResult::failure('Google Calendar create requires a non-empty `summary`.');
        }

        $start = $this->resolve($context, 'start');
        $end = $this->resolve($context, 'end');
        if (! is_string($start) || $start === '' || ! is_string($end) || $end === '') {
            return AutomationResult::failure('Google Calendar create requires `start` and `end` (RFC3339 date-times).');
        }

        $event = new Event([
            'summary' => $summary,
            'description' => $this->resolve($context, 'description'),
            'location' => $this->resolve($context, 'location'),
            'start' => new EventDateTime([
                'dateTime' => $start,
                'timeZone' => (string) $this->get('timezone', 'UTC'),
            ]),
            'end' => new EventDateTime([
                'dateTime' => $end,
                'timeZone' => (string) $this->get('timezone', 'UTC'),
            ]),
        ]);

        $attendees = $this->resolve($context, 'attendees', []);
        if (! empty($attendees)) {
            $event->setAttendees(array_map(
                fn ($email) => ['email' => $email],
                (array) $attendees
            ));
        }

        $created = $service->events->insert($calendarId, $event);

        return AutomationResult::success([
            'event_id' => $created->getId(),
            'html_link' => $created->getHtmlLink(),
            'summary' => $created->getSummary(),
            'start' => $created->getStart()->getDateTime(),
            'end' => $created->getEnd()->getDateTime(),
        ]);
    }

    private function listEvents(Calendar $service, AutomationContext $context, string $calendarId): AutomationResult
    {
        $params = [
            'maxResults' => (int) $this->get('limit', 10),
            'orderBy' => 'startTime',
            'singleEvents' => true,
            'timeMin' => $this->resolve($context, 'time_min', now()->toRfc3339String()),
            'timeMax' => $this->resolve($context, 'time_max'),
        ];

        $results = $service->events->listEvents($calendarId, array_filter($params, fn ($v) => $v !== null && $v !== ''));
        $events = array_map(fn ($e) => [
            'event_id' => $e->getId(),
            'summary' => $e->getSummary(),
            'start' => $e->getStart()->getDateTime() ?? $e->getStart()->getDate(),
            'end' => $e->getEnd()->getDateTime() ?? $e->getEnd()->getDate(),
            'html_link' => $e->getHtmlLink(),
        ], $results->getItems());

        return AutomationResult::success([
            'events' => $events,
            'count' => count($events),
        ]);
    }
}
