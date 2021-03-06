<?php
/**
 * Class to cache DB and web lookup results
 *
 * @author      Lee Garner <lee@leegarner.com>
 * @copyright   Copyright (c) 2018 Lee Garner <lee@leegarner.com>
 * @package     locator
 * @version     1.2.0
 * @since       1.2.0
 * @license     http://opensource.org/licenses/gpl-2.0.php
 *              GNU Public License v2 or later
 * @filesource
 */
namespace Locator;

/**
 * Class for Locator Cache.
 * If glFusion is version 2.0.0 or higher then the phpFastCache is used.
 * Otherwise a database cache table is used.
 * @package locator
 */
class Cache
{
    /** Default tag added to all cache entries */
    const TAG = 'locator';
    /** Minimum glFusion version that supports caching */
    const MIN_GVERSION = '2.0.0';

    /**
     * Update the cache.
     * Adds an array of tags including the plugin name
     *
     * @param   string  $key    Item key
     * @param   mixed   $data   Data, typically an array
     * @param   mixed   $tag    Tag, or array of tags.
     * @param   integer $cache_mins Cache minutes
     * @return  boolean     True on success, False on error
     */
    public static function set($key, $data, $tag='', $cache_mins=1440)
    {
        global $_TABLES;

        $key = self::makeKey($key);
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            $data = DB_escapeString(serialize($data));
            $sql = "INSERT INTO {$_TABLES['locator_cache']} VALUES ('$key', '$data')
                ON DUPLICATE KEY UPDATE data = '$data'";
            DB_query($sql, 1);
        } else {
            $ttl = (int)$cache_mins * 60;   // convert to seconds
            // Always make sure the base tag is included
            $tags = array(self::TAG);
            if (!empty($tag)) {
                if (!is_array($tag)) $tag = array($tag);
                $tags = array_merge($tags, $tag);
            }
            return \glFusion\Cache\Cache::getInstance()->set($key, $data, $tags, $ttl);
        }
    }


    /**
     * Delete a single item from the cache by key.
     *
     * @param   string  $key    Base key, e.g. item ID
     * @return  boolean     True on success, False on error
     */
    public static function delete($key)
    {
        global $_TABLES;

        $key = self::makeKey($key);
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            DB_delete($_TABLES['locator_cache'], 'cache_id', $key);
            return DB_error() ? false : true;
        } else {
            return \glFusion\Cache\Cache::getInstance()->delete($key);
        }
    }


    /**
     * Completely clear the cache. Called after upgrade.
     *
     * @param   array   $tag    Optional array of tags, base tag used if undefined
     * @return  boolean     True on success, False on error
     */
    public static function clear($tag = array())
    {
        global $_TABLES;

        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            DB_query("TRUNCATE {$_TABLES['locator_cache']}");
            return DB_error() ? false : true;
        } else {
            $tags = array(self::TAG);
            if (!empty($tag)) {
                if (!is_array($tag)) $tag = array($tag);
                $tags = array_merge($tags, $tag);
            }
            return \glFusion\Cache\Cache::getInstance()->deleteItemsByTagsAll($tags);
        }
    }


    /**
     * Create a unique cache key.
     * Intended for internal use, but public in case it is needed.
     *
     * @param   string  $key    Base key, e.g. Item ID
     * @param   boolean $incl_sechash   True to include the security hash
     * @return  string          Encoded key string to use as a cache ID
     */
    public static function makeKey($key, $incl_sechash = false)
    {
        if ($incl_sechash) {
            // Call the parent class function to use the security hash
            $key = \glFusion\Cache\Cache::getInstance()->createKey(self::TAG . '_' . $key);
        } else {
            // Just generate a simple string key
            $key = self::TAG . '_' . $key;
        }
        return $key;
    }


    /**
     * Get an item from cache by key.
     *
     * @param   string  $key    Key to retrieve
     * @return  mixed       Value of key, or NULL if not found
     */
    public static function get($key)
    {
        global $_TABLES;

        $key = self::makeKey($key);
        if (version_compare(GVERSION, self::MIN_GVERSION, '<')) {
            $data = DB_getItem($_TABLES['locator_cache'], 'data', "cache_id = '$key'");
            $data = @unserialize($data);
            return $data ? $data : NULL;
        } else {
            if (\glFusion\Cache\Cache::getInstance()->has($key)) {
                return \glFusion\Cache\Cache::getInstance()->get($key);
            } else {
                return NULL;
            }
        }
    }

}   // class Locator\Cache

?>
