<?php

/**
 * This class provides text/string helpers for StoryBB's templates.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2017 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 3.0 Alpha 1
 */

namespace StoryBB\Template\Helper;

class Text
{
	public static function _list()
	{
		return ([
			'get_text' => 'StoryBB\\Template\\Helper\\Text::get_text',
			'textTemplate' => 'StoryBB\\Template\\Helper\\Text::textTemplate',
			'concat' => 'StoryBB\\Template\\Helper\\Text::concat',
			'jsEscape' => 'StoryBB\\Template\\Helper\\Text::jsEscape',
		]);
	}

	/**
	 * Combines all the items in $key (except the last, which is Lightncandy context)
	 * and returns the time pointed to in $txt.
	 *
	 * @param array $key An array of items to implode and treat as key to $txt
	 * @return string The final string in $txt
	 */
	public static function get_text(...$key) {
		global $txt;
		if (is_array($key)) {
			array_pop($key);
		    $key = implode('', $key);
		}
		return $txt[$key];
	}

	/**
	 * Accepts a string to be sprintf'd, and an array of things
	 * to pass into that sprintf. The last item is stripped because
	 * it's the calling context from Lightncandy.
	 *
	 * @param string $template An sprintf-able string
	 * @param array $args A list of arguments to insert into the sprintf call
	 * @return string The string, sprintf'd
	 */
	public static function textTemplate($template, ...$args) {
		array_pop($args);
		$string = new \LightnCandy\SafeString(sprintf($template, ...$args));
		return (string) $string;
	}

	/**
	 * Accept an array of items and return the array as a string.
	 * Useful for combining things in helpers for other helpers.
	 * The last item is stripped from the array as it is Lightncandy's context.
	 *
	 * @param array $items A list of items to concatenate.
	 * @return string The list of items concatenated.
	 */
	public static function concat(...$items)
	{
		array_pop($items);
		return implode('', $items);
	}

	public static function jsEscape($string)
	{
		global $scripturl;

		return '\'' . strtr($string, array(
			"\r" => '',
			"\n" => '\\n',
			"\t" => '\\t',
			'\\' => '\\\\',
			'\'' => '\\\'',
			'</' => '<\' + \'/',
			'<script' => '<scri\'+\'pt',
			'<body>' => '<bo\'+\'dy>',
			'<a href' => '<a hr\'+\'ef',
			$scripturl => '\' + smf_scripturl + \'',
		)) . '\'';
	}

	public static function isSelected($current_val, $val) 
	{
		return new \LightnCandy\SafeString($current_val == $val ? 'selected="selected' : '');
	}
}

?>