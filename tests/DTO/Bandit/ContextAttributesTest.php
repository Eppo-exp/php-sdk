<?php

namespace DTO\Bandit;

use Eppo\DTO\Bandit\AttributeSet;
use Eppo\DTO\Bandit\ContextAttributes;
use PHPUnit\Framework\TestCase;

class ContextAttributesTest extends TestCase
{
    public function testFromFlexibleInput(): void
    {
        $contextKey = "key";
        $unsortedArray = [
            "areaCode" => "555",
            "age" => 25,
            "country" => "US",
            "zip" => 10101
        ];
        $sortedAttrs = AttributeSet::fromArray($unsortedArray);

        // Zip is numeric but passed explicitly as a categorical attribute.
        $explicitAttrs = new AttributeSet(["age" => 25], ["country" => "US", "areaCode" => "555", "zip" => 10101]);

        $context1 = ContextAttributes::fromFlexibleInput($contextKey, $unsortedArray);
        $context2 = ContextAttributes::fromFlexibleInput($contextKey, $sortedAttrs);
        $context3 = ContextAttributes::fromFlexibleInput($contextKey, $explicitAttrs);

        $this->assertEquals($contextKey, $context1->getKey());
        $this->assertEquals($contextKey, $context2->getKey());
        $this->assertEquals($contextKey, $context3->getKey());

        $this->assertCount(2, $context1->getAttributes()->categoricalAttributes);
        $this->assertCount(2, $context1->getAttributes()->numericAttributes);

        $this->assertCount(2, $context2->getAttributes()->categoricalAttributes);
        $this->assertCount(2, $context2->getAttributes()->numericAttributes);

        $this->assertCount(3, $context3->getAttributes()->categoricalAttributes);
        $this->assertCount(1, $context3->getAttributes()->numericAttributes);
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

        $contextArray1 = ContextAttributes::arrayFromFlexibleInput($arrayOfKeys);
        $contextArray2 = ContextAttributes::arrayFromFlexibleInput($arrayOfUnsortedAttributes);
        $contextArray3 = ContextAttributes::arrayFromFlexibleInput($arrayOfAttributeSets);

        $this->assertEquals(["adidas", "reebok", "nike"], array_keys($contextArray1));
        $this->assertEquals(["adidas", "reebok", "nike"], array_keys($contextArray2));
        $this->assertEquals(["adidas", "reebok", "nike"], array_keys($contextArray3));

        $this->assertCount(0, $contextArray1["adidas"]->getAttributes()->toArray());
        $this->assertCount(0, $contextArray1["reebok"]->getAttributes()->toArray());
        $this->assertCount(0, $contextArray1["nike"]->getAttributes()->toArray());


        $this->assertCount(2, $contextArray2["adidas"]->getAttributes()->toArray());
        $this->assertCount(2, $contextArray2["reebok"]->getAttributes()->toArray());
        $this->assertCount(2, $contextArray2["nike"]->getAttributes()->toArray());

        $this->assertCount(3, $contextArray3["adidas"]->getAttributes()->toArray());
        $this->assertCount(3, $contextArray3["reebok"]->getAttributes()->toArray());
        $this->assertCount(3, $contextArray3["nike"]->getAttributes()->toArray());
    }
}
