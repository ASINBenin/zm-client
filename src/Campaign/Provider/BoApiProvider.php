<?php declare(strict_types=1);

namespace Monarc\FrontOffice\Campaign\Provider;

use DateTimeImmutable;
use DateTimeInterface;
use GuzzleHttp\Client;
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
        $query = $since === null ? [] : ['since' => $since->format(DateTimeInterface::ATOM)];

        try {
            $response = $this->getClient()->get('campaigns', ['query' => $query]);
            $data = json_decode($response->getBody()->getContents(), true, 512, JSON_THROW_ON_ERROR);
        } catch (GuzzleException | JsonException $e) {
            throw new BoApiException('BackOffice request failed: ' . $e->getMessage(), (int)$e->getCode(), $e);
        }

        $serverTime = DateTimeImmutable::createFromFormat(DateTimeInterface::ATOM, (string)($data['serverTime'] ?? ''));
        if (!is_array($data['campaigns'] ?? null) || $serverTime === false) {
            throw new BoApiException('Unexpected BackOffice response format.');
        }

        return ['campaigns' => $data['campaigns'], 'serverTime' => $serverTime];
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
