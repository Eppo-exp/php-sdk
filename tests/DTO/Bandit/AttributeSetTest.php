<?php

namespace Eppo\Tests\DTO\Bandit;

use Eppo\DTO\Bandit\AttributeSet;
use PHPUnit\Framework\TestCase;

class AttributeSetTest extends TestCase
{
    public function testConstructor()
    {
        $numericAttributes = array('age' => 25.0, 'height' => 1.80);
        $categoricalAttributes = array('city' => 'New York', 'subscribed' => true, 'zip' => 90210);

        $attributeSet = new AttributeSet($numericAttributes, $categoricalAttributes);

        $this->assertEquals($numericAttributes, $attributeSet->numericAttributes);
        $this->assertEquals($categoricalAttributes, $attributeSet->categoricalAttributes);
    }

    public function testFromArray()
    {
        $attributes = array(
            'age' => 25,
            'height' => 1.8,
            'city' => 'London',
            'subscribed' => false,
            'zip' => 90210, // Numeric
            'areaCode' => "90210" // String so should be categorical
        );

        $attributeSet = AttributeSet::fromArray($attributes);

        $expectedNumericAttributes = array(
            'age' => 25.0,
            'height' => 1.8,
            'zip' => 90210
        );
        $expectedCategoricalAttributes = array(
            'city' => 'London',
            'subscribed' => false,
            'areaCode' => "90210"
        );

        $this->assertEquals($expectedNumericAttributes, $attributeSet->numericAttributes);
        $this->assertEquals($expectedCategoricalAttributes, $attributeSet->categoricalAttributes);
    }

    public function testFromArrayFiltersInvalidValueTypes()
    {
        $attributes = array(
            'age' => 25,
            'active' => null,
            'city' => 'Paris',
            'subscribed' => [1, 2, 3]
        );

        $attributeSet = AttributeSet::fromArray($attributes);

        $expectedNumericAttributes = array(
            'age' => 25.0
        );
        $expectedCategoricalAttributes = array(
            'city' => 'Paris'
        );

        $this->assertEquals($expectedNumericAttributes, $attributeSet->numericAttributes);
        $this->assertEquals($expectedCategoricalAttributes, $attributeSet->categoricalAttributes);
    }

    public function testDropsNonNumericAttributes(): void
    {
        $numericAttributes = array('age' => 25, 'height' => 1.80, 'mistake' => 'whoops');
        $expectedNumericAttributes = array('age' => 25, 'height' => 1.80);
        $categoricalAttributes = array('city' => 'New York', 'subscribed' => true, 'zip' => 90210);

        $attributeSet = new AttributeSet($numericAttributes, $categoricalAttributes);

        $this->assertEquals($expectedNumericAttributes, $attributeSet->numericAttributes);
        $this->assertEquals($categoricalAttributes, $attributeSet->categoricalAttributes);
    }
}
