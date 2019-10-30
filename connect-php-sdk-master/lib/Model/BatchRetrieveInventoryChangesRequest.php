<?php
/**
 * NOTE: This class is auto generated by the swagger code generator program.
 * https://github.com/swagger-api/swagger-codegen
 * Do not edit the class manually.
 */

namespace SquareConnect\Model;

use \ArrayAccess;
/**
 * BatchRetrieveInventoryChangesRequest Class Doc Comment
 *
 * @category Class
 * @package  SquareConnect
 * @author   Square Inc.
 * @license  http://www.apache.org/licenses/LICENSE-2.0 Apache License v2
 * @link     https://squareup.com/developers
 */
class BatchRetrieveInventoryChangesRequest implements ArrayAccess
{
    /**
      * Array of property to type mappings. Used for (de)serialization 
      * @var string[]
      */
    static $swaggerTypes = array(
        'catalog_object_ids' => 'string[]',
        'location_ids' => 'string[]',
        'types' => 'string[]',
        'states' => 'string[]',
        'updated_after' => 'string',
        'updated_before' => 'string',
        'cursor' => 'string'
    );
  
    /** 
      * Array of attributes where the key is the local name, and the value is the original name
      * @var string[] 
      */
    static $attributeMap = array(
        'catalog_object_ids' => 'catalog_object_ids',
        'location_ids' => 'location_ids',
        'types' => 'types',
        'states' => 'states',
        'updated_after' => 'updated_after',
        'updated_before' => 'updated_before',
        'cursor' => 'cursor'
    );
  
    /**
      * Array of attributes to setter functions (for deserialization of responses)
      * @var string[]
      */
    static $setters = array(
        'catalog_object_ids' => 'setCatalogObjectIds',
        'location_ids' => 'setLocationIds',
        'types' => 'setTypes',
        'states' => 'setStates',
        'updated_after' => 'setUpdatedAfter',
        'updated_before' => 'setUpdatedBefore',
        'cursor' => 'setCursor'
    );
  
    /**
      * Array of attributes to getter functions (for serialization of requests)
      * @var string[]
      */
    static $getters = array(
        'catalog_object_ids' => 'getCatalogObjectIds',
        'location_ids' => 'getLocationIds',
        'types' => 'getTypes',
        'states' => 'getStates',
        'updated_after' => 'getUpdatedAfter',
        'updated_before' => 'getUpdatedBefore',
        'cursor' => 'getCursor'
    );
  
    /**
      * $catalog_object_ids Filters results by [CatalogObject](#type-catalogobject) ID. Only applied when set. Default: unset.
      * @var string[]
      */
    protected $catalog_object_ids;
    /**
      * $location_ids Filters results by [Location](#type-location) ID. Only applied when set. Default: unset.
      * @var string[]
      */
    protected $location_ids;
    /**
      * $types Filters results by [InventoryChangeType](#type-inventorychangetype). Default: [`PHYSICAL_COUNT`, `ADJUSTMENT`]. `TRANSFER` is not supported as a filter.
      * @var string[]
      */
    protected $types;
    /**
      * $states Filters `ADJUSTMENT` query results by [InventoryState](#type-inventorystate). Only applied when set. Default: unset.
      * @var string[]
      */
    protected $states;
    /**
      * $updated_after Provided as an RFC 3339 timestamp. Returns results whose `created_at` or `calculated_at` value is after the given time. Default: UNIX epoch (`1970-01-01T00:00:00Z`).
      * @var string
      */
    protected $updated_after;
    /**
      * $updated_before Provided as an RFC 3339 timestamp. Returns results whose `created_at` or `calculated_at` value is strictly before the given time. Default: UNIX epoch (`1970-01-01T00:00:00Z`).
      * @var string
      */
    protected $updated_before;
    /**
      * $cursor A pagination cursor returned by a previous call to this endpoint. Provide this to retrieve the next set of results for the original query.  See [Pagination](/basics/api101/pagination) for more information.
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
            if (isset($data["catalog_object_ids"])) {
              $this->catalog_object_ids = $data["catalog_object_ids"];
            } else {
              $this->catalog_object_ids = null;
            }
            if (isset($data["location_ids"])) {
              $this->location_ids = $data["location_ids"];
            } else {
              $this->location_ids = null;
            }
            if (isset($data["types"])) {
              $this->types = $data["types"];
            } else {
              $this->types = null;
            }
            if (isset($data["states"])) {
              $this->states = $data["states"];
            } else {
              $this->states = null;
            }
            if (isset($data["updated_after"])) {
              $this->updated_after = $data["updated_after"];
            } else {
              $this->updated_after = null;
            }
            if (isset($data["updated_before"])) {
              $this->updated_before = $data["updated_before"];
            } else {
              $this->updated_before = null;
            }
            if (isset($data["cursor"])) {
              $this->cursor = $data["cursor"];
            } else {
              $this->cursor = null;
            }
        }
    }
    /**
     * Gets catalog_object_ids
     * @return string[]
     */
    public function getCatalogObjectIds()
    {
        return $this->catalog_object_ids;
    }
  
    /**
     * Sets catalog_object_ids
     * @param string[] $catalog_object_ids Filters results by [CatalogObject](#type-catalogobject) ID. Only applied when set. Default: unset.
     * @return $this
     */
    public function setCatalogObjectIds($catalog_object_ids)
    {
        $this->catalog_object_ids = $catalog_object_ids;
        return $this;
    }
    /**
     * Gets location_ids
     * @return string[]
     */
    public function getLocationIds()
    {
        return $this->location_ids;
    }
  
    /**
     * Sets location_ids
     * @param string[] $location_ids Filters results by [Location](#type-location) ID. Only applied when set. Default: unset.
     * @return $this
     */
    public function setLocationIds($location_ids)
    {
        $this->location_ids = $location_ids;
        return $this;
    }
    /**
     * Gets types
     * @return string[]
     */
    public function getTypes()
    {
        return $this->types;
    }
  
    /**
     * Sets types
     * @param string[] $types Filters results by [InventoryChangeType](#type-inventorychangetype). Default: [`PHYSICAL_COUNT`, `ADJUSTMENT`]. `TRANSFER` is not supported as a filter.
     * @return $this
     */
    public function setTypes($types)
    {
        $this->types = $types;
        return $this;
    }
    /**
     * Gets states
     * @return string[]
     */
    public function getStates()
    {
        return $this->states;
    }
  
    /**
     * Sets states
     * @param string[] $states Filters `ADJUSTMENT` query results by [InventoryState](#type-inventorystate). Only applied when set. Default: unset.
     * @return $this
     */
    public function setStates($states)
    {
        $this->states = $states;
        return $this;
    }
    /**
     * Gets updated_after
     * @return string
     */
    public function getUpdatedAfter()
    {
        return $this->updated_after;
    }
  
    /**
     * Sets updated_after
     * @param string $updated_after Provided as an RFC 3339 timestamp. Returns results whose `created_at` or `calculated_at` value is after the given time. Default: UNIX epoch (`1970-01-01T00:00:00Z`).
     * @return $this
     */
    public function setUpdatedAfter($updated_after)
    {
        $this->updated_after = $updated_after;
        return $this;
    }
    /**
     * Gets updated_before
     * @return string
     */
    public function getUpdatedBefore()
    {
        return $this->updated_before;
    }
  
    /**
     * Sets updated_before
     * @param string $updated_before Provided as an RFC 3339 timestamp. Returns results whose `created_at` or `calculated_at` value is strictly before the given time. Default: UNIX epoch (`1970-01-01T00:00:00Z`).
     * @return $this
     */
    public function setUpdatedBefore($updated_before)
    {
        $this->updated_before = $updated_before;
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
     * @param string $cursor A pagination cursor returned by a previous call to this endpoint. Provide this to retrieve the next set of results for the original query.  See [Pagination](/basics/api101/pagination) for more information.
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