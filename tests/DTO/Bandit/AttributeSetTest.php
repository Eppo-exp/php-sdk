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


    public function testFromFlexibleInput(): void
    {
        $unsortedArray = [
            "areaCode" => "555",
            "age" => 25,
            "country" => "US",
            "zip" => 10101
        ];
        $sortedAttrs = new AttributeSet(
            [
                "age" => 25,
                "zip" => 10101
            ],
            [
                "country" => "US",
                "areaCode" => "555",
            ]
        );

        // Zip is numeric but passed explicitly as a categorical attribute.
        $explicitAttrs = new AttributeSet(["age" => 25], ["country" => "US", "areaCode" => "555", "zip" => 10101]);

        $context1 = AttributeSet::fromFlexibleInput($unsortedArray);
        $context2 = AttributeSet::fromFlexibleInput($sortedAttrs);
        $context3 = AttributeSet::fromFlexibleInput($explicitAttrs);

        $this->assertCount(2, $context1->categoricalAttributes);
        $this->assertCount(2, $context1->numericAttributes);

        $this->assertCount(2, $context2->categoricalAttributes);
        $this->assertCount(2, $context2->numericAttributes);

        $this->assertCount(3, $context3->categoricalAttributes);
        $this->assertCount(1, $context3->numericAttributes);
    }

    public function testArrayFromFlexibleInput(): void
    {
        $arrayOfKeys = ["adidas", "reebok", "nike"];
        $arrayOfUnsortedAttributes = [
            "adidas" => [
                "price" => 10,
                "colour" > "green"
            ],
            "reebok" => [
                "price" => 20,
                "colour" > "red"
            ],
            "nike" => [
                "price" => 15,
                "colour" > "blue"
            ]
        ];
        $arrayOfAttributeSets = [
            "adidas" => AttributeSet::fromArray([
                "price" => 10,
                "size" => 5,
                "colour" > "green"
            ]),
            "reebok" => AttributeSet::fromArray([
                "price" => 20,
                "size" => 5,
                "colour" > "red"
            ]),
            "nike" => AttributeSet::fromArray([
                "price" => 15,
                "size" => 10,
                "colour" > "blue"
            ])
        ];

        $contextArray1 = AttributeSet::arrayFromFlexibleInput($arrayOfKeys);
        $contextArray2 = AttributeSet::arrayFromFlexibleInput($arrayOfUnsortedAttributes);
        $contextArray3 = AttributeSet::arrayFromFlexibleInput($arrayOfAttributeSets);

        $this->assertEquals(["adidas", "reebok", "nike"], array_keys($contextArray1));
        $this->assertEquals(["adidas", "reebok", "nike"], array_keys($contextArray2));
        $this->assertEquals(["adidas", "reebok", "nike"], array_keys($contextArray3));

        $this->assertCount(0, $contextArray1["adidas"]->toArray());
        $this->assertCount(0, $contextArray1["reebok"]->toArray());
        $this->assertCount(0, $contextArray1["nike"]->toArray());


        $this->assertCount(2, $contextArray2["adidas"]->toArray());
        $this->assertCount(2, $contextArray2["reebok"]->toArray());
        $this->assertCount(2, $contextArray2["nike"]->toArray());

        $this->assertCount(3, $contextArray3["adidas"]->toArray());
        $this->assertCount(3, $contextArray3["reebok"]->toArray());
        $this->assertCount(3, $contextArray3["nike"]->toArray());
    }
}
