<?php

/**
 * Copyright 2011-2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2011-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Imap_Client
 */

/**
 * An object that provides a way to identify a list of IMAP indices.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2011-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Imap_Client
 *
 * @property-read boolean $all  Does this represent an ALL message set?
 * @property-read array $ids  The list of IDs.
 * @property-read boolean $largest  Does this represent the largest ID in use?
 * @property-read string $max  The largest ID (@since 2.20.0).
 * @property-read string $min  The smallest ID (@since 2.20.0).
 * @property-read string $range_string  Generates a range string consisting of
 *                                      all messages between begin and end of
 *                                      ID list.
 * @property-read boolean $search_res  Does this represent a search result?
 * @property-read boolean $sequence  Are these sequence IDs? If false, these
 *                                   are UIDs.
 * @property-read boolean $special  True if this is a "special" ID
 *                                  representation.
 * @property-read string $tostring  Return the non-sorted string
 *                                  representation.
 * @property-read string $tostring_sort  Return the sorted string
 *                                       representation.
 */
class Horde_Imap_Client_Ids implements Countable, Iterator, Serializable
{
    /**
     * "Special" representation constants.
     */
    public const ALL = "\01";
    public const SEARCH_RES = "\02";
    public const LARGEST = "\03";

    /**
     * Allow duplicate IDs?
     *
     * @var boolean
     */
    public $duplicates = false;

    /**
     * List of IDs.
     *
     * @var mixed
     */
    protected $_ids = [];

    /**
     * Compact, sorted ID intervals. When set, this is authoritative and
     * $_ids remains empty until the flat list is explicitly requested.
     *
     * @var array|null
     */
    protected $_intervals = null;

    /**
     * Iterator state while traversing compact intervals.
     *
     * @var array
     */
    protected $_iterState = [];

    /**
     * Are IDs message sequence numbers?
     *
     * @var boolean
     */
    protected $_sequence = false;

    /**
     * Are IDs sorted?
     *
     * @var boolean
     */
    protected $_sorted = false;

    /**
     * Constructor.
     *
     * @param mixed $ids         See self::add().
     * @param boolean $sequence  Are $ids message sequence numbers?
     */
    public function __construct($ids = null, $sequence = false)
    {
        $this->add($ids);
        $this->_sequence = $sequence;
    }

    /**
     */
    public function __get($name)
    {
        switch ($name) {
            case 'all':
                return ($this->_ids === self::ALL);

            case 'ids':
                if ($this->_intervals !== null) {
                    $this->_expandIntervals();
                }
                return is_array($this->_ids)
                    ? $this->_ids
                    : [];

            case 'largest':
                return ($this->_ids === self::LARGEST);

            case 'max':
                if ($this->_intervals !== null) {
                    if (!$this->_intervals) {
                        return false;
                    }
                    return max(array_column($this->_intervals, 1));
                }
                if (is_string($this->_ids)) {
                    return null;
                }
                $this->sort();
                return end($this->_ids);

            case 'min':
                if ($this->_intervals !== null) {
                    if (!$this->_intervals) {
                        return false;
                    }
                    return min(array_column($this->_intervals, 0));
                }
                if (is_string($this->_ids)) {
                    return null;
                }
                $this->sort();
                return reset($this->_ids);

            case 'range_string':
                if (!count($this)) {
                    return '';
                }

                $min = $this->min;
                $max = $this->max;

                return ($min == $max)
                    ? $min
                    : $min . ':' . $max;

            case 'search_res':
                return ($this->_ids === self::SEARCH_RES);

            case 'sequence':
                return (bool) $this->_sequence;

            case 'special':
                return is_string($this->_ids);

            case 'tostring':
            case 'tostring_sort':
                if ($this->all) {
                    return '1:*';
                } elseif ($this->largest) {
                    return '*';
                } elseif ($this->search_res) {
                    return '$';
                }
                if (is_string($this->_ids)) {
                    return $this->_ids;
                }
                if ($this->_intervals !== null) {
                    return $this->_intervalsToSequenceString(
                        $name == 'tostring_sort'
                    );
                }
                return strval($this->_toSequenceString($name == 'tostring_sort'));
        }
    }

    /**
     */
    public function __toString()
    {
        return $this->tostring;
    }

    /**
     * Add IDs to the current object.
     *
     * @param mixed $ids  Either self::ALL, self::SEARCH_RES, self::LARGEST,
     *                    Horde_Imap_Client_Ids object, array, or sequence
     *                    string.
     */
    public function add($ids)
    {
        if (!is_null($ids)) {
            if (is_string($ids)
                && in_array($ids, [self::ALL, self::SEARCH_RES, self::LARGEST])) {
                $this->_ids = $ids;
                $this->_intervals = null;
            } elseif (is_string($ids)
                && in_array(trim($ids), ['1:*', '*', '$'], true)) {
                $this->_ids = match (trim($ids)) {
                    '1:*' => self::ALL,
                    '*' => self::LARGEST,
                    '$' => self::SEARCH_RES,
                };
                $this->_intervals = null;
            } elseif (is_string($ids) && str_contains($ids, '*')) {
                $sequenceSet = trim($ids);
                if (!$this->_isValidWildcardSequenceString($sequenceSet)) {
                    throw new InvalidArgumentException(
                        'Invalid IMAP sequence set.'
                    );
                }
                $this->_ids = $sequenceSet;
                $this->_intervals = null;
            } elseif ($add = $this->_resolveIds($ids)) {
                $isIntervals = isset($add[0]) && is_array($add[0]);
                if ($isIntervals || ($this->_intervals !== null)) {
                    $existing = $this->_intervals
                        ?? $this->_flatToIntervals(
                            is_array($this->_ids) ? $this->_ids : []
                        );
                    $incoming = $isIntervals
                        ? $add
                        : $this->_flatToIntervals($add);
                    $this->_intervals = $this->duplicates
                        ? array_merge($existing, $incoming)
                        : $this->_mergeIntervals(
                            array_merge($existing, $incoming)
                        );
                    $this->_ids = [];
                    $this->_sorted = true;
                    return;
                }
                if (is_array($this->_ids) && !empty($this->_ids)) {
                    foreach ($add as $val) {
                        $this->_ids[] = (int) $val;
                    }
                } else {
                    $this->_ids = $add;
                }
                if (!$this->duplicates) {
                    $this->_ids = (count($this->_ids) > 25000)
                        ? array_unique($this->_ids)
                        : array_keys(array_flip($this->_ids));
                }
            }

            $this->_sorted = is_array($this->_ids) && (count($this->_ids) === 1);
        }
    }

    /**
     * Removed IDs from the current object.
     *
     * @since 2.17.0
     *
     * @param mixed $ids  Either Horde_Imap_Client_Ids object, array, or
     *                    sequence string.
     */
    public function remove($ids)
    {
        if (is_string($this->_ids)) {
            return;
        }
        if (!$this->isEmpty()
            && ($remove = $this->_resolveIds($ids))) {
            $removeIsIntervals = isset($remove[0]) && is_array($remove[0]);
            if ($this->_intervals !== null || $removeIsIntervals) {
                $source = $this->_intervals
                    ?? $this->_flatToIntervals(
                        is_array($this->_ids) ? $this->_ids : []
                    );
                $removeIntervals = $removeIsIntervals
                    ? $remove
                    : $this->_flatToIntervals($remove);
                $removeIntervals = $this->_mergeIntervals($removeIntervals);
                $this->_intervals = $this->duplicates
                    ? $this->_subtractIntervalList($source, $removeIntervals)
                    : $this->_subtractIntervals(
                        $this->_mergeIntervals($source),
                        $removeIntervals
                    );
                $this->_ids = [];
                $this->_sorted = true;
            } else {
                $this->_ids = array_diff($this->_ids, array_unique($remove));
            }
        }
    }

    /**
     * Is this object empty (i.e. does not contain IDs)?
     *
     * @return boolean  True if object is empty.
     */
    public function isEmpty()
    {
        if ($this->_intervals !== null) {
            return !$this->_intervals;
        }
        return (is_array($this->_ids) && !count($this->_ids));
    }

    /**
     * Reverses the order of the IDs.
     */
    public function reverse()
    {
        if ($this->_intervals !== null) {
            $this->_expandIntervals();
        }
        if (is_array($this->_ids)) {
            $this->_ids = array_reverse($this->_ids);
        }
    }

    /**
     * Sorts the IDs.
     */
    public function sort()
    {
        if ($this->_intervals !== null) {
            return;
        }
        if (!$this->_sorted && is_array($this->_ids)) {
            $this->_sort($this->_ids);
            $this->_sorted = true;
        }
    }

    /**
     * Sorts the IDs numerically.
     *
     * @param array $ids  The array list.
     */
    protected function _sort(&$ids)
    {
        sort($ids, SORT_NUMERIC);
    }

    /**
     * Split the sequence string at an approximate length.
     *
     * @since 2.7.0
     *
     * @param integer $length  Length to split.
     *
     * @return array  A list containing individual sequence strings.
     */
    public function split($length)
    {
        $id = new Horde_Stream_Temp();
        $id->add($this->tostring_sort, true);

        $out = [];

        do {
            $out[] = $id->substring(0, $length) . $id->getToChar(',');
        } while (!$id->eof());

        return $out;
    }

    /**
     * Resolve the $ids input to add() and remove().
     *
     * @param mixed $ids  Either Horde_Imap_Client_Ids object, array, or
     *                    sequence string.
     *
     * @return array  An array of IDs or compact [start, end] intervals.
     */
    protected function _resolveIds($ids)
    {
        if ($ids instanceof Horde_Imap_Client_Ids) {
            if ($ids->_intervals !== null) {
                return $ids->_intervals;
            }
            return $ids->ids;
        } elseif (is_array($ids)) {
            return $ids;
        } elseif (is_string($ids) || is_integer($ids)) {
            return is_numeric($ids)
                ? [$ids]
                : $this->_fromSequenceString($ids);
        }

        return [];
    }

    /**
     * Create an IMAP message sequence string from a list of indices.
     *
     * Index Format: range_start:range_end,uid,uid2,...
     *
     * @param boolean $sort  Numerically sort the IDs before creating the
     *                       range?
     *
     * @return string  The IMAP message sequence string.
     */
    protected function _toSequenceString($sort = true)
    {
        if (empty($this->_ids)) {
            return '';
        }

        $in = $this->_ids;

        if ($sort && !$this->_sorted) {
            $this->_sort($in);
        }

        $first = $last = array_shift($in);
        $i = count($in) - 1;
        $out = [];

        foreach ($in as $key => $val) {
            if (($last + 1) == $val) {
                $last = $val;
            }

            if (($i == $key) || ($last != $val)) {
                if ($last == $first) {
                    $out[] = $first;
                    if ($i == $key) {
                        $out[] = $val;
                    }
                } else {
                    $out[] = $first . ':' . $last;
                    if (($i == $key) && ($last != $val)) {
                        $out[] = $val;
                    }
                }
                $first = $last = $val;
            }
        }

        return empty($out)
            ? $first
            : implode(',', $out);
    }

    /**
     * Parse an IMAP message sequence string into a list of indices.
     *
     * @see _toSequenceString()
     *
     * @param string $str  The IMAP message sequence string.
     *
     * @return array  An array of IDs or compact [start, end] intervals.
     */
    protected function _fromSequenceString($str)
    {
        $str = trim($str);

        if (!strlen($str)) {
            return [];
        }

        $idarray = explode(',', $str);
        $hasRange = strpos($str, ':') !== false;
        $ids = [];

        foreach ($idarray as $val) {
            $range = explode(':', $val, 2);
            if (isset($range[1])) {
                if (!ctype_digit($range[0]) || !ctype_digit($range[1])) {
                    throw new InvalidArgumentException(
                        'Open-ended or invalid IMAP ID ranges cannot be expanded without mailbox context.'
                    );
                }
                $start = (int) $range[0];
                $end = (int) $range[1];
                $ids[] = ($start <= $end)
                    ? [$start, $end]
                    : [$end, $start];
            } else {
                $ids[] = (int) $val;
            }
        }

        return $hasRange
            ? array_map(
                static fn($id) => is_array($id) ? $id : [$id, $id],
                $ids
            )
            : $ids;
    }

    /**
     * Check a sequence set containing the unresolved `*` endpoint.
     */
    protected function _isValidWildcardSequenceString($str)
    {
        foreach (explode(',', $str) as $part) {
            $range = explode(':', $part, 2);
            foreach ($range as $endpoint) {
                if ($endpoint !== '*' && !ctype_digit($endpoint)) {
                    return false;
                }
            }
        }

        return $str !== '';
    }

    /**
     * Expand compact intervals only when a caller explicitly requests ids.
     */
    protected function _expandIntervals()
    {
        if ($this->_intervals === null) {
            return;
        }

        $this->_ids = [];
        foreach ($this->_intervals as [$start, $end]) {
            for ($id = $start; $id <= $end; ++$id) {
                $this->_ids[] = $id;
            }
        }
        $this->_intervals = null;
        $this->_sorted = true;
    }

    /**
     * Convert flat IDs to single-value intervals.
     *
     * @return array  List of [start, end] pairs.
     */
    protected function _flatToIntervals(array $ids)
    {
        return array_map(
            static fn($id) => [(int) $id, (int) $id],
            $ids
        );
    }

    /**
     * Merge overlapping or adjacent intervals.
     *
     * @return array  Sorted, non-overlapping [start, end] pairs.
     */
    protected function _mergeIntervals(array $intervals)
    {
        if (!$intervals) {
            return [];
        }

        usort($intervals, static fn($a, $b) => $a[0] <=> $b[0]);
        $merged = [$intervals[0]];
        foreach (array_slice($intervals, 1) as [$start, $end]) {
            $last = count($merged) - 1;
            if ($start <= $merged[$last][1] + 1) {
                $merged[$last][1] = max($merged[$last][1], $end);
            } else {
                $merged[] = [$start, $end];
            }
        }

        return $merged;
    }

    /**
     * Subtract sorted removal intervals from sorted source intervals.
     *
     * @return array  Remaining [start, end] pairs.
     */
    protected function _subtractIntervals(array $source, array $remove)
    {
        $result = [];
        foreach ($source as [$start, $end]) {
            foreach ($remove as [$removeStart, $removeEnd]) {
                if ($removeEnd < $start || $removeStart > $end) {
                    continue;
                }
                if ($removeStart > $start) {
                    $result[] = [$start, $removeStart - 1];
                }
                $start = max($start, $removeEnd + 1);
                if ($start > $end) {
                    break;
                }
            }
            if ($start <= $end) {
                $result[] = [$start, $end];
            }
        }

        return $result;
    }

    /**
     * Subtract ranges while retaining duplicate intervals.
     */
    protected function _subtractIntervalList(array $source, array $remove)
    {
        $result = [];
        foreach ($source as $interval) {
            array_push(
                $result,
                ...$this->_subtractIntervals([$interval], $remove)
            );
        }

        return $result;
    }

    /**
     * Format compact intervals as an IMAP sequence string.
     */
    protected function _intervalsToSequenceString($sort = false)
    {
        $intervals = $this->_intervals;
        if ($sort) {
            usort($intervals, static fn($a, $b) => $a[0] <=> $b[0]);
        }

        return implode(',', array_map(
            static fn($interval) => ($interval[0] === $interval[1])
                ? (string) $interval[0]
                : $interval[0] . ':' . $interval[1],
            $intervals
        ));
    }

    /* Countable methods. */

    /**
     */
    #[ReturnTypeWillChange]
    public function count()
    {
        if ($this->_intervals !== null) {
            return array_sum(array_map(
                static fn($interval) => $interval[1] - $interval[0] + 1,
                $this->_intervals
            ));
        }
        return is_array($this->_ids)
            ? count($this->_ids)
            : 0;
    }

    /* Iterator methods. */

    /**
     */
    #[ReturnTypeWillChange]
    public function current()
    {
        if ($this->_intervals !== null) {
            return isset($this->_iterState['idx'])
                && isset($this->_intervals[$this->_iterState['idx']])
                ? $this->_iterState['pos']
                : null;
        }
        return is_array($this->_ids)
            ? current($this->_ids)
            : null;
    }

    /**
     */
    #[ReturnTypeWillChange]
    public function key()
    {
        if ($this->_intervals !== null) {
            return isset($this->_iterState['idx'])
                && isset($this->_intervals[$this->_iterState['idx']])
                ? $this->_iterState['key']
                : null;
        }
        return is_array($this->_ids)
            ? key($this->_ids)
            : null;
    }

    /**
     */
    #[ReturnTypeWillChange]
    public function next()
    {
        if ($this->_intervals !== null) {
            if (!isset($this->_iterState['idx'])) {
                return;
            }
            ++$this->_iterState['key'];
            ++$this->_iterState['pos'];
            if ($this->_iterState['pos']
                > $this->_intervals[$this->_iterState['idx']][1]) {
                ++$this->_iterState['idx'];
                if (isset($this->_intervals[$this->_iterState['idx']])) {
                    $this->_iterState['pos']
                        = $this->_intervals[$this->_iterState['idx']][0];
                }
            }
            return;
        }
        if (is_array($this->_ids)) {
            next($this->_ids);
        }
    }

    /**
     */
    #[ReturnTypeWillChange]
    public function rewind()
    {
        if ($this->_intervals !== null) {
            $this->_iterState = [];
            if ($this->_intervals) {
                $this->_iterState = [
                    'idx' => 0,
                    'pos' => $this->_intervals[0][0],
                    'key' => 0,
                ];
            }
            return;
        }
        if (is_array($this->_ids)) {
            reset($this->_ids);
        }
    }

    /**
     */
    #[ReturnTypeWillChange]
    public function valid()
    {
        return !is_null($this->key());
    }

    public function serialize()
    {
        return serialize($this->__serialize());
    }

    public function unserialize($data)
    {
        $data = @unserialize($data);
        if (!is_array($data)) {
            throw new Exception('Cache version change.');
        }

        $this->__unserialize($data);
    }

    /**
     * @return array
     */
    public function __serialize()
    {
        return [
            'd' => $this->duplicates,
            's' => $this->_sequence,
            'is' => $this->_sorted,
            'ids' => $this->_ids,
            'ir' => $this->_intervals,
        ];
    }

    public function __unserialize(array $data)
    {
        $this->duplicates = !empty($data['d']);
        $this->_sequence = !empty($data['s']);
        $this->_sorted = !empty($data['is']);
        $this->_ids = $data['ids'] ?? [];
        $this->_intervals = $data['ir'] ?? null;
        $this->_iterState = [];
    }

}
