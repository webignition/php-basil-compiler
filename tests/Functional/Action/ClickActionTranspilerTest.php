<?php
/** @noinspection PhpDocSignatureInspection */
/** @noinspection PhpUnhandledExceptionInspection */

declare(strict_types=1);

namespace webignition\BasilTranspiler\Tests\Functional\Action;

use webignition\BasilModel\Action\ActionInterface;
use webignition\BasilTranspiler\Action\ClickActionTranspiler;
use webignition\BasilTranspiler\Tests\DataProvider\Action\ClickActionFunctionalDataProviderTrait;
use webignition\BasilTranspiler\Tests\Functional\AbstractTestCase;

class ClickActionTranspilerTest extends AbstractTestCase
{
    use ClickActionFunctionalDataProviderTrait;

    /**
     * @var ClickActionTranspiler
     */
    private $transpiler;

    protected function setUp(): void
    {
        parent::setUp();

        $this->transpiler = ClickActionTranspiler::createTranspiler();
    }

    /**
     * @dataProvider clickActionFunctionalDataProvider
     */
    public function testTranspileForExecutableActions(
        ActionInterface $action,
        string $fixture,
        array $variableIdentifiers,
        array $additionalUseStatements,
        array $additionalPreLines,
        array $additionalPostLines
    ) {
        $transpilationResult = $this->transpiler->transpile($action);

        $executableCall = $this->createExecutableCall(
            $transpilationResult,
            array_merge(self::VARIABLE_IDENTIFIERS, $variableIdentifiers),
            $fixture,
            $additionalPreLines,
            $additionalPostLines,
            $additionalUseStatements
        );

        eval($executableCall);
    }
}