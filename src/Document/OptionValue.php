<?php
/**
 * @Author: Sebastian Castro
 * @Date:   2017-03-03 15:23:08
 * @Last Modified by:   Sebastian Castro
 * @Last Modified time: 2018-04-01 13:15:05
 */

namespace App\Document;

use Doctrine\ODM\MongoDB\Mapping\Annotations as MongoDB;
use JMS\Serializer\Annotation\Expose;

/** @MongoDB\EmbeddedDocument */
class OptionValue
{
    /** @MongoDB\Id */
    private $id;

    /**
     * @Expose
     * @MongoDB\Field(type="int") @MongoDB\Index
     */
    public $optionId;

    /**
     * @Expose
     * @MongoDB\Field(type="string")
     */
    public $description;

    /**
     * @Expose
     * @MongoDB\Field(type="int")
     */
    public $index = 0;

    public function toJson($optionName)
    {
        $result = '{';
        $result .= '"id":'.$this->optionId;
        if ($optionName) {
            $result .= ', "name":'.$optionName;
        }
        $result .= ', "description":'.json_encode($this->description);
        $result .= ', "index":'.$this->index;
        $result .= '}';

        return $result;
    }

    /**
     * Get id.
     *
     * @return id $id
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * Set description.
     *
     * @param string $description
     *
     * @return $this
     */
    public function setDescription($description)
    {
        $this->description = $description;

        return $this;
    }

    /**
     * Get description.
     *
     * @return string $description
     */
    public function getDescription()
    {
        return $this->description;
    }

    /**
     * Set index.
     *
     * @param int $index
     *
     * @return $this
     */
    public function setIndex($index)
    {
        $this->index = $index;

        return $this;
    }

    /**
     * Get index.
     *
     * @return int $index
     */
    public function getIndex()
    {
        return $this->index;
    }

    /**
     * Set optionId.
     *
     * @param int $optionId
     *
     * @return $this
     */
    public function setOptionId($optionId)
    {
        $this->optionId = $optionId;

        return $this;
    }

    /**
     * Get optionId.
     *
     * @return int $optionId
     */
    public function getOptionId()
    {
        return $this->optionId;
    }

    public function getStringOptionId()
    {
        return strval($this->optionId);
    }
}
