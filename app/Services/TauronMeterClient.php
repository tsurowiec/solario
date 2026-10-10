<?php

namespace App\Services;

use App\Exceptions\TauronLoginException;
use Carbon\CarbonInterface;
use GuzzleHttp\Cookie\CookieJar;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use RuntimeException;

/**
 * Downloads the hourly meter CSV from Tauron eLicznik — the same file that is exported by hand
 * and read by MeterCsvImporter.
 *
 * Ported from https://github.com/mlesniew/elicznik (MIT):
 * log in via Keycloak (GET the login page, POST the credentials to the form's action),
 * optionally select the metering point, then GET /energia/do/dane with the CSV export form.
 * Tauron redirects to /blokada when an account makes too many requests, and shows a message on the
 * login page after too many logins; both block the account for 8 hours.
 */
class TauronMeterClient
{
    private const LOGIN_URL = 'https://logowanie.tauron-dystrybucja.pl/login';

    private const SERVICE_URL = 'https://elicznik.tauron-dystrybucja.pl';

    private const SITE_URL = self::SERVICE_URL.'/ustaw_punkt';

    private const DATA_URL = self::SERVICE_URL.'/energia/do/dane';

    /** Shown on the login page when the account is blocked after too many logins. */
    private const LOGIN_LIMIT_MESSAGE = 'Przekroczono maksymalną liczbę logowań';

    private CookieJar $cookies;

    public function __construct(
        private readonly ?string $username,
        private readonly ?string $password,
        private readonly ?string $site = null,
    ) {}

    public function fetchCsv(CarbonInterface $from, CarbonInterface $to): string
    {
        if (! $this->username || ! $this->password) {
            throw new RuntimeException('Tauron credentials are not configured (TAURON_USERNAME, TAURON_PASSWORD).');
        }

        $this->login();

        // The export is sent uncompressed but with a bogus "Content-Encoding: UTF-8" header, which cURL rejects.
        $response = $this->check($this->http()->withOptions(['decode_content' => false])->get(self::DATA_URL, [
            'form[from]' => $from->format('d.m.Y'),
            'form[to]' => $to->format('d.m.Y'),
            'form[type]' => 'godzin',
            'form[energy][consum]' => 1,
            'form[energy][oze]' => 1,
            'form[energy][netto]' => 1,
            'form[energy][netto_oze]' => 1,
            'form[fileType]' => 'CSV',
        ]));

        if (! str_contains($response->body(), 'Rodzaj')) {
            throw new RuntimeException('Tauron did not return the CSV export.');
        }

        return $response->body();
    }

    private function login(): void
    {
        $this->cookies = new CookieJar;

        $page = $this->check($this->http()->get(self::LOGIN_URL, ['service' => self::SERVICE_URL]));

        if (! preg_match('/<form[^>]+id="kc-form-login"[^>]+action="([^"]+)"/', $page->body(), $m)) {
            throw new RuntimeException('Tauron login form not found.');
        }

        $result = $this->check($this->http()->asForm()->post(html_entity_decode($m[1]), [
            'username' => $this->username,
            'password' => $this->password,
            'credentialId' => '',
        ]));

        // A successful login redirects away; the form is shown again when the credentials are wrong.
        if (str_contains($result->body(), 'id="kc-form-login"')) {
            throw new TauronLoginException('Tauron login failed: invalid username or password.');
        }

        if ($this->site) {
            $this->check($this->http()->asForm()->post(self::SITE_URL, ['site[client]' => $this->site]));
        }
    }

    private function http(): PendingRequest
    {
        return Http::withOptions([
            'cookies' => $this->cookies,
            // Tauron's servers need older ciphers than OpenSSL allows by default.
            'curl' => [CURLOPT_SSL_CIPHER_LIST => 'DEFAULT@SECLEVEL=1'],
        ])->timeout(60);
    }

    private function check(Response $response): Response
    {
        if (str_ends_with((string) $response->effectiveUri()?->getPath(), '/blokada')) {
            throw new TauronLoginException('Tauron has temporarily blocked the account (too many requests).');
        }

        if (str_contains($response->body(), self::LOGIN_LIMIT_MESSAGE)) {
            throw new TauronLoginException('Tauron has temporarily blocked the account (too many logins).');
        }

        return $response->throw();
    }
}
