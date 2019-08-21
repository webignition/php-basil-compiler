<?php
/** @noinspection PhpDocSignatureInspection */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace webignition\BasilTranspiler\Tests\Unit\Value;

use webignition\BasilModel\Identifier\AttributeIdentifier;
use webignition\BasilModel\Identifier\ElementIdentifier;
use webignition\BasilModel\Value\AttributeValue;
use webignition\BasilModel\Value\ElementValue;
use webignition\BasilModel\Value\EnvironmentValue;
use webignition\BasilModel\Value\LiteralValue;
use webignition\BasilModel\Value\ObjectNames;
use webignition\BasilModel\Value\ObjectValue;
use webignition\BasilModel\Value\ValueInterface;
use webignition\BasilModel\Value\ValueTypes;
use webignition\BasilTranspiler\UnknownObjectPropertyException;
use webignition\BasilTranspiler\Value\PageObjectValueTranspiler;

class PageObjectValueTranspilerTest extends \PHPUnit\Framework\TestCase
{
    /**
     * @var PageObjectValueTranspiler
     */
    private $transpiler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transpiler = PageObjectValueTranspiler::createTranspiler();
    }

    /**
     * @dataProvider handlesDoesHandleDataProvider
     * @dataProvider handlesDoesNotHandleDataProvider
     */
    public function testHandles(ValueInterface $value, bool $expectedHandles)
    {
        $this->assertSame($expectedHandles, $this->transpiler->handles($value));
    }

    public function handlesDoesHandleDataProvider(): array
    {
        $expectedHandles = true;

        return [
            'page object property: title' => [
                'value' => new ObjectValue(
                    ValueTypes::PAGE_OBJECT_PROPERTY,
                    '$page.title',
                    ObjectNames::PAGE,
                    'title'
                ),
                'expectedHandles' => $expectedHandles,
            ],
            'page object property: url' => [
                'value' => new ObjectValue(
                    ValueTypes::PAGE_OBJECT_PROPERTY,
                    '$page.url',
                    ObjectNames::PAGE,
                    'url'
                ),
                'expectedHandles' => $expectedHandles,
            ],
        ];
    }

    public function handlesDoesNotHandleDataProvider(): array
    {
        $expectedHandles = false;

        return [
            'literal string' => [
                'value' => LiteralValue::createStringValue('value'),
                'expectedHandles' => $expectedHandles,
            ],
            'literal css selector' => [
                'value' => LiteralValue::createCssSelectorValue('.selector'),
                'expectedHandles' => $expectedHandles,
            ],
            'literal xpath expression' => [
                'value' => LiteralValue::createCssSelectorValue('//h1'),
                'expectedHandles' => $expectedHandles,
            ],
            'browser object property' => [
                'value' => new ObjectValue(
                    ValueTypes::BROWSER_OBJECT_PROPERTY,
                    '$browser.size',
                    ObjectNames::BROWSER,
                    'size'
                ),
                'expectedHandles' => $expectedHandles,
            ],
            'data parameter' => [
                'value' => new ObjectValue(ValueTypes::DATA_PARAMETER, '', '', ''),
                'expectedHandles' => $expectedHandles,
            ],
            'element parameter' => [
                'value' => new ObjectValue(ValueTypes::ELEMENT_PARAMETER, '', '', ''),
                'expectedHandles' => $expectedHandles,
            ],
            'page element reference' => [
                'value' => new ObjectValue(ValueTypes::PAGE_ELEMENT_REFERENCE, '', '', ''),
                'expectedHandles' => $expectedHandles,
            ],
            'attribute parameter' => [
                'value' => new ObjectValue(ValueTypes::ATTRIBUTE_PARAMETER, '', '', ''),
                'expectedHandles' => $expectedHandles,
            ],
            'environment parameter' => [
                'value' => new EnvironmentValue('', ''),
                'expectedHandles' => $expectedHandles,
            ],
            'element identifier' => [
                'value' => new ElementValue(
                    new ElementIdentifier(
                        LiteralValue::createCssSelectorValue('.selector')
                    )
                ),
                'expectedHandles' => $expectedHandles,
            ],
            'attribute identifier' => [
                'value' => new AttributeValue(
                    new AttributeIdentifier(
                        new ElementIdentifier(
                            LiteralValue::createCssSelectorValue('.selector')
                        ),
                        'attribute_name'
                    )
                ),
                'expectedHandles' => $expectedHandles,
            ],
        ];
    }

    public function testTranspileDoesNotHandle()
    {
        $value = new ObjectValue(ValueTypes::DATA_PARAMETER, '', '', '');

        $this->assertNull($this->transpiler->transpile($value));
    }

    public function testTranspileThrowsUnknownObjectPropertyException()
    {
        $value = new ObjectValue(
            ValueTypes::PAGE_OBJECT_PROPERTY,
            '$page.foo',
            ObjectNames::PAGE,
            'foo'
        );

        $this->expectException(UnknownObjectPropertyException::class);
        $this->expectExceptionMessage('Unknown object property "foo"');

        $this->transpiler->transpile($value);
    }
}