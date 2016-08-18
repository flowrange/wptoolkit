<?php

namespace Flowrange\WPToolkit;

/**
 * Just like WP's get_permalink, but the result is stored in cache using the WP Cache API
 *
 * * Instantiate with a cache group name, and optionally a TTL
 * * The class hooks itself in the save_post action, to clear the cache after saving a post
 * * Permalinks are returned without protocol (//some/url insteand of http://some/url). This allows one to set the
 *   protocol at runtime (for example to allow for both http and https)
 * * After retrieving a link from the cache, no filter is applied. This means once a permalink is in cache, you can't
 *   dynamically filter it afterwise.
 *
 * @author Florent Geffroy <contact@flowrange.fr>
 */
class PermalinkCache
{


    /**
     * Cache group name
     * @var string
     */
    private $cacheGroup;


    /**
     * TTL
     * @var int
     */
    private $ttl;


    /**
     * Constructor
     *
     * @param string $cacheGroup Cache group (must be unique, use something like yourtheme.permalinks)
     * @param int    $ttl        TTL in seconds (default 86400, or 24h)
     */
    public function __construct($cacheGroup, $ttl = 86400)
    {
        $this->cacheGroup = $cacheGroup;
        $this->ttl        = $ttl;

        \add_action('save_post', [$this, 'clearPostCache']);
    }


    /**
     * Callback for save_post action : The cached permalink for a post is cleared
     */
    public function clearPostCache($postId)
    {
        \wp_cache_delete($postId, $this->cacheGroup);
    }


    /**
     * Returns a post permalink
     *
     * Uses WP's get_permalink function. The result is stored in cache.
     *
     * Absolute URLs are returned without protocol, allowing one to set it at runtime.
     *
     * @param \WP_Post|int $post      The post or its ID
     * @param bool         $leaveName Whether to keep post name (default false)
     *
     * @return string The permalink
     *
     * @throws \Exception If the cache group wasn't set
     */
    public function getPermalink($post, $leaveName = false)
    {
        $postId = null;
        if ($post instanceof \WP_Post) {
            $postId = (int)$post->ID;
        } elseif(\is_string($post) || \is_int($post)) {
            $postId = (int)$post;
        } else {
            return '';
        }

        $found              = false;
        $permalinkFromCache = \wp_cache_get($postId, $this->cacheGroup, false, $found);

        if ($permalinkFromCache || $found) {
            return $permalinkFromCache;
        }

        $permalinkWithScheme = \get_permalink($post instanceof \WP_Post ? $post : $postId, $leaveName);
        $permalink           = self::removeSchemeFromURLIfAny($permalinkWithScheme);

        \wp_cache_set($postId, $permalink, $this->cacheGroup, $this->ttl);

        return $permalink;
    }


    /**
     * Removes the scheme from an URL if any (eg. http://some-url/ -> //some-url/)
     *
     * If no scheme, leaves the URL alone
     *
     * @param string $url URL to remove scheme from
     *
     * @return string
     */
    private static function removeSchemeFromURLIfAny($url)
    {
        $matches = [];
        if (\strpos($url, '://') !== false && \preg_match('#^(.*):(//.+)$#', $url, $matches)) {

            return $matches[2];
        }
        return $url;
    }

}
