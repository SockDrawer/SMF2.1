<?php

/**
 * This allows using Xcache as a short term data cache.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2018 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Cache;

/**
 * Our Cache API class
 * @package cacheAPI
 */
class Xcache extends API
{
	/**
	 * {@inheritDoc}
	 */
	public function __construct()
	{
		global $modSettings;

		parent::__construct();

		// Xcache requuires a admin username and password in order to issue a clear.
		if (!empty($modSettings['xcache_adminuser']) && !empty($modSettings['xcache_adminpass']))
		{
			ini_set('xcache.admin.user', $modSettings['xcache_adminuser']);
			ini_set('xcache.admin.pass', md5($modSettings['xcache_adminpass']));
		}
	}

	/**
	 * {@inheritDoc}
	 */
	public function getName()
	{
		return 'Xcache';
	}

	/**
	 * {@inheritDoc}
	 */
	public function isSupported($test = false)
	{
		$supported = function_exists('xcache_get') && function_exists('xcache_set') && ini_get('xcache.var_size') > 0;

		if ($test)
			return $supported;
		return parent::isSupported() && $supported;
	}

	/**
	 * {@inheritDoc}
	 */
	public function getData($key, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		return xcache_get($key);
	}

	/**
	 * {@inheritDoc}
	 */
	public function putData($key, $value, $ttl = null)
	{
		$key = $this->prefix . strtr($key, ':/', '-_');

		if ($value === null)
			return xcache_unset($key);
		else
			return xcache_set($key, $value, $ttl);
	}

	/**
	 * {@inheritDoc}
	 */
	public function cleanCache($type = '')
	{
		global $modSettings;

		// Xcache requuires a admin username and password in order to issue a clear. Ideally this would log an error, but it seems like something that could fill up the error log quickly.
		if (empty($modSettings['xcache_adminuser']) || empty($modSettings['xcache_adminpass']))
		{
			// We are going to at least invalidate it.
			$this->invalidateCache();
			return false;
		}

		// if passed a type, clear that type out
		if ($type === '' || $type === 'user')
			xcache_clear_cache(XC_TYPE_VAR, 0);
		if ($type === '' || $type === 'data')
			xcache_clear_cache(XC_TYPE_PHP, 0);

		$this->invalidateCache();
		return true;
	}

	/**
	 * {@inheritDoc}
	 */
	public function cacheSettings(array &$config_vars)
	{
		global $context, $txt;

		$config_vars[] = $txt['cache_xcache_settings'];
		$config_vars[] = array('xcache_adminuser', $txt['cache_xcache_adminuser'], 'db', 'text', 0, 'xcache_adminuser');

		// While we could md5 this when saving, this could be tricky to be sure it doesn't get corrupted on additional saves.
		$config_vars[] = array('xcache_adminpass', $txt['cache_xcache_adminpass'], 'db', 'text', 0);

		if (!isset($context['settings_post_javascript']))
			$context['settings_post_javascript'] = '';

		$context['settings_post_javascript'] .= '
			$("#cache_accelerator").change(function (e) {
				var cache_type = e.currentTarget.value;
				$("#xcache_adminuser").prop("disabled", cache_type != "xcache");
				$("#xcache_adminpass").prop("disabled", cache_type != "xcache");
			});';
	}
}

?>