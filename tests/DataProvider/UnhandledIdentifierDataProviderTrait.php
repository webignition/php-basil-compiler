<?php

declare(strict_types=1);

namespace webignition\BasilTranspiler\Tests\DataProvider;

use webignition\BasilModel\Identifier\AttributeIdentifier;
use webignition\BasilModel\Identifier\ElementIdentifier;
use webignition\BasilModel\Identifier\Identifier;
use webignition\BasilModel\Identifier\IdentifierTypes;
use webignition\BasilModel\Value\LiteralValue;
use webignition\BasilModel\Value\ObjectNames;
use webignition\BasilModel\Value\ObjectValue;
use webignition\BasilModel\Value\ValueTypes;
use webignition\BasilTestIdentifierFactory\TestIdentifierFactory;

trait UnhandledIdentifierDataProviderTrait
{
    public function unhandledIdentifierDataProvider(): array
    {
        return [
            'attribute identifier' => [
                'identifier' => new AttributeIdentifier(
                    new ElementIdentifier(
                        LiteralValue::createCssSelectorValue('.selector')
                    ),
                    'attribute_name'
                ),
            ],
            'page element reference' => [
                'identifier' => TestIdentifierFactory::createPageElementReferenceIdentifier(
                    new ObjectValue(
                        ValueTypes::PAGE_ELEMENT_REFERENCE,
                        'page_import_name.elements.element_name',
                        'page_import_name',
                        'element_name'
                    )
                ),
            ],
            'element parameter' => [
                'identifier' => new Identifier(
                    IdentifierTypes::ELEMENT_PARAMETER,
                    new ObjectValue(
                        ValueTypes::ELEMENT_PARAMETER,
                        '$elements.element_name',
                        ObjectNames::ELEMENT,
                        'element_name'
                    )
                ),
            ],
        ];
    }
}