<?php
namespace ImageBanks;

class ProviderSearchResults implements \ArrayAccess, \Iterator, \Countable
    {
    private $position = 0;
    private $results = array();
    private $error = "";
    private $warning = "";

    public $total = 0;

    /**
    * Assign a value to the specified offset
    * 
    * @param  integer                     $offset  The offset to assign the value to
    * @param  \ImageBanks\ProviderResult  $value   The value to set
    * 
    * @return  void
    */
    public function offsetSet($offset, $value) : void
        {
        if(!($value instanceof \ImageBanks\ProviderResult))
            {
            return;
            }

        if(is_null($offset) || !is_int($offset))
            {
            $this->results[] = $value;

            return;
            }

        $this->results[$offset] = $value;
        }

    /**
    * Offset to retrieve.
    * This method is executed when checking if offset is empty().
    * 
    * @param mixed $offset  The offset to retrieve
    * 
    * @return \ImageBanks\ProviderResult
    */
    public function offsetGet($offset) : mixed
        {
        return isset($this->results[$offset]) ? $this->results[$offset] : null;
        }

    /**
    * Whether or not an offset exists.
    * This method is executed when using isset() or empty().
    * 
    * @param  mixed  $offset  An offset to check for
    * 
    * @return boolean
    */
    public function offsetExists($offset) : bool
        {
        return isset($this->results[$offset]);
        }

    /**
    * Unset an offset.
    * This method will not be called when type-casting to (unset).
    * 
    * @param  mixed  $offset  The offset to unset
    * 
    * @return void
    */
    public function offsetUnset($offset) : void
        {
        unset($this->results[$offset]);
        }

    /**
    * Return the current element
    * 
    * @return \ImageBanks\ProviderResult
    */
    public function current() : object
        {
        return $this->results[$this->position];
        }

    /**
    * Return the key of the current element
    * 
    * @return integer
    */
    public function key() : int
        {
        return $this->position;
        }

    /**
    * Move forward to next element
    * 
    * @return void
    */ 
    public function next() : void
        {
        ++$this->position;
        }

    /**
    * Rewind the Iterator to the first element
    * 
    * @return void
    */ 
    public function rewind() : void
        {
        $this->position = 0;
        }

    /**
    * Checks if current position is valid
    * 
    * @return boolean
    */ 
    public function valid() : bool
        {
        return isset($this->results[$this->position]);
        }

    /**
    * Count elements of in ProviderSearchResults object
    * 
    * @return integer Returns the number of ProviderResult elements
    */
    public function count() : int
        {
        return count($this->results);
        }

    /**
    * Set error message
    */
    public function setError(string $message): void
        {
        $this->error = trim($message);
        }

    /**
    * Get error message
    * 
    * @return string
    */
    public function getError() : string
        {
        return $this->error;
        }

    /**
    * Set warning message
    */
    public function setWarning(string $message): void
        {
        $this->warning = trim($message);
        }

    /**
    * Get warning message
    * 
    * @return string
    */
    public function getWarning() : string
        {
        return $this->warning;
        }
    }