<?php
/** @noinspection PhpDocSignatureInspection */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace webignition\BasilTranspiler\Tests\Unit\Value;

use webignition\BasilModel\Identifier\ElementIdentifier;
use webignition\BasilModel\Value\ElementValue;
use webignition\BasilModel\Value\EnvironmentValue;
use webignition\BasilModel\Value\LiteralValue;
use webignition\BasilModel\Value\ObjectNames;
use webignition\BasilModel\Value\ObjectValue;
use webignition\BasilModel\Value\ValueInterface;
use webignition\BasilModel\Value\ValueTypes;
use webignition\BasilTestIdentifierFactory\TestIdentifierFactory;
use webignition\BasilTranspiler\Model\Call\VariableAssignmentCall;
use webignition\BasilTranspiler\Model\TranspilationResult;
use webignition\BasilTranspiler\Model\TranspilationResultInterface;
use webignition\BasilTranspiler\Model\UseStatementCollection;
use webignition\BasilTranspiler\Model\VariablePlaceholderCollection;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\Tests\DataProvider\Value\BrowserObjectValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\ElementValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\EnvironmentParameterValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\CssSelectorValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\LiteralValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\XpathExpressionValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\PageObjectValueDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Value\UnhandledValueDataProviderTrait;
use webignition\BasilTranspiler\Value\ValueTranspiler;
use webignition\BasilTranspiler\VariableNames;
use webignition\BasilTranspiler\Model\VariablePlaceholder;

class ValueTranspilerTest extends \PHPUnit\Framework\TestCase
{
    use BrowserObjectValueDataProviderTrait;
    use ElementValueDataProviderTrait;
    use EnvironmentParameterValueDataProviderTrait;
    use CssSelectorValueDataProviderTrait;
    use LiteralValueDataProviderTrait;
    use XpathExpressionValueDataProviderTrait;
    use PageObjectValueDataProviderTrait;
    use UnhandledValueDataProviderTrait;

    /**
     * @var ValueTranspiler
     */
    private $transpiler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transpiler = ValueTranspiler::createTranspiler();
    }

    /**
     * @dataProvider browserObjectValueDataProvider
     * @dataProvider elementValueDataProvider
     * @dataProvider environmentParameterValueDataProvider
     * @dataProvider cssSelectorValueDataProvider
     * @dataProvider literalValueDataProvider
     * @dataProvider xpathExpressionValueDataProvider
     * @dataProvider pageObjectValueDataProvider
     */
    public function testHandlesDoesHandle(ValueInterface $model)
    {
        $this->assertTrue($this->transpiler->handles($model));
    }

    /**
     * @dataProvider handlesDoesNotHandleDataProvider
     * @dataProvider unhandledValueDataProvider
     */
    public function testHandlesDoesNotHandle(object $model)
    {
        $this->assertFalse($this->transpiler->handles($model));
    }

    public function handlesDoesNotHandleDataProvider(): array
    {
        return [
            'non-value object' => [
                'value' => new \stdClass(),
            ],
        ];
    }

    /**
     * @dataProvider transpileDataProvider
     */
    public function testTranspile(ValueInterface $model, TranspilationResultInterface $expectedTranspilationResult)
    {
        $this->assertEquals($expectedTranspilationResult, $this->transpiler->transpile($model));
    }

    public function transpileDataProvider(): array
    {
        return [
            'literal string value: string' => [
                'value' => LiteralValue::createStringValue('value'),
                'expectedTranspilationResult' => new TranspilationResult(
                    ['"value"'],
                    new UseStatementCollection(),
                    new VariablePlaceholderCollection()
                ),
            ],
            'literal string value: integer' => [
                'value' => LiteralValue::createStringValue('100'),
                'expectedTranspilationResult' => new TranspilationResult(
                    ['"100"'],
                    new UseStatementCollection(),
                    new VariablePlaceholderCollection()
                ),
            ],
            'environment parameter value' => [
                'value' => new EnvironmentValue(
                    '$env.KEY',
                    'KEY'
                ),
                'expectedTranspilationResult' => new TranspilationResult(
                    [(string) new VariablePlaceholder(VariableNames::ENVIRONMENT_VARIABLE_ARRAY) . '[\'KEY\']'],
                    new UseStatementCollection(),
                    VariablePlaceholderCollection::createCollection([
                        VariableNames::ENVIRONMENT_VARIABLE_ARRAY,
                    ])
                ),
            ],
            'element identifier, css selector' => [
                'value' => new ElementValue(
                    new ElementIdentifier(
                        LiteralValue::createCssSelectorValue('.selector')
                    )
                ),
                'expectedTranspilationResult' => new TranspilationResult(
                    ['".selector"'],
                    new UseStatementCollection(),
                    new VariablePlaceholderCollection()
                ),
            ],
            'element identifier, css selector with non-default position' => [
                'value' => new ElementValue(
                    new ElementIdentifier(
                        LiteralValue::createCssSelectorValue('.selector'),
                        2
                    )
                ),
                'expectedTranspilationResult' => new TranspilationResult(
                    ['".selector"'],
                    new UseStatementCollection(),
                    new VariablePlaceholderCollection()
                ),
            ],
            'element identifier, css selector with name' => [
                'value' => new ElementValue(
                    TestIdentifierFactory::createCssElementIdentifier('.selector', null, 'element_name')
                ),
                'expectedTranspilationResult' => new TranspilationResult(
                    ['".selector"'],
                    new UseStatementCollection(),
                    new VariablePlaceholderCollection()
                ),
            ],
            'element identifier, xpath expression' => [
                'value' => new ElementValue(
                    new ElementIdentifier(
                        LiteralValue::createXpathExpressionValue('//h1')
                    )
                ),
                'expectedTranspilationResult' => new TranspilationResult(
                    ['"//h1"'],
                    new UseStatementCollection(),
                    new VariablePlaceholderCollection()
                ),
            ],
            'browser object value, size' => [
                'value' => new ObjectValue(
                    ValueTypes::BROWSER_OBJECT_PROPERTY,
                    '$browser.size',
                    ObjectNames::BROWSER,
                    'size'
                ),
                'expectedTranspilationResult' => new VariableAssignmentCall(
                    new TranspilationResult(
                        [
                        '{{ WEBDRIVER_DIMENSION }} = '
                        . '{{ PANTHER_CLIENT }}->getWebDriver()->manage()->window()->getSize()',
                        '(string) {{ WEBDRIVER_DIMENSION }}->getWidth() . \'x\' . '
                        . '(string) {{ WEBDRIVER_DIMENSION }}->getHeight()',
                        ],
                        new UseStatementCollection(),
                        new VariablePlaceholderCollection([
                            new VariablePlaceholder('WEBDRIVER_DIMENSION'),
                            new VariablePlaceholder('BROWSER_SIZE'),
                            new VariablePlaceholder(VariableNames::PANTHER_CLIENT),
                        ])
                    ),
                    new VariablePlaceholder('BROWSER_SIZE')
                ),
            ],
        ];
    }

    public function testTranspileNonTranspilableModel()
    {
        $model = new ObjectValue('foo', '', '', '');

        $this->expectException(NonTranspilableModelException::class);
        $this->expectExceptionMessage('Non-transpilable model "webignition\BasilModel\Value\ObjectValue"');

        $this->transpiler->transpile($model);
    }
}
