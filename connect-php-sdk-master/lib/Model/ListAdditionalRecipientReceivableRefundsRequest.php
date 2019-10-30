<?php
/**
 * NOTE: This class is auto generated by the swagger code generator program.
 * https://github.com/swagger-api/swagger-codegen
 * Do not edit the class manually.
 */

namespace SquareConnect\Model;

use \ArrayAccess;
/**
 * @deprecated
 * ListAdditionalRecipientReceivableRefundsRequest Class Doc Comment
 *
 * @category Class
 * @package  SquareConnect
 * @author   Square Inc.
 * @license  http://www.apache.org/licenses/LICENSE-2.0 Apache License v2
 * @link     https://squareup.com/developers
 */
class ListAdditionalRecipientReceivableRefundsRequest implements ArrayAccess
{
    /**
      * Array of property to type mappings. Used for (de)serialization 
      * @var string[]
      */
    static $swaggerTypes = array(
        'begin_time' => 'string',
        'end_time' => 'string',
        'sort_order' => 'string',
        'cursor' => 'string'
    );
  
    /** 
      * Array of attributes where the key is the local name, and the value is the original name
      * @var string[] 
      */
    static $attributeMap = array(
        'begin_time' => 'begin_time',
        'end_time' => 'end_time',
        'sort_order' => 'sort_order',
        'cursor' => 'cursor'
    );
  
    /**
      * Array of attributes to setter functions (for deserialization of responses)
      * @var string[]
      */
    static $setters = array(
        'begin_time' => 'setBeginTime',
        'end_time' => 'setEndTime',
        'sort_order' => 'setSortOrder',
        'cursor' => 'setCursor'
    );
  
    /**
      * Array of attributes to getter functions (for serialization of requests)
      * @var string[]
      */
    static $getters = array(
        'begin_time' => 'getBeginTime',
        'end_time' => 'getEndTime',
        'sort_order' => 'getSortOrder',
        'cursor' => 'getCursor'
    );
  
    /**
      * $begin_time The beginning of the requested reporting period, in RFC 3339 format.  See [Date ranges](#dateranges) for details on date inclusivity/exclusivity.  Default value: The current time minus one year.
      * @var string
      */
    protected $begin_time;
    /**
      * $end_time The end of the requested reporting period, in RFC 3339 format.  See [Date ranges](#dateranges) for details on date inclusivity/exclusivity.  Default value: The current time.
      * @var string
      */
    protected $end_time;
    /**
      * $sort_order The order in which results are listed in the response (`ASC` for oldest first, `DESC` for newest first).  Default value: `DESC` See [SortOrder](#type-sortorder) for possible values
      * @var string
      */
    protected $sort_order;
    /**
      * $cursor A pagination cursor returned by a previous call to this endpoint. Provide this to retrieve the next set of results for your original query.  See [Pagination](https://developer.squareup.com/docs/basics/api101/pagination) for more information.
      * @var string
      */
    protected $cursor;

    /**
     * Constructor
     * @param mixed[] $data Associated array of property value initializing the model
     */
    public function __construct(array $data = null)
    {
        if ($data != null) {
            if (isset($data["begin_time"])) {
              $this->begin_time = $data["begin_time"];
            } else {
              $this->begin_time = null;
            }
            if (isset($data["end_time"])) {
              $this->end_time = $data["end_time"];
            } else {
              $this->end_time = null;
            }
            if (isset($data["sort_order"])) {
              $this->sort_order = $data["sort_order"];
            } else {
              $this->sort_order = null;
            }
            if (isset($data["cursor"])) {
              $this->cursor = $data["cursor"];
            } else {
              $this->cursor = null;
            }
        }
    }
    /**
     * Gets begin_time
     * @return string
     */
    public function getBeginTime()
    {
        return $this->begin_time;
    }
  
    /**
     * Sets begin_time
     * @param string $begin_time The beginning of the requested reporting period, in RFC 3339 format.  See [Date ranges](#dateranges) for details on date inclusivity/exclusivity.  Default value: The current time minus one year.
     * @return $this
     */
    public function setBeginTime($begin_time)
    {
        $this->begin_time = $begin_time;
        return $this;
    }
    /**
     * Gets end_time
     * @return string
     */
    public function getEndTime()
    {
        return $this->end_time;
    }
  
    /**
     * Sets end_time
     * @param string $end_time The end of the requested reporting period, in RFC 3339 format.  See [Date ranges](#dateranges) for details on date inclusivity/exclusivity.  Default value: The current time.
     * @return $this
     */
    public function setEndTime($end_time)
    {
        $this->end_time = $end_time;
        return $this;
    }
    /**
     * Gets sort_order
     * @return string
     */
    public function getSortOrder()
    {
        return $this->sort_order;
    }
  
    /**
     * Sets sort_order
     * @param string $sort_order The order in which results are listed in the response (`ASC` for oldest first, `DESC` for newest first).  Default value: `DESC` See [SortOrder](#type-sortorder) for possible values
     * @return $this
     */
    public function setSortOrder($sort_order)
    {
        $this->sort_order = $sort_order;
        return $this;
    }
    /**
     * Gets cursor
     * @return string
     */
    public function getCursor()
    {
        return $this->cursor;
    }
  
    /**
     * Sets cursor
     * @param string $cursor A pagination cursor returned by a previous call to this endpoint. Provide this to retrieve the next set of results for your original query.  See [Pagination](https://developer.squareup.com/docs/basics/api101/pagination) for more information.
     * @return $this
     */
    public function setCursor($cursor)
    {
        $this->cursor = $cursor;
        return $this;
    }
    /**
     * Returns true if offset exists. False otherwise.
     * @param  integer $offset Offset 
     * @return boolean
     */
    public function offsetExists($offset)
    {
        return isset($this->$offset);
    }
  
    /**
     * Gets offset.
     * @param  integer $offset Offset 
     * @return mixed 
     */
    public function offsetGet($offset)
    {
        return $this->$offset;
    }
  
    /**
     * Sets value based on offset.
     * @param  integer $offset Offset 
     * @param  mixed   $value  Value to be set
     * @return void
     */
    public function offsetSet($offset, $value)
    {
        $this->$offset = $value;
    }
  
    /**
     * Unsets offset.
     * @param  integer $offset Offset 
     * @return void
     */
    public function offsetUnset($offset)
    {
        unset($this->$offset);
    }
  
    /**
     * Gets the string presentation of the object
     * @return string
     */
    public function __toString()
    {
        if (defined('JSON_PRETTY_PRINT')) {
            return json_encode(\SquareConnect\ObjectSerializer::sanitizeForSerialization($this), JSON_PRETTY_PRINT);
        } else {
            return json_encode(\SquareConnect\ObjectSerializer::sanitizeForSerialization($this));
        }
    }
}
