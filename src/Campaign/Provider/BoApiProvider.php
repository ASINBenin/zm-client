<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Provider;

use DateTimeImmutable;
use DateTimeInterface;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\BadResponseException;
use GuzzleHttp\Exception\GuzzleException;
use GuzzleHttp\HandlerStack;
use JsonException;
use Monarc\FrontOffice\Campaign\Exception\BoApiException;
use Monarc\FrontOffice\Exception\InvalidConfigurationException;

/**
 * Appels de l'entité vers l'API du BackOffice, authentifiés par son jeton (il n'y a aucune connexion d'utilisateur).
 */
class BoApiProvider
{
    private const DEFAULT_TIMEOUT = 30;
    private const UPLOAD_TIMEOUT = 120;

    /** @var array */
    private $config;

    /** @var callable|null */
    private $handler;

    private ?Client $guzzleClient = null;

    public function __construct(array $config, callable $handler = null)
    {
        $this->config = $config['boApi'] ?? [];
        $this->handler = $handler;
    }

    /**
     * Campagnes de l'entité ; avec $since, seulement celles modifiées depuis. serverTime est l'heure du BO.
     *
     * @return array{campaigns: array[], serverTime: DateTimeImmutable}
     *
     * @throws BoApiException|InvalidConfigurationException
     */
    public function getCampaigns(?DateTimeInterface $since): array
    {
        $data = $this->request(
            'GET',
            'campaigns',
            ['query' => $since === null ? [] : ['since' => $since->format(DateTimeInterface::ATOM)]]
        );

        $serverTime = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, (string)($data['serverTime'] ?? ''));
        if (!is_array($data['campaigns'] ?? null) || $serverTime === false) {
            throw new BoApiException('Unexpected BackOffice response format.');
        }

        return ['campaigns' => $data['campaigns'], 'serverTime' => $serverTime];
    }

    /**
     * Le BO enregistre la participation de l'entité à la campagne ; sans effet si elle participe déjà.
     * Une participation refusée (campagne hors période, non invitée) lève une BoApiException de code 412.
     *
     * @return string Statut de la participation dans le BO.
     *
     * @throws BoApiException|InvalidConfigurationException
     */
    public function participate(int $boCampaignId): string
    {
        $data = $this->request('POST', 'campaigns/' . $boCampaignId . '/participation');
        if (!is_string($data['participation']['status'] ?? null)) {
            throw new BoApiException('Unexpected BackOffice response format.');
        }

        return $data['participation']['status'];
    }

    /**
     * Envoie le rapport PDF d'une analyse : nouvelle version dans la campagne, ou hors campagne si $boCampaignId est
     * null. Un envoi refusé (campagne clôturée, entité sans participation) lève une BoApiException de code 412.
     *
     * @return array Version enregistrée par le BO (id, version, fileSize, sha256, submittedAt...).
     *
     * @throws BoApiException|InvalidConfigurationException
     */
    public function submitReport(
        string $pdfPath,
        int $anrId,
        string $analysisLabel,
        bool $isSigned,
        string $submittedBy,
        ?int $boCampaignId
    ): array {
        $fields = [
            'anrId' => $anrId,
            'analysisLabel' => $analysisLabel,
            'isSigned' => (int)$isSigned,
            'submittedBy' => $submittedBy,
        ];
        if ($boCampaignId !== null) {
            $fields['campaignId'] = $boCampaignId;
        }
        $multipart = [['name' => 'file', 'contents' => fopen($pdfPath, 'rb'), 'filename' => 'report.pdf']];
        foreach ($fields as $name => $value) {
            $multipart[] = ['name' => $name, 'contents' => (string)$value];
        }

        $data = $this->request('POST', 'submissions', ['multipart' => $multipart, 'timeout' => self::UPLOAD_TIMEOUT]);
        if (!is_array($data['submission'] ?? null) || !isset($data['submission']['id'], $data['submission']['version'])) {
            throw new BoApiException('Unexpected BackOffice response format.');
        }

        return $data['submission'];
    }

    /**
     * Versions de rapport envoyées par l'entité, avec l'appréciation de l'ASIN quand elle existe.
     *
     * @return array[]
     *
     * @throws BoApiException|InvalidConfigurationException
     */
    public function getSubmissions(): array
    {
        $data = $this->request('GET', 'submissions');
        if (!is_array($data['submissions'] ?? null)) {
            throw new BoApiException('Unexpected BackOffice response format.');
        }

        return $data['submissions'];
    }

    /**
     * @throws BoApiException|InvalidConfigurationException
     */
    private function request(string $method, string $uri, array $options = []): array
    {
        try {
            $response = $this->getClient()->request($method, $uri, $options);

            return json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException | JsonException $e) {
            throw new BoApiException($this->getErrorMessage($e), (int)$e->getCode(), $e);
        }
    }

    /**
     * Motif du refus renvoyé par le BO ; à défaut, celui de la requête.
     */
    private function getErrorMessage(GuzzleException | JsonException $e): string
    {
        if ($e instanceof BadResponseException) {
            $body = json_decode((string)$e->getResponse()->getBody(), true);
            if (is_string($body['errors'][0]['message'] ?? null)) {
                return $body['errors'][0]['message'];
            }
        }

        return 'BackOffice request failed: ' . $e->getMessage();
    }

    /**
     * Créé à la première utilisation : un FO sans configuration du BO continue de fonctionner.
     *
     * @throws InvalidConfigurationException
     */
    private function getClient(): Client
    {
        if ($this->guzzleClient === null) {
            $missedOptions = array_diff(['baseUrl', 'token'], array_keys(array_filter($this->config)));
            if ($missedOptions !== []) {
                throw new InvalidConfigurationException(
                    array_map(static fn (string $option) => 'boApi.' . $option, $missedOptions)
                );
            }

            $options = [
                'base_uri' => rtrim($this->config['baseUrl'], '/') . '/api/entity/',
                'timeout' => $this->config['timeout'] ?? self::DEFAULT_TIMEOUT,
                'verify' => $this->config['verifyTls'] ?? true,
                'headers' => ['Authorization' => 'Bearer ' . $this->config['token']],
            ];
            if ($this->handler !== null) {
                $options['handler'] = HandlerStack::create($this->handler);
            }
            $this->guzzleClient = new Client($options);
        }

        return $this->guzzleClient;
    }
}
