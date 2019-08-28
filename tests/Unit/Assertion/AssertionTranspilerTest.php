<?php
/** @noinspection PhpDocSignatureInspection */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace webignition\BasilTranspiler\Tests\Unit\Assertion;

use webignition\BasilModel\Assertion\AssertionInterface;
use webignition\BasilModel\Value\ObjectValue;
use webignition\BasilModelFactory\AssertionFactory;
use webignition\BasilTranspiler\Assertion\AssertionTranspiler;
use webignition\BasilTranspiler\Model\UseStatement;
use webignition\BasilTranspiler\Model\UseStatementCollection;
use webignition\BasilTranspiler\Model\VariablePlaceholder;
use webignition\BasilTranspiler\Model\VariablePlaceholderCollection;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\Tests\DataProvider\Assertion\ExistsAssertionDataProviderTrait;
use webignition\BasilTranspiler\Tests\DataProvider\Assertion\UnhandledAssertionDataProviderTrait;
use webignition\BasilTranspiler\VariableNames;
use webignition\SymfonyDomCrawlerNavigator\Model\ElementLocator;
use webignition\SymfonyDomCrawlerNavigator\Model\LocatorType;

class AssertionTranspilerTest extends \PHPUnit\Framework\TestCase
{
    use ExistsAssertionDataProviderTrait;
    use UnhandledAssertionDataProviderTrait;

    /**
     * @var AssertionTranspiler
     */
    private $transpiler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transpiler = AssertionTranspiler::createTranspiler();
    }

    /**
     * @dataProvider existsAssertionDataProvider
     */
    public function testHandlesDoesHandle(AssertionInterface $model)
    {
        $this->assertTrue($this->transpiler->handles($model));
    }

    /**
     * @dataProvider handlesDoesNotHandleDataProvider
     * @dataProvider unhandledAssertionDataProvider
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
    public function testTranspile(
        AssertionInterface $assertion,
        string $expectedContentPattern,
        UseStatementCollection $expectedUseStatements,
        VariablePlaceholderCollection $expectedPlaceholders
    ) {
        $transpilationResult = $this->transpiler->transpile($assertion);

        $this->assertRegExp($expectedContentPattern, (string) $transpilationResult);
        $this->assertEquals($expectedUseStatements->getAll(), $transpilationResult->getUseStatements()->getAll());
        $this->assertEquals($expectedPlaceholders->getAll(), $transpilationResult->getVariablePlaceholders()->getAll());
    }

    public function transpileDataProvider(): array
    {
        $assertionFactory = AssertionFactory::createFactory();

        $phpUnitTestCasePlaceholder = new VariablePlaceholder(VariableNames::PHPUNIT_TEST_CASE);
        $domCrawlerNavigatorPlaceholder = new VariablePlaceholder(VariableNames::DOM_CRAWLER_NAVIGATOR);
        $environmentVariablePlaceholder = new VariablePlaceholder('ENVIRONMENT_VARIABLE');
        $environmentVariableArrayPlaceholder = new VariablePlaceholder(VariableNames::ENVIRONMENT_VARIABLE_ARRAY);

        return [
            'exists comparison, element identifier examined value' => [
                'assertion' => $assertionFactory->createFromAssertionString(
                    '".selector" exists'
                ),
                'expectedContentPattern' =>
                    '/^'
                    . $phpUnitTestCasePlaceholder
                    . '->assertTrue\('
                    . $domCrawlerNavigatorPlaceholder
                    . '->hasElement\(.*\)$/',
                'expectedUseStatements' => new UseStatementCollection([
                    new UseStatement(ElementLocator::class),
                    new UseStatement(LocatorType::class),
                ]),
                'expectedVariablePlaceholders' => new VariablePlaceholderCollection([
                    new VariablePlaceholder(VariableNames::DOM_CRAWLER_NAVIGATOR),
                    new VariablePlaceholder(VariableNames::PHPUNIT_TEST_CASE),
                ]),
            ],
            'exists comparison, attribute identifier examined value' => [
                'assertion' => $assertionFactory->createFromAssertionString(
                    '".selector".attribute_name exists'
                ),
                'expectedContentPattern' => '//',
                'expectedUseStatements' => new UseStatementCollection([
                    new UseStatement(ElementLocator::class),
                    new UseStatement(LocatorType::class),
                ]),
                'expectedVariablePlaceholders' => new VariablePlaceholderCollection([
                    new VariablePlaceholder('ELEMENT_LOCATOR'),
                    new VariablePlaceholder('ELEMENT'),
                    new VariablePlaceholder(VariableNames::PHPUNIT_TEST_CASE),
                    new VariablePlaceholder(VariableNames::DOM_CRAWLER_NAVIGATOR),
                ]),
            ],
            'exists comparison, environment examined value' => [
                'assertion' => $assertionFactory->createFromAssertionString(
                    '$env.KEY exists'
                ),
                'expectedContentPattern' =>
                    '/^'
                    . $environmentVariablePlaceholder
                    .' = '
                    . $environmentVariableArrayPlaceholder
                    . preg_quote('[\'KEY\'] ?? null', "/")
                    . "\n"
                    . $phpUnitTestCasePlaceholder
                    .'->assertNotNull\('
                    . $environmentVariablePlaceholder
                    .'\)/m',
                'expectedUseStatements' => new UseStatementCollection(),
                'expectedVariablePlaceholders' => new VariablePlaceholderCollection([
                    new VariablePlaceholder(VariableNames::PHPUNIT_TEST_CASE),
                ]),
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
