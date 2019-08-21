<?php

declare(strict_types=1);

namespace webignition\BasilTranspiler\Tests\DataProvider;

use webignition\BasilModel\Value\ObjectNames;
use webignition\BasilModel\Value\ObjectValue;
use webignition\BasilModel\Value\ValueTypes;

trait BrowserObjectValueDataProviderTrait
{
    public function browserObjectValueDataProvider(): array
    {
        return [
            'default browser object property' => [
                'value' => new ObjectValue(
                    ValueTypes::BROWSER_OBJECT_PROPERTY,
                    '$browser.size',
                    ObjectNames::BROWSER,
                    'size'
                ),
            ],
        ];
    }
}