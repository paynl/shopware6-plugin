<?php

declare(strict_types=1);

namespace PaynlPayment\Shopware6\Helper;

use DateTime;
use DateTimeInterface;
use PaynlPayment\Shopware6\Components\Api;
use Psr\Cache\CacheItemPoolInterface;

class PublicKeysHelper
{
    const CACHE_KEY = 'paynl_public_encryption_keys';
    const CACHE_TTL = 15768000;

    /** @var Api */
    private $paynlApi;

    /** @var CacheItemPoolInterface */
    private $cache;

    public function __construct(Api $api, CacheItemPoolInterface $cache)
    {
        $this->paynlApi = $api;
        $this->cache = $cache;
    }

    public function getKeys(string $salesChannelId, bool $refresh = false): array
    {
        $keysCacheItem = $this->cache->getItem(self::CACHE_KEY);

        if ($refresh || !$keysCacheItem->isHit() || !$keysCacheItem->get()) {
            $keys = $this->paynlApi->getPublicKeys($salesChannelId);
            $expiresAt = $this->getExpiresAtPublicKey($keys);
            $keysCacheItem->set(json_encode($keys));
            if ($expiresAt !== null) {
                $keysCacheItem->expiresAt($expiresAt);
            }

            $this->cache->save($keysCacheItem);
        } else {
            $keys = json_decode($keysCacheItem->get(), true);
        }

        return $keys;
    }

    private function getExpiresAtPublicKey(array $keys): ?DateTimeInterface
    {
        if (empty($keys)) {
            return null;
        }

        usort($keys, function ($firstDate, $secondDate) {
            return strtotime($firstDate['expires_at']) - strtotime($secondDate['expires_at']);
        });

        $expiresAt = reset($keys)['expires_at'];

        $expiresAtDateTime = new DateTime();
        $expiresAtDateTime->setTimestamp(strtotime($expiresAt));

        return $expiresAtDateTime;
    }
}
