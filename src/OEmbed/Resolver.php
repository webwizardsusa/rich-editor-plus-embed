<?php

namespace Webwizardsusa\RichEditorPlusEmbed\OEmbed;

use Webwizardsusa\RichEditorPlusEmbed\Providers\ProviderRegistry;

/**
 * Looks URLs up through their providers: answers come from the cache when they can, and the rest are fetched together
 * in one pool and cached.
 */
class Resolver
{
    public function __construct(
        protected ProviderRegistry $providers,
        protected OEmbedClient $client,
        protected EmbedCache $cache,
        protected ?int $maxWidth = null,
    ) {}

    public function resolve(string $url): ?OEmbedResponse
    {
        return $this->resolveMany([$url])[$url] ?? null;
    }

    /**
     * @param  array<string>  $urls
     * @return array<string, ?OEmbedResponse> By URL. Null for a URL no provider takes, or whose lookup failed.
     */
    public function resolveMany(array $urls): array
    {
        $answers = [];
        $requests = [];

        foreach (array_unique($urls) as $url) {
            $answers[$url] = null;
            $provider = $this->providers->match($url);

            if ($provider !== null) {
                $requests[EmbedCache::key($provider->getName(), $url, $this->maxWidth)] = ['provider' => $provider, 'url' => $url];
            }
        }

        $cached = $this->cache->getMany(array_keys($requests));
        $missing = array_diff_key($requests, $cached);

        foreach ($this->client->fetchMany($missing, $this->maxWidth) as $key => $response) {
            $this->cache->put($key, $response);
            $cached[$key] = $response ?? false;
        }

        foreach ($requests as $key => $request) {
            $answers[$request['url']] = ($cached[$key] ?? false) ?: null;
        }

        return $answers;
    }
}
