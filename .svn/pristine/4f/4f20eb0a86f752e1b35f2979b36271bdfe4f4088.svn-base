<?php

declare(strict_types=1);

namespace SimpleSAML\Module\statistics;

use SimpleSAML\Assert\Assert;

/**
 * @package SimpleSAMLphp
 */
class DateHandlerMonth extends DateHandler
{
    /**
     * Constructor
     *
     * @param integer $offset Date offset
     */
    public function __construct(int $offset)
    {
        $this->offset = $offset;
    }


    /**
     * @param int $epoch
     * @param int $slotsize
     * @return int
     */
    public function toSlot(int $epoch, int $slotsize): int
    {
        $dsttime = $this->getDST($epoch) + $epoch;
        $parsed = getdate($dsttime);
        $slot = (($parsed['year'] - 2000) * 12) + $parsed['mon'] - 1;
        return $slot;
    }


    /**
     * @param int $slot
     * @param int| $slotsize
     * @return int
     */
    public function fromSlot(int $slot, ?int $slotsize): int
    {
        Assert::null($slotsize); // Only the upstream DateHandler uses this

        $month = ($slot % 12);
        $year = 2000 + intval(floor($slot / 12));
        return mktime(0, 0, 0, $month + 1, 1, $year);
    }


    /**
     * @param int $from
     * @param int $to
     * @param int $slotsize
     * @param string $dateformat
     * @return string
     */
    public function prettyHeader(int $from, int $to, int $slotsize, string $dateformat): string
    {
        $month = ($from % 12) + 1;
        $year = 2000 + intval(floor($from / 12));
        return $year . '-' . $month;
    }
}
