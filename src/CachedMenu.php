<?php

namespace Flowrange\WPToolkit;

/**
 * Cached wp_nav_menu
 *
 * * Called exactly like wp_nav_menu
 * * Currently only supports calling menus by theme_location, not slugs
 * * Set $args['fg_no_classes'] to false to avoid WP's css classes, for extra perfs
 *
 * @author Florent Geffroy <contact@flowrange.fr>
 */

class CachedMenu
{


    /**
     * Key for storing list of menu ids
     * @var string
     */
    const KEY_MENU_IDS = 'ids';



    /**
     * Key for storing locations
     * @var string
     */
    const KEY_LOCATIONS = 'locations';


    /**
     * Key for storing menu objects
     * @var string
     */
    const KEY_MENU_OBJECTS = 'menu-';


    /**
     * Key for storing menu items
     * @var string
     */
    const KEY_MENU_ITEMS = 'items-';


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
     * Returns the menus' ids stored in cache
     *
     * If nothing found, prime the cache with existing menus
     *
     * @return int[]
     */
    public function getMenusIdsFromCache()
    {
        $found    = false;
        $menusIds = \wp_cache_get(self::KEY_MENU_IDS, $this->cacheGroup, false, $found);

        if ($found && is_array($menusIds)) {

            return $menusIds;
        }

        $menusIds = [];
        $menus    = \get_terms('nav_menu');
        foreach ($menus as $menu) {
            $menusIds[] = (int)$menu->term_id;
        }

        \wp_cache_set(self::KEY_MENU_IDS, $menusIds, $this->cacheGroup, $this->ttl);

        return $menusIds;
    }


    /**
     * Retrieve menu locations from the cache
     *
     * @return array
     */
    public function getMenuLocationsFromCache()
    {
        $found = false;
        $locations = \wp_cache_get(self::KEY_LOCATIONS, $this->cacheGroup, false, $found);

        if ($found && is_array($locations)) {

            return $locations;
        }

        $locations = \get_nav_menu_locations();

        \wp_cache_set(self::KEY_LOCATIONS, $locations, $this->cacheGroup, $this->ttl);

        return $locations;
    }


    /**
     * Retrieve a menu object from the cache
     *
     * @param array $args Args
     *
     * @return \WP_Term
     */
    public function getMenuObjectFromCache($args)
    {
        $menu      = null;
        $locations = $this->getMenuLocationsFromCache();

        if (isset($locations[$args->theme_location])) {

            $found   = false;
            $menuKey = $locations[$args->theme_location];
            $menu    = \wp_cache_get(self::KEY_MENU_OBJECTS . $menuKey, $this->cacheGroup, false, $found);

            if ($found && $menu instanceof \WP_Term) {

                return $menu;
            }

            $menu = \wp_get_nav_menu_object($menuKey);
        }

        if ($menu) {

            \wp_cache_set(self::KEY_MENU_OBJECTS . $menuKey, $menu, $this->cacheGroup, $this->ttl);
        }

        return $menu;
    }


    /**
     * Retrieve menu items from the cache
     *
     * @param int   $menuId Menu id
     * @param array $args   Args
     *
     * @return array
     */
    public function getMenuItemsFromCache($menuId, array $args = [])
    {
        $found = false;

        $items = \wp_cache_get(self::KEY_MENU_ITEMS . $menuId, $this->cacheGroup, false, $found);

        if ($found && is_array($items)) {

            return $items;
        }

        $items = \wp_get_nav_menu_items($menuId, $args);

        if ($items) {

            \wp_cache_set(self::KEY_MENU_ITEMS . $menuId, $items, $this->cacheGroup, $this->ttl);
        }

        return $items;
    }


    /**
     * Constructor
     *
     * @param string $cacheGroup Cache group (must be unique, use something like yourtheme.menus)
     * @param int    $ttl        TTL in seconds (default 86400, or 24h)
     */
    public function __construct($cacheGroup, $ttl = 86400)
    {
        $this->cacheGroup = $cacheGroup;
        $this->ttl        = $ttl;

        \add_action('wp_create_nav_menu', [$this, 'addMenu'],         100);
        \add_action('wp_update_nav_menu', [$this, 'deleteMenuCache'], 100);
        \add_action('wp_delete_nav_menu', [$this, 'deleteMenu'],      100);

        \add_action('save_post', [$this, 'deleteAllMenusCaches']);
    }


    /**
     * Adds a menu
     *
     * Its id is added to the cached list of ids
     *
     * @param int $menuId Menu id
     */
    public function addMenu($menuId)
    {
        $menuId   = (int)$menuId;
        $menusIds = $this->getMenusIdsFromCache();

        if (!in_array($menuId, $menusIds)) {
            $menusIds[] = $menuId;
        }

        \wp_cache_set(self::KEY_MENU_IDS, $menusIds, $this->cacheGroup, $this->ttl);
    }


    /**
     * Updates a menu
     *
     * Its cache is cleared
     *
     * @param int $menuId Menu id
     */
    public function deleteMenuCache($menuId)
    {
        \wp_cache_delete(self::KEY_MENU_OBJECTS . $menuId, $this->cacheGroup);
        \wp_cache_delete(self::KEY_MENU_ITEMS   . $menuId, $this->cacheGroup);

        \wp_cache_delete(self::KEY_LOCATIONS, $this->cacheGroup);
    }


    /**
     * Deletes a menu
     *
     * The menu is removed from the cached list of ids, and its cache deleted
     *
     * @param int $menuId Menu id
     */
    public function deleteMenu($menuId)
    {
        $menuId   = (int)$menuId;
        $menusIds = $this->getMenusIdsFromCache();

        if (($key = array_search($menuId, $menusIds)) !== false) {
            unset($menusIds[$key]);
        }

        $this->deleteMenuCache($menuId);
        \wp_cache_set(self::KEY_MENU_IDS, $menusIds, $this->cacheGroup, $this->ttl);
    }


    /**
     * Delete all menus' caches
     */
    public function deleteAllMenusCaches()
    {
        $menusIds = $this->getMenusIdsFromCache();

        foreach ($menusIds as $menuId) {

            $this->deleteMenuCache($menuId);
        }
    }


    /**
     * Returns or displays a menu
     *
     * This is copy/pasted from wp-includes\nav-menu-template\wp_nav_menu(), with some changes to retrieve menu
     * objects from the cache
     *
     * Additionnal args :
     *  * fg_no_classes : set to true if you don't want wp's items classes, e.g. you have your own MenuWalker and they
     *                    override all generated classes, for extra perfs squeeze
     *
     * @param array $args Args (see wp_nav_menu)
     *
     * @return string|null Output (if $args['echo'] is true)
     */
    public function getMenu($args = [])
    {
        static $menu_id_slugs = array();

        $defaults = [
            'menu'            => '',
            'container'       => 'div',
            'container_class' => '',
            'container_id'    => '',
            'menu_class'      => 'menu',
            'menu_id'         => '',
            'echo'            => true,
            'fallback_cb'     => 'wp_page_menu',
            'before'          => '',
            'after'           => '',
            'link_before'     => '',
            'link_after'      => '',
            'items_wrap'      => '<ul id="%1$s" class="%2$s">%3$s</ul>',
            'depth'           => 0,
            'walker'          => '',
            'theme_location'  => '',
            'fg_no_classes'   => false
        ];

        $args = wp_parse_args($args, $defaults);
        $args = apply_filters('wp_nav_menu_args', $args);
        $args = (object)$args;

        $menu = $this->getMenuObjectFromCache($args);

        // If the menu exists, get its items.
        if (   $menu
            && !is_wp_error($menu)
            && !isset($menu_items))
        {
            $menu_items = $this->getMenuItemsFromCache($menu->term_id, ['update_post_term_cache' => false]);
        }


        if ( ( !$menu || is_wp_error($menu) || ( isset($menu_items) && empty($menu_items) && !$args->theme_location ) )
            && isset( $args->fallback_cb ) && $args->fallback_cb && is_callable( $args->fallback_cb ) )
                return call_user_func( $args->fallback_cb, (array) $args );

        if ( ! $menu || is_wp_error( $menu ) )
            return false;

        $nav_menu = $items = '';

        $show_container = false;
        if ( $args->container ) {

            $allowed_tags = apply_filters( 'wp_nav_menu_container_allowedtags', array( 'div', 'nav' ) );

            if ( is_string( $args->container ) && in_array( $args->container, $allowed_tags ) ) {

                $show_container = true;
                $class = $args->container_class ? ' class="' . esc_attr( $args->container_class ) . '"' : ' class="menu-'. $menu->slug .'-container"';
                $id = $args->container_id ? ' id="' . esc_attr( $args->container_id ) . '"' : '';
                $nav_menu .= '<'. $args->container . $id . $class . '>';
            }
        }

        if (!$args->fg_no_classes) {
            _wp_menu_item_classes_by_context( $menu_items );
        }

        $sorted_menu_items = $menu_items_with_children = array();
        foreach ( (array) $menu_items as $menu_item ) {
            $sorted_menu_items[ $menu_item->menu_order ] = $menu_item;
            if ( $menu_item->menu_item_parent )
                $menu_items_with_children[ $menu_item->menu_item_parent ] = true;
        }

        if (!$args->fg_no_classes) {

            if ( $menu_items_with_children ) {
                foreach ( $sorted_menu_items as &$menu_item ) {
                    if ( isset( $menu_items_with_children[ $menu_item->ID ] ) )
                        $menu_item->classes[] = 'menu-item-has-children';
                }
            }
        }

        unset( $menu_items, $menu_item );

        $sorted_menu_items = apply_filters( 'wp_nav_menu_objects', $sorted_menu_items, $args );

        $items .= walk_nav_menu_tree( $sorted_menu_items, $args->depth, $args );
        unset($sorted_menu_items);

        // Attributes
        if ( ! empty( $args->menu_id ) ) {
            $wrap_id = $args->menu_id;
        } else {
            $wrap_id = 'menu-' . $menu->slug;
            while ( in_array( $wrap_id, $menu_id_slugs ) ) {
                if ( preg_match( '#-(\d+)$#', $wrap_id, $matches ) )
                    $wrap_id = preg_replace('#-(\d+)$#', '-' . ++$matches[1], $wrap_id );
                else
                    $wrap_id = $wrap_id . '-1';
            }
        }
        $menu_id_slugs[] = $wrap_id;

        $wrap_class = $args->menu_class ? $args->menu_class : '';

        $items = apply_filters( 'wp_nav_menu_items', $items, $args );
        $items = apply_filters( "wp_nav_menu_{$menu->slug}_items", $items, $args );

        if ( empty( $items ) )
            return false;

        $nav_menu .= sprintf( $args->items_wrap, esc_attr( $wrap_id ), esc_attr( $wrap_class ), $items );
        unset( $items );

        if ($show_container) {

            $nav_menu .= '</' . $args->container . '>';
        }

        $nav_menu = apply_filters( 'wp_nav_menu', $nav_menu, $args );

        if ($args->echo) {

            echo $nav_menu;

        } else {

            return $nav_menu;
        }
    }

}
