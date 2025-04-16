<?php

namespace Eppo\Tests\DTO;

use Eppo\DTO\Bandit\Bandit;
use PHPUnit\Framework\TestCase;

class BanditDTOTest extends TestCase
{
    private const BANDIT_JSON = '{
      "banditKey": "banner_bandit",
      "modelName": "falcon",
      "updatedAt": "2023-09-13T04:52:06.462Z",
      "modelVersion": "v123",
      "modelData": {
        "gamma": 1.0,
        "defaultActionScore": 0.0,
        "actionProbabilityFloor": 0.0,
        "coefficients": {
          "nike": {
            "actionKey": "nike",
            "intercept": 1.0,
            "actionNumericCoefficients": [
              {
                "attributeKey": "brand_affinity",
                "coefficient": 1.0,
                "missingValueCoefficient": -0.1
              }
            ],
            "actionCategoricalCoefficients": [
              {
                "attributeKey": "loyalty_tier",
                "valueCoefficients": {
                   "gold": 4.5,
                   "silver":  3.2,
                   "bronze":  1.9
                },
                "missingValueCoefficient": 0.0
              },
              {
                "attributeKey": "zip",
                "valueCoefficients": {
                  "22203": 5.0,
                  "94111": -10.0,
                  "81427": 8.0
                },
                "missingValueCoefficient": 0.0
              }
            ],
            "subjectNumericCoefficients": [
              {
                "attributeKey": "account_age",
                "coefficient": 0.3,
                "missingValueCoefficient": 0.0
              }
            ],
            "subjectCategoricalCoefficients": [
              {
                "attributeKey": "gender_identity",
                "valueCoefficients": {
                  "female": 0.5,
                  "male": -0.5
                },
                "missingValueCoefficient": 2.3
              }
            ]
          },
          "adidas": {
            "actionKey": "adidas",
            "intercept": 1.1,
            "actionNumericCoefficients": [
              {
                "attributeKey": "brand_affinity",
                "coefficient": 2.0,
                "missingValueCoefficient": 1.2
              }
            ],
            "actionCategoricalCoefficients": [
              {
                "attributeKey": "purchased_last_30_days",
                "valueCoefficients": {
                  "true": 9.0,
                  "false": 0.0
                },
                "missingValueCoefficient": 0.0
              }
            ],
            "subjectNumericCoefficients": [],
            "subjectCategoricalCoefficients": [
              {
                "attributeKey": "gender_identity",
                "valueCoefficients": {
                  "female": 0.0,
                  "male": 0.3
                },
                "missingValueCoefficient": 0.45
              },
              {
                "attributeKey": "area_code",
                "valueCoefficients": {
                  "303": 10.0,
                  "301": 7.0,
                  "415": -2.0
                },
                "missingValueCoefficient": 0.0
              }
            ]
          }
        }
      }
    }';

    public function testParsesFromJson(): void
    {
        $json = json_decode(self::BANDIT_JSON, true);
        $bandit = Bandit::fromArray($json);

        $this->assertNotNull($bandit);

        // Assert on most properties down the entire object tree, testing the fromJSON methods of each DTO.

        $this->assertEquals('banner_bandit', $bandit->banditKey);
        $this->assertEquals('falcon', $bandit->modelName);
        $this->assertEquals('v123', $bandit->modelVersion);
        $this->assertNotNull($bandit->modelData);

        $model = $bandit->modelData;

        $this->assertEquals(1.0, $model->gamma);
        $this->assertEquals(0.0, $model->defaultActionScore);

        $coefficients = $model->coefficients;

        $this->assertNotNull($coefficients);
        $this->assertEquals(['nike', 'adidas'], array_keys($coefficients));

        $nike = $coefficients['nike'];
        $this->assertNotNull($nike);
        $this->assertEquals('nike', $nike->actionKey);
        $this->assertEquals(1, $nike->intercept);

        $this->assertNotNull($nike->actionNumericCoefficients);
        $this->assertCount(1, $nike->actionNumericCoefficients);

        $this->assertNotNull($nike->actionCategoricalCoefficients);
        $this->assertCount(2, $nike->actionCategoricalCoefficients);

        $this->assertNotNull($nike->subjectNumericCoefficients);
        $this->assertCount(1, $nike->subjectNumericCoefficients);

        $this->assertNotNull($nike->subjectCategoricalCoefficients);
        $this->assertCount(1, $nike->subjectCategoricalCoefficients);

        $coefficient = $nike->actionCategoricalCoefficients[0];
        $this->assertNotNull($coefficient);
        $this->assertEquals('loyalty_tier', $coefficient->attributeKey);
        $this->assertEquals([
            "gold" => 4.5,
            "silver" => 3.2,
            "bronze" => 1.9
        ], $coefficient->valueCoefficients);
        $this->assertEquals(0, $coefficient->missingValueCoefficient);
    }
}
