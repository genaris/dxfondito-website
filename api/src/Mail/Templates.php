<?php

declare(strict_types=1);

namespace DxFondito\Mail;

use DxFondito\Audit\AuditLog;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;

/**
 * The subject and the body of the messages (FR-MAIL-10): a general text for each kind, and an own text for an
 * activity (QSL cards) or a season (certificates). Without a saved text, the default text of MessageTemplate.
 */
final class Templates
{
    public const GENERAL = 'default';
    private const MAX_SUBJECT = 255;
    private const MAX_BODY = 10000;

    public function __construct(
        private readonly MailStore $store,
        private readonly AuditLog $audit,
    ) {
    }

    /**
     * The text for a scope: its own text, or the general text.
     *
     * @return array{subject: string, body: string, own: bool}
     */
    public function get(string $kind, string $scope = self::GENERAL): array
    {
        $own = $scope === self::GENERAL ? null : $this->store->template($kind, $scope);
        if ($own !== null) {
            return $own + ['own' => true];
        }
        $general = $this->store->template($kind, self::GENERAL) ?? MessageTemplate::DEFAULTS[$kind];

        return $general + ['own' => $scope === self::GENERAL && $this->store->template($kind, self::GENERAL) !== null];
    }

    /**
     * @throws HttpException 422 for an empty or long text, or an unknown variable.
     */
    public function save(User $actor, string $kind, string $scope, string $subject, string $body): void
    {
        self::check($kind, $subject, $body);
        $this->store->saveTemplate($kind, $scope, trim($subject), $body, $actor->id);
        $this->audit->record($actor->id, AuditLog::MAIL_TEMPLATE_SAVE, null, ['kind' => $kind, 'scope' => $scope]);
    }

    /**
     * Deletes the own text of a scope: the scope uses the general text again. For the general scope, the default text.
     */
    public function reset(User $actor, string $kind, string $scope): void
    {
        $this->store->deleteTemplate($kind, $scope);
        $this->audit->record($actor->id, AuditLog::MAIL_TEMPLATE_DELETE, null, ['kind' => $kind, 'scope' => $scope]);
    }

    /**
     * @throws HttpException 422
     */
    public static function check(string $kind, string $subject, string $body): void
    {
        if (trim($subject) === '' || mb_strlen($subject) > self::MAX_SUBJECT) {
            throw new HttpException(422, 'The subject must have 1 to 255 characters');
        }
        if (trim($body) === '' || mb_strlen($body) > self::MAX_BODY) {
            throw new HttpException(422, 'The body must have 1 to 10000 characters');
        }
        $unknown = MessageTemplate::unknownVariables($kind, $subject . "\n" . $body);
        if ($unknown !== []) {
            throw new HttpException(422, 'Unknown variables: ' . implode(', ', array_map(static fn (string $name): string => '{' . $name . '}', $unknown)));
        }
    }

    public static function activityScope(int $activityId): string
    {
        return 'activity:' . $activityId;
    }

    public static function seasonScope(int $season): string
    {
        return 'season:' . $season;
    }
}
