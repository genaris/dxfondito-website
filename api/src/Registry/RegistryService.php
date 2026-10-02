<?php

declare(strict_types=1);

namespace DxFondito\Registry;

use DxFondito\Audit\AuditLog;
use DxFondito\Auth\User;
use DxFondito\Http\HttpException;
use RuntimeException;

/**
 * The official registries of licensees of Argentina and Uruguay, for the name on the QSL cards (FR-QSL-3a).
 * An administrator updates them with a button. The API downloads each list (system design, section 6.4).
 */
final class RegistryService
{
    public const ENACOM_URL = 'https://hertz.enacom.gob.ar/se/portal/arg/publico/ListadoRadioaficionado.php';
    public const URSEC_PAGE = 'https://www.gub.uy/unidad-reguladora-servicios-comunicaciones/tematica/radioaficionados';
    private const URSEC_BASE = 'https://www.gub.uy';

    /**
     * The smallest plausible list of each country. A smaller list means a change of the source page,
     * and the API keeps the old list.
     */
    private const MINIMUM = ['AR' => 5000, 'UY' => 300];

    public function __construct(
        private readonly LicenseeStore $licensees,
        private readonly HttpClient $http,
        private readonly AuditLog $audit,
        private readonly string $enacomCaFile,
    ) {
    }

    /**
     * @return list<array{country: string, count: int, sourceUrl: string, updatedAt: string}>
     */
    public function updates(): array
    {
        return $this->licensees->updates();
    }

    /**
     * Downloads the list of a country and replaces the licensees of that country.
     *
     * @return int The number of licensees.
     * @throws HttpException 404 for an unknown country, 502 if the download or the list is not correct.
     */
    public function update(User $actor, string $country): int
    {
        $country = strtoupper($country);
        if (!isset(self::MINIMUM[$country])) {
            throw new HttpException(404, 'The registry does not exist');
        }
        try {
            [$list, $source] = $country === 'AR' ? $this->enacom() : $this->ursec();
        } catch (RuntimeException $e) {
            throw new HttpException(502, $e->getMessage());
        }
        if (count($list) < self::MINIMUM[$country]) {
            throw new HttpException(502, sprintf('The list has only %d licensees. The source page possibly changed', count($list)));
        }

        $this->licensees->replace($country, $list, $source);
        $this->audit->record($actor->id, AuditLog::REGISTRY_UPDATE, null, ['country' => $country, 'licensees' => count($list)]);

        return count($list);
    }

    /**
     * The page of ENACOM shows all licensees after a POST request with its CSRF token.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function enacom(): array
    {
        $form = $this->http->request(self::ENACOM_URL, null, $this->enacomCaFile);
        if (preg_match('/name="csrf_token" value="([^"]+)"/', $form, $match) !== 1) {
            throw new RuntimeException('The page of ENACOM has no form');
        }
        $html = $this->http->request(self::ENACOM_URL, ['csrf_token' => $match[1], 'valor' => '', 'mostrarTodos' => '1'], $this->enacomCaFile);

        return [RegistryList::enacom($html), self::ENACOM_URL];
    }

    /**
     * The page of URSEC links the newest list as an ODS file.
     *
     * @return array{0: array<string, string>, 1: string}
     */
    private function ursec(): array
    {
        $link = RegistryList::ursecLink($this->http->request(self::URSEC_PAGE), self::URSEC_BASE);

        return [RegistryList::ursec($this->http->request(str_replace(' ', '%20', $link))), $link];
    }
}
