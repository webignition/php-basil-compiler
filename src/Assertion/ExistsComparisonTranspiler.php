<?php declare(strict_types=1);

namespace webignition\BasilTranspiler\Assertion;

use webignition\BasilModel\Assertion\AssertionComparisons;
use webignition\BasilModel\Assertion\AssertionInterface;
use webignition\BasilModel\Value\AttributeValueInterface;
use webignition\BasilModel\Value\ElementValueInterface;
use webignition\BasilModel\Value\EnvironmentValueInterface;
use webignition\BasilModel\Value\ObjectNames;
use webignition\BasilModel\Value\ObjectValueInterface;
use webignition\BasilModel\Value\ValueInterface;
use webignition\BasilTranspiler\CallFactory\AssertionCallFactory;
use webignition\BasilTranspiler\CallFactory\VariableAssignmentCallFactory;
use webignition\BasilTranspiler\DomCrawlerNavigatorCallFactory;
use webignition\BasilTranspiler\ElementLocatorCallFactory;
use webignition\BasilTranspiler\Model\TranspilationResult;
use webignition\BasilTranspiler\Model\VariablePlaceholder;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\TranspilationResultComposer;
use webignition\BasilTranspiler\TranspilerInterface;
use webignition\BasilTranspiler\UnknownItemException;
use webignition\BasilTranspiler\Value\ValueTranspiler;
use webignition\BasilTranspiler\VariableNames;

class ExistsComparisonTranspiler implements TranspilerInterface
{
    const VARIABLE_EXISTS_TEMPLATE = '%s->assertNotNull(%s)';
    const VARIABLE_NOT_EXISTS_TEMPLATE = '%s->assertNull(%s)';

    private $assertionCallFactory;
    private $variableAssignmentCallFactory;
    private $valueTranspiler;
    private $domCrawlerNavigatorCallFactory;
    private $elementLocatorCallFactory;
    private $assertableValueExaminer;
    private $phpUnitTestCasePlaceholder;
    private $transpilationResultComposer;

    /**
     * @var string
     */
    private $attributeExistsTemplate = '';

    /**
     * @var string
     */
    private $attributeNotExistsTemplate = '';

    public function __construct(
        AssertionCallFactory $assertionCallFactory,
        VariableAssignmentCallFactory $variableAssignmentCallFactory,
        ValueTranspiler $valueTranspiler,
        DomCrawlerNavigatorCallFactory $domCrawlerNavigatorCallFactory,
        ElementLocatorCallFactory $elementLocatorCallFactory,
        AssertableValueExaminer $assertableValueExaminer,
        TranspilationResultComposer $transpilationResultComposer
    ) {
        $this->assertionCallFactory = $assertionCallFactory;
        $this->variableAssignmentCallFactory = $variableAssignmentCallFactory;
        $this->valueTranspiler = $valueTranspiler;
        $this->domCrawlerNavigatorCallFactory = $domCrawlerNavigatorCallFactory;
        $this->elementLocatorCallFactory = $elementLocatorCallFactory;
        $this->assertableValueExaminer = $assertableValueExaminer;
        $this->transpilationResultComposer = $transpilationResultComposer;

        $this->phpUnitTestCasePlaceholder = new VariablePlaceholder(VariableNames::PHPUNIT_TEST_CASE);
        $this->attributeExistsTemplate = sprintf(
            self::VARIABLE_EXISTS_TEMPLATE,
            '%s',
            '%s->getAttribute(\'%s\')'
        );

        $this->attributeNotExistsTemplate = sprintf(
            self::VARIABLE_NOT_EXISTS_TEMPLATE,
            '%s',
            '%s->getAttribute(\'%s\')'
        );
    }

    public static function createTranspiler(): ExistsComparisonTranspiler
    {
        return new ExistsComparisonTranspiler(
            AssertionCallFactory::createFactory(),
            VariableAssignmentCallFactory::createFactory(),
            ValueTranspiler::createTranspiler(),
            DomCrawlerNavigatorCallFactory::createFactory(),
            ElementLocatorCallFactory::createFactory(),
            AssertableValueExaminer::create(),
            TranspilationResultComposer::create()
        );
    }

    public function handles(object $model): bool
    {
        if (!$model instanceof AssertionInterface) {
            return false;
        }

        return in_array($model->getComparison(), [
            AssertionComparisons::EXISTS,
            AssertionComparisons::NOT_EXISTS,
        ]);
    }

    /**
     * @param object $model
     *
     * @return TranspilationResult
     *
     * @throws NonTranspilableModelException
     * @throws UnknownItemException
     */
    public function transpile(object $model): TranspilationResult
    {
        if (!$model instanceof AssertionInterface) {
            throw new NonTranspilableModelException($model);
        }

        $isHandledComparison = in_array($model->getComparison(), [
            AssertionComparisons::EXISTS,
            AssertionComparisons::NOT_EXISTS,
        ]);

        if (false === $isHandledComparison) {
            throw new NonTranspilableModelException($model);
        }

        $examinedValue = $model->getExaminedValue();
        if (!$this->assertableValueExaminer->isAssertableExaminedValue($examinedValue)) {
            throw new NonTranspilableModelException($model);
        }

        if ($examinedValue instanceof ElementValueInterface) {
            $hasElementCall = $this->domCrawlerNavigatorCallFactory->createHasElementCallForIdentifier(
                $examinedValue->getIdentifier()
            );

            return AssertionComparisons::EXISTS === $model->getComparison()
                ? $this->assertionCallFactory->createElementExistsAssertionCall($hasElementCall)
                : $this->assertionCallFactory->createElementNotExistsAssertionCall($hasElementCall);
        }

        if ($examinedValue instanceof AttributeValueInterface) {
            return $this->transpileForAttributeValue($examinedValue, (string) $model->getComparison());
        }

        if ($examinedValue instanceof EnvironmentValueInterface) {
            return $this->transpileForScalarValue(
                $examinedValue,
                new VariablePlaceholder('ENVIRONMENT_VARIABLE'),
                (string) $model->getComparison()
            );
        }

        if ($examinedValue instanceof ObjectValueInterface) {
            if (ObjectNames::BROWSER === $examinedValue->getObjectName()) {
                return $this->transpileForScalarValue(
                    $examinedValue,
                    new VariablePlaceholder('BROWSER_VARIABLE'),
                    (string) $model->getComparison()
                );
            }

            if (ObjectNames::PAGE === $examinedValue->getObjectName()) {
                return $this->transpileForScalarValue(
                    $examinedValue,
                    new VariablePlaceholder('PAGE_VARIABLE'),
                    (string) $model->getComparison()
                );
            }
        }

        throw new NonTranspilableModelException($model);
    }

    /**
     * @param AttributeValueInterface $attributeValue
     * @param string $comparison
     *
     * @return TranspilationResult
     *
     * @throws NonTranspilableModelException
     * @throws UnknownItemException
     */
    private function transpileForAttributeValue(
        AttributeValueInterface $attributeValue,
        string $comparison
    ): TranspilationResult {
        $attributeIdentifier = $attributeValue->getIdentifier();
        $elementIdentifier = $attributeIdentifier->getElementIdentifier();
        $attributeName = (string) $attributeIdentifier->getAttributeName();

        $elementVariableAssignmentCall = $this->variableAssignmentCallFactory->createForElement($elementIdentifier);

        return AssertionComparisons::EXISTS === $comparison
            ? $this->assertionCallFactory->createAttributeExistsAssertionCall(
                $elementVariableAssignmentCall,
                $attributeName
            )
            : $this->assertionCallFactory->createAttributeNotExistsAssertionCall(
                $elementVariableAssignmentCall,
                $attributeName
            );
    }

    /**
     * @param ValueInterface $value
     * @param VariablePlaceholder $variablePlaceholder
     * @param string $comparison
     *
     * @return TranspilationResult
     *
     * @throws NonTranspilableModelException
     */
    private function transpileForScalarValue(
        ValueInterface $value,
        VariablePlaceholder $variablePlaceholder,
        string $comparison
    ): TranspilationResult {
        $variableAssignmentCall = $this->variableAssignmentCallFactory->createForScalar($value, $variablePlaceholder);

        return AssertionComparisons::EXISTS === $comparison
            ? $this->assertionCallFactory->createValueExistsAssertionCall(
                $variableAssignmentCall,
                $variablePlaceholder
            )
            : $this->assertionCallFactory->createValueNotExistsAssertionCall(
                $variableAssignmentCall,
                $variablePlaceholder
            );
    }
}
