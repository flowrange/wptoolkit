<?php

namespace Flowrange\WPToolkit;

/**
 * Like WP's wp_get_attachment_image, but the result is stored in cache using the WP Cache API
 *
 * * Instantiate with a cache group name, and optionally a TTL
 * * The class hooks itself into image_downsize, to dynamically regenerate sizes
 * * Fixes a bug with utf-8 chars in file names
 *
 * @author Florent Geffroy <contact@flowrange.fr>
 */
class CachedThumbTag
{


    /**
     * Cache group name
     * @var string
     */
    private static $cacheGroup;


    /**
     * TTL
     * @var int
     */
    private static $ttl;


    /**
     * Constructor
     *
     * @param string $cacheGroup Cache group (must be unique, use something like yourtheme.thumbs)
     * @param int    $ttl        TTL in seconds (default 86400, or 24h)
     */
    public function __construct($cacheGroup, $ttl = 86400)
    {
        self::$cacheGroup = $cacheGroup;
        self::$ttl        = $ttl;

        add_filter('image_downsize', [$this, 'imageDownsize'], 10, 3);
        add_action('save_post',      [$this, 'clearPostCache']);
    }


    /**
     * Filter for image_downsize : resize image
     *
     * @global type $_wp_additional_image_sizes
     * @param type $out
     * @param type $id
     * @param type $size
     *
     * @return boolean|array
     */
    public function imageDownsize($out, $id, $size)
    {
        if (!is_string($size)) {
            return;
        }
        $imagedata = wp_get_attachment_metadata($id);
        if (is_array($imagedata) && isset($imagedata['sizes'][$size])) {
            return false;
        }

        global $_wp_additional_image_sizes;
        if (!isset($_wp_additional_image_sizes[$size])) {
            return false;
        }

        $file = get_attached_file($id);

        if (strncmp(PHP_OS, 'WIN', 3) === 0) {
            $file =  iconv('utf-8', 'cp1252', $file);
        }

        $resized = image_make_intermediate_size(
            $file,
            $_wp_additional_image_sizes[$size]['width'],
            $_wp_additional_image_sizes[$size]['height'],
            $_wp_additional_image_sizes[$size]['crop']);

        if (!$resized) {
            return false;
        }

        if (strncmp(PHP_OS, 'WIN', 3) === 0) {
            $resized['file'] = iconv('cp1252', 'utf-8', $resized['file']);
        }

        $imagedata['sizes'][$size] = $resized;
        wp_update_attachment_metadata($id, $imagedata);

        $att_url = wp_get_attachment_url($id);
        return array(dirname($att_url) . '/' . $resized['file'], $resized['width'], $resized['height'], true);
    }


    /**
     * Callback for save_post action : The cached thumb for a post is cleared
     */
    public function clearPostCache($postId)
    {
        wp_cache_delete($postId, self::$cacheGroup);
    }


    /**
     * Return a post's thumbnail
     *
     * @param \WP_Post|int $post     Post ou post ID
     * @param string       $size     Size
     * @param string       $fallback Fallback if not thumbnail
     *
     * @return string
     */
    public static function getTag($post, $size, $fallback = '', $attrs = [])
    {
        if (self::$cacheGroup === null) {
            throw new \Exception('Cannot retrieve thumbnail from cache : no cache group was set');
        }

        global $_wp_additional_image_sizes;

        $postId = null;
        if ($post instanceof \WP_Post) {
            $postId = $post->ID;
        } elseif(is_string($post) || is_int($post)) {
            $postId = (int)$post;
            $post   = \get_post($postId);
        } else {
            return;
        }

        $sizeId = null;
        if (is_string($size)) {
            $sizeId = $size;
        } elseif (is_array($size)) {
            $sizeId = implode('x', $size);
        } else {
            return;
        }

        $found         = null;
        $postThumbTags = \wp_cache_get($postId, self::$cacheGroup, false, $found);

        if ($found && isset($postThumbTags[$sizeId])) {
            return $postThumbTags[$sizeId];
        }

        $isAlreadyAnAttachment = ($post instanceof \WP_Post) && ($post->post_type === 'attachment');

        if ($isAlreadyAnAttachment || \has_post_thumbnail($post)) {

            if ($isAlreadyAnAttachment) {
                $postThumbId = $post->ID;
            } else {
                $postThumbId = \get_post_thumbnail_id($post);
            }

            $postThumb = \wp_get_attachment_image($postThumbId, $size, false, $attrs);

        } elseif ($fallback) {

            $thumbId = \attachment_url_to_postid($fallback);
            if ($thumbId) {
                $postThumb = \wp_get_attachment_image($thumbId, $size, false, $attrs);
            } else {
                return '';
            }

        } else {
            return '';
        }


        $postThumbTags[$sizeId] = $postThumb;

        \wp_cache_set($postId, $postThumbTags, self::$cacheGroup, self::$ttl);

        return $postThumb;
    }

}
