<?php
/** @noinspection PhpDocSignatureInspection */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace webignition\BasilTranspiler\Tests\Functional\Action;

use webignition\BasilModel\Action\ActionInterface;
use webignition\BasilTranspiler\Action\WaitActionTranspiler;
use webignition\BasilTranspiler\Tests\DataProvider\Action\WaitActionFunctionalDataProviderTrait;
use webignition\BasilTranspiler\Tests\Functional\AbstractTestCase;

class WaitActionTranspilerTest extends AbstractTestCase
{
    use WaitActionFunctionalDataProviderTrait;

    /**
     * @var WaitActionTranspiler
     */
    private $transpiler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transpiler = WaitActionTranspiler::createTranspiler();
    }

    /**
     * @dataProvider waitActionFunctionalDataProvider
     */
    public function testTranspileForExecutableActions(
        ActionInterface $action,
        string $fixture,
        array $variableIdentifiers,
        array $additionalPreLines,
        array $additionalPostLines,
        array $additionalUseStatements,
        int $expectedDuration
    ) {
        $transpilationResult = $this->transpiler->transpile($action);

        $expectedDurationThreshold = $expectedDuration + 1;

        $executableCall = $this->createExecutableCall(
            $transpilationResult,
            array_merge(self::VARIABLE_IDENTIFIERS, $variableIdentifiers),
            $fixture,
            $additionalPreLines,
            $additionalPostLines,
            $additionalUseStatements
        );

        $executableCallLines = explode("\n", $executableCall);
        $sleepLine = array_pop($executableCallLines);

        $executableCallLines = array_merge($executableCallLines, [
            '$before = microtime(true);',
            $sleepLine,
            '$executionDurationInMilliseconds = (microtime(true) - $before) * 1000;',
            '$this->assertGreaterThan(' . $expectedDuration . ', $executionDurationInMilliseconds);',
            '$this->assertLessThan(' . $expectedDurationThreshold . ', $executionDurationInMilliseconds);',
        ]);

        $executableCall = implode("\n", $executableCallLines);

        eval($executableCall);
    }
}