<?php

declare(strict_types=1);

namespace App\Cache;

use Memcached;
use RuntimeException;

final class MemcachedCache
{
    private Memcached $client;
    private string $prefix;

    /** @param array{host:string,port:int,prefix:string} $config */
    public function __construct(array $config)
    {
        if (!extension_loaded('memcached')) {
            throw new RuntimeException('PHP extension memcached is required');
        }

        $this->prefix = $config['prefix'];
        $this->client = new Memcached('news_aggregator_pool');

        if (count($this->client->getServerList()) === 0) {
            $this->client->addServer($config['host'], $config['port']);
            $this->client->setOption(Memcached::OPT_BINARY_PROTOCOL, true);
            $this->client->setOption(Memcached::OPT_TCP_NODELAY, true);
            $this->client->setOption(Memcached::OPT_NO_BLOCK, true);
        }
    }

    public function get(string $key): mixed
    {
        $value = $this->client->get($this->prefix . $key);
        if ($this->client->getResultCode() === Memcached::RES_NOTFOUND) {
            return null;
        }

        return $value;
    }

    public function set(string $key, mixed $value, int $ttl): bool
    {
        return $this->client->set($this->prefix . $key, $value, $ttl);
    }

    public function delete(string $key): bool
    {
        return $this->client->delete($this->prefix . $key);
    }

    /** @param list<string> $keys */
    public function deleteMany(array $keys): void
    {
        foreach ($keys as $key) {
            $this->delete($key);
        }
    }

    public function flush(): bool
    {
        return $this->client->flush();
    }

    public function remember(string $key, int $ttl, callable $callback): mixed
    {
        $cached = $this->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $value = $callback();
        $this->set($key, $value, $ttl);

        return $value;
    }
}
