<?php

/**
 * Generic search class, all search backends should extend this class.
 *
 * @package StoryBB (storybb.org) - A roleplayer's forum software
 * @copyright 2021 StoryBB and individual contributors (see contributors.txt)
 * @license 3-clause BSD (see accompanying LICENSE file)
 *
 * @version 1.0 Alpha 1
 */

namespace StoryBB\Search;

/**
 * The generic search class has a number of functions, most backends should override most of these.
 */
abstract class AbstractSearchableOld
{
	/**
	 * @var bool Whether or not it's supported
	 */
	public $is_supported = true;

	/**
	 * @var bool Whether this backend has a template associated with it
	 */
	public $has_template = true;

	/**
	 * Returns the name of the search index.
	 */
	abstract public function getName(): string;

	/**
	 * Returns the description of the search index.
	 */
	public function getDescription(): string
	{
		return '';
	}

	/**
	 * Whether this method is valid for implementation or not
	 *
	 * @return bool Whether or not this method is valid
	 */
	public function isValid()
	{
		return false;
	}

	/**
	 * Callback function for usort used to sort the fulltext results.
	 * the order of sorting is: large words, small words, large words that
	 * are excluded from the search, small words that are excluded.
	 *
	 * @param string $a Word A
	 * @param string $b Word B
	 * @return int An integer indicating how the words should be sorted
	 */
	abstract public function searchSort($a, $b);

	/**
	 * Callback while preparing indexes for searching
	 *
	 * @param string $word A word to index
	 * @param array $wordsSearch Search words
	 * @param array $wordsExclude Words to exclude
	 * @param bool $isExcluded Whether the specfied word should be excluded
	 */
	abstract public function prepareIndexes($word, array &$wordsSearch, array &$wordsExclude, $isExcluded);

	/**
	 * Search for indexed words.
	 *
	 * @param array $words An array of words
	 * @param array $search_data An array of search data
	 * @return mixed
	 */
	abstract public function indexedWordQuery(array $words, array $search_data);

	/**
	 * Callback when a post is created
	 * @see createPost()
	 *
	 * @param array $msgOptions An array of post data
	 * @param array $topicOptions An array of topic data
	 * @param array $posterOptions An array of info about the person who made this post
	 * @return void
	 */
	abstract public function postCreated(array &$msgOptions, array &$topicOptions, array &$posterOptions);

	/**
	 * Callback when a post is modified
	 * @see modifyPost()
	 *
	 * @param array $msgOptions An array of post data
	 * @param array $topicOptions An array of topic data
	 * @param array $posterOptions An array of info about the person who made this post
	 * @return void
	 */
	abstract public function postModified(array &$msgOptions, array &$topicOptions, array &$posterOptions);

	/**
	 * Callback when a post is removed
	 *
	 * @param int $id_msg The ID of the post that was removed
	 * @return void
	 */
	abstract public function postRemoved($id_msg);

	/**
	 * Callback when a topic is removed
	 *
	 * @param array $topics The ID(s) of the removed topic(s)
	 * @return void
	 */
	abstract public function topicsRemoved(array $topics);

	/**
	 * Callback when a topic is moved
	 *
	 * @param array $topics The ID(s) of the moved topic(s)
	 * @param int $board_to The board that the topics were moved to
	 * @return void
	 */
	abstract public function topicsMoved(array $topics, $board_to);

	/**
	 * Callback for actually performing the search query
	 *
	 * @param array $query_params An array of parameters for the query
	 * @param array $searchWords The words that were searched for
	 * @param array $excludedIndexWords Indexed words that should be excluded
	 * @param array $participants
	 * @param array $searchArray
	 * @return mixed
	 */
	abstract public function searchQuery(array $query_params, array $searchWords, array $excludedIndexWords, array &$participants, array &$searchArray);
}
