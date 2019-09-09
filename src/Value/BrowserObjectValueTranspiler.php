<?php declare(strict_types=1);

namespace webignition\BasilTranspiler\Value;

use webignition\BasilModel\Value\ObjectNames;
use webignition\BasilModel\Value\ObjectValueInterface;
use webignition\BasilModel\Value\ValueTypes;
use webignition\BasilTranspiler\Model\Call\VariableAssignmentCall;
use webignition\BasilTranspiler\Model\TranspilationResult;
use webignition\BasilTranspiler\Model\TranspilationResultInterface;
use webignition\BasilTranspiler\Model\UseStatementCollection;
use webignition\BasilTranspiler\Model\VariablePlaceholderCollection;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\TranspilerInterface;
use webignition\BasilTranspiler\UnknownObjectPropertyException;
use webignition\BasilTranspiler\VariableNames;

class BrowserObjectValueTranspiler implements TranspilerInterface
{
    const PROPERTY_NAME_SIZE = 'size';

    private $variablePlaceholders;

    public function __construct()
    {
        $this->variablePlaceholders = VariablePlaceholderCollection::createCollection([
            VariableNames::PANTHER_CLIENT,
        ]);
    }

    public static function createTranspiler(): BrowserObjectValueTranspiler
    {
        return new BrowserObjectValueTranspiler();
    }

    public function handles(object $model): bool
    {
        if (!$model instanceof ObjectValueInterface) {
            return false;
        }

        if (ValueTypes::BROWSER_OBJECT_PROPERTY !== $model->getType()) {
            return false;
        }

        return ObjectNames::BROWSER === $model->getObjectName();
    }

    protected function getTranspiledValueMap(): array
    {
        return [
            self::PROPERTY_NAME_SIZE =>
                (string) $this->variablePlaceholders->create(VariableNames::PANTHER_CLIENT) .
                '->getWebDriver()->manage()->window()->getSize()',
        ];
    }

    /**
     * @param object $model
     *
     * @return TranspilationResultInterface
     *
     * @throws NonTranspilableModelException
     * @throws UnknownObjectPropertyException
     */
    public function transpile(object $model): TranspilationResultInterface
    {
        if (!$this->handles($model) || !$model instanceof ObjectValueInterface) {
            throw new NonTranspilableModelException($model);
        }

        $property = $model->getObjectProperty();
        if (self::PROPERTY_NAME_SIZE !== $property) {
            throw new UnknownObjectPropertyException($model);
        }

        $variablePlaceholders = new VariablePlaceholderCollection();
        $webDriverDimensionPlaceholder = $variablePlaceholders->create('WEBDRIVER_DIMENSION');
        $valuePlaceholder = $variablePlaceholders->create('BROWSER_SIZE');
        $pantherClientPlaceholder = $variablePlaceholders->create(VariableNames::PANTHER_CLIENT);

        $dimensionAssignmentStatement = sprintf(
            '%s = %s',
            $webDriverDimensionPlaceholder,
            $pantherClientPlaceholder . '->getWebDriver()->manage()->window()->getSize()'
        );

        $getWidthCall = $webDriverDimensionPlaceholder . '->getWidth()';
        $getHeightCall = $webDriverDimensionPlaceholder . '->getHeight()';

        $dimensionConcatenationStatement = '(string) ' . $getWidthCall . ' . \'x\' . (string) ' . $getHeightCall;

        return new VariableAssignmentCall(
            new TranspilationResult(
                [
                    $dimensionAssignmentStatement,
                    $dimensionConcatenationStatement,
                ],
                new UseStatementCollection(),
                new VariablePlaceholderCollection([
                    $webDriverDimensionPlaceholder,
                    $valuePlaceholder,
                    $pantherClientPlaceholder,
                ])
            ),
            $valuePlaceholder
        );
    }
}
