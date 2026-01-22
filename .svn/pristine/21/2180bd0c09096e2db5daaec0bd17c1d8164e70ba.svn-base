<?php

declare(strict_types=1);

namespace SimpleSAML\Module\statistics;

/**
 * @package SimpleSAMLphp
 */
class DateHandler
{
    /** @var int */
    protected int $offset;


    /**
     * Constructor
     *
     * @param int $offset Date offset
     */
    public function __construct(int $offset)
    {
        $this->offset = $offset;
    }


    /**
     * @param int $timestamp
     * @return int
     */
    protected function getDST(int $timestamp): int
    {
        if (idate('I', $timestamp)) {
            return 3600;
        }
        return 0;
    }


    /**
     * @param int $epoch
     * @param int $slotsize
     * @return int
     */
    public function toSlot(int $epoch, int $slotsize): int
    {
        $dst = $this->getDST($epoch);
        return intval(floor(($epoch + $this->offset + $dst) / $slotsize));
    }


    /**
     * @param int $slot
     * @param int $slotsize
     * @return int
     */
    public function fromSlot(int $slot, int $slotsize): int
    {
        $temp = $slot * $slotsize - $this->offset;
        $dst = $this->getDST($temp);
        return $slot * $slotsize - $this->offset - $dst;
    }


    /**
     * @param int $epoch
     * @param string $dateformat
     * @return string
     */
    public function prettyDateEpoch(int $epoch, string $dateformat): string
    {
        return date($dateformat, $epoch);
    }


    /**
     * @param int $slot
     * @param int $slotsize
     * @param string $dateformat
     * @return string
     */
    public function prettyDateSlot(int $slot, int $slotsize, string $dateformat): string
    {
        return $this->prettyDateEpoch($this->fromSlot($slot, $slotsize), $dateformat);
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
        $text = $this->prettyDateSlot($from, $slotsize, $dateformat);
        $text .= ' to ';
        $text .= $this->prettyDateSlot($to, $slotsize, $dateformat);
        return $text;
    }
}
