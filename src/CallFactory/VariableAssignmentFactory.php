<?php declare(strict_types=1);

namespace webignition\BasilTranspiler\CallFactory;

use webignition\BasilCompilationSource\CompilableSource;
use webignition\BasilCompilationSource\CompilableSourceInterface;
use webignition\BasilCompilationSource\CompilationMetadata;
use webignition\BasilCompilationSource\VariablePlaceholder;
use webignition\BasilCompilationSource\VariablePlaceholderCollection;
use webignition\BasilModel\Identifier\DomIdentifierInterface;
use webignition\BasilModel\Value\DomIdentifierValueInterface;
use webignition\BasilModel\Value\LiteralValueInterface;
use webignition\BasilModel\Value\ObjectValueType;
use webignition\BasilModel\Value\ValueInterface;
use webignition\BasilTranspiler\Model\VariableAssignment;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\NonTranspilableValueException;
use webignition\BasilTranspiler\ObjectValueTypeExaminer;
use webignition\BasilTranspiler\SingleQuotedStringEscaper;
use webignition\BasilTranspiler\Value\ValueTranspiler;

class VariableAssignmentFactory
{
    const DEFAULT_ELEMENT_LOCATOR_PLACEHOLDER_NAME = 'ELEMENT_LOCATOR';
    const DEFAULT_ELEMENT_PLACEHOLDER_NAME = 'ELEMENT';

    private $assertionCallFactory;
    private $elementLocatorCallFactory;
    private $domCrawlerNavigatorCallFactory;
    private $valueTranspiler;
    private $singleQuotedStringEscaper;
    private $webDriverElementInspectorCallFactory;
    private $objectValueTypeExaminer;

    public function __construct(
        AssertionCallFactory $assertionCallFactory,
        ElementLocatorCallFactory $elementLocatorCallFactory,
        DomCrawlerNavigatorCallFactory $domCrawlerNavigatorCallFactory,
        ValueTranspiler $valueTranspiler,
        SingleQuotedStringEscaper $singleQuotedStringEscaper,
        WebDriverElementInspectorCallFactory $webDriverElementInspectorCallFactory,
        ObjectValueTypeExaminer $objectValueTypeExaminer
    ) {
        $this->assertionCallFactory = $assertionCallFactory;
        $this->elementLocatorCallFactory = $elementLocatorCallFactory;
        $this->domCrawlerNavigatorCallFactory = $domCrawlerNavigatorCallFactory;
        $this->valueTranspiler = $valueTranspiler;
        $this->singleQuotedStringEscaper = $singleQuotedStringEscaper;
        $this->webDriverElementInspectorCallFactory = $webDriverElementInspectorCallFactory;
        $this->objectValueTypeExaminer = $objectValueTypeExaminer;
    }

    public static function createFactory(): VariableAssignmentFactory
    {
        return new VariableAssignmentFactory(
            AssertionCallFactory::createFactory(),
            ElementLocatorCallFactory::createFactory(),
            DomCrawlerNavigatorCallFactory::createFactory(),
            ValueTranspiler::createTranspiler(),
            SingleQuotedStringEscaper::create(),
            WebDriverElementInspectorCallFactory::createFactory(),
            ObjectValueTypeExaminer::createExaminer()
        );
    }

    /**
     * @param DomIdentifierInterface $elementIdentifier
     * @param VariablePlaceholder $elementLocatorPlaceholder
     * @param VariablePlaceholder $elementPlaceholder
     *
     * @return CompilableSourceInterface
     */
    public function createForElement(
        DomIdentifierInterface $elementIdentifier,
        VariablePlaceholder $elementLocatorPlaceholder,
        VariablePlaceholder $elementPlaceholder
    ) {
        $argumentsVariableExports = new VariablePlaceholderCollection([
            $elementLocatorPlaceholder,
        ]);

        $arguments = (new CompilableSource())
            ->withStatements([(string) $elementLocatorPlaceholder])
            ->withCompilationMetadata((new CompilationMetadata())->withVariableExports($argumentsVariableExports));

        $hasCall = $this->domCrawlerNavigatorCallFactory->createHasOneCallForTranspiledArguments($arguments);
        $findCall = $this->domCrawlerNavigatorCallFactory->createFindOneCallForTranspiledArguments($arguments);

        return $this->createForElementOrCollection(
            $elementIdentifier,
            $elementLocatorPlaceholder,
            $elementPlaceholder,
            $hasCall,
            $findCall
        );
    }

    /**
     * @param ValueInterface $value
     * @param VariablePlaceholder $placeholder
     * @param string $type
     * @param string $default
     *
     * @return CompilableSourceInterface
     *
     * @throws NonTranspilableModelException
     * @throws NonTranspilableValueException
     */
    public function createForValue(
        ValueInterface $value,
        VariablePlaceholder $placeholder,
        string $type = 'string',
        string $default = 'null'
    ): VariableAssignment {
        $assignment = null;

        $isOfScalarObjectType = $this->objectValueTypeExaminer->isOfType($value, [
            ObjectValueType::BROWSER_PROPERTY,
            ObjectValueType::ENVIRONMENT_PARAMETER,
            ObjectValueType::PAGE_PROPERTY,
        ]);

        $isScalarValue = $value instanceof LiteralValueInterface || $isOfScalarObjectType;

        if ($isScalarValue) {
            $assignment = $this->createForScalar($value, $placeholder, $default);
        }

        if (null === $assignment && $value instanceof DomIdentifierValueInterface) {
            $identifier = $value->getIdentifier();

            if (null === $identifier->getAttributeName()) {
                $assignment = $this->createForElementCollectionValue($identifier, $placeholder);
            } else {
                $assignment = $this->createForAttribute(
                    $identifier,
                    $this->createElementLocatorPlaceholder(),
                    $this->createElementPlaceholder(),
                    $placeholder
                );
            }
        }

        if ($assignment instanceof VariableAssignment) {
            $variableAssignmentCallPlaceholder = $assignment->getVariablePlaceholder();

            $assignment = (new VariableAssignment($variableAssignmentCallPlaceholder))
                ->withPredecessors([$assignment])
                ->withStatements([
                    sprintf('(%s) %s', $type, (string) $variableAssignmentCallPlaceholder)
                ]);
        }

        if (null === $assignment) {
            throw new NonTranspilableValueException($value);
        }

        return $assignment;
    }

    /**
     * @param ValueInterface $value
     * @param VariablePlaceholder $placeholder
     *
     * @return CompilableSourceInterface
     *
     * @throws NonTranspilableModelException
     * @throws NonTranspilableValueException
     */
    public function createForValueExistence(
        ValueInterface $value,
        VariablePlaceholder $placeholder
    ): VariableAssignment {
        $isScalarValue = $this->objectValueTypeExaminer->isOfType($value, [
            ObjectValueType::BROWSER_PROPERTY,
            ObjectValueType::ENVIRONMENT_PARAMETER,
            ObjectValueType::PAGE_PROPERTY,
        ]);

        if ($isScalarValue) {
            return $this->createForScalarExistence(
                $value,
                $placeholder
            );
        }

        if ($value instanceof DomIdentifierValueInterface) {
            $identifier = $value->getIdentifier();

            return null === $identifier->getAttributeName()
                ? $this->createForElementExistence($identifier, $placeholder)
                : $this->createForAttributeExistence($identifier, $placeholder);
        }

        throw new NonTranspilableValueException($value);
    }

    /**
     * @param DomIdentifierInterface $elementIdentifier
     * @param VariablePlaceholder $elementLocatorPlaceholder
     * @param VariablePlaceholder $collectionPlaceholder
     *
     * @return CompilableSourceInterface
     */
    public function createForElementCollection(
        DomIdentifierInterface $elementIdentifier,
        VariablePlaceholder $elementLocatorPlaceholder,
        VariablePlaceholder $collectionPlaceholder
    ) {
        $argumentsVariableExports = new VariablePlaceholderCollection([$elementLocatorPlaceholder]);
        $argumentsCompilationMetadata = (new CompilationMetadata())->withVariableExports($argumentsVariableExports);

        $arguments = (new CompilableSource())
            ->withStatements([(string) $elementLocatorPlaceholder])
            ->withCompilationMetadata($argumentsCompilationMetadata);

        $hasCall = $this->domCrawlerNavigatorCallFactory->createHasCallForTranspiledArguments($arguments);
        $findCall = $this->domCrawlerNavigatorCallFactory->createFindCallForTranspiledArguments($arguments);

        return $this->createForElementOrCollection(
            $elementIdentifier,
            $elementLocatorPlaceholder,
            $collectionPlaceholder,
            $hasCall,
            $findCall
        );
    }

    /**
     * @param DomIdentifierInterface $elementIdentifier
     * @param VariablePlaceholder $elementPlaceholder
     *
     * @return VariableAssignment
     */
    private function createForElementExistence(
        DomIdentifierInterface $elementIdentifier,
        VariablePlaceholder $elementPlaceholder
    ): CompilableSourceInterface {
        $hasCall = $this->domCrawlerNavigatorCallFactory->createHasCallForIdentifier($elementIdentifier);

        $variableExports = new VariablePlaceholderCollection([
            $elementPlaceholder,
        ]);

        $compilationMetadata = (new CompilationMetadata())
            ->merge([$hasCall->getCompilationMetadata()])
            ->withAdditionalVariableExports($variableExports);

        return (new VariableAssignment($elementPlaceholder))
            ->withStatements([
                (string) $hasCall,
            ])
            ->withCompilationMetadata($compilationMetadata);
    }

    /**
     * @param DomIdentifierInterface $identifier
     * @param VariablePlaceholder $elementLocatorPlaceholder
     * @param VariablePlaceholder $elementPlaceholder
     * @param VariablePlaceholder $attributePlaceholder
     *
     * @return VariableAssignment
     */
    private function createForAttribute(
        DomIdentifierInterface $identifier,
        VariablePlaceholder $elementLocatorPlaceholder,
        VariablePlaceholder $elementPlaceholder,
        VariablePlaceholder $attributePlaceholder
    ): CompilableSourceInterface {
        $elementAssignment = $this->createForElement($identifier, $elementLocatorPlaceholder, $elementPlaceholder);

        return (new VariableAssignment($attributePlaceholder))
            ->withPredecessors([$elementAssignment])
            ->withStatements([
                sprintf(
                    '%s->getAttribute(\'%s\')',
                    $elementAssignment->getVariablePlaceholder(),
                    $this->singleQuotedStringEscaper->escape((string) $identifier->getAttributeName())
                ),
            ]);
    }

    /**
     * @param DomIdentifierInterface $elementIdentifier
     * @param VariablePlaceholder $valuePlaceholder
     *
     * @return VariableAssignment
     */
    private function createForElementCollectionValue(
        DomIdentifierInterface $elementIdentifier,
        VariablePlaceholder $valuePlaceholder
    ): CompilableSourceInterface {
        $collectionAssignment = $this->createForElementCollection(
            $elementIdentifier,
            $this->createElementLocatorPlaceholder(),
            $valuePlaceholder
        );

        $collectionPlaceholder = $collectionAssignment->getVariablePlaceholder();

        $getValueAssignment = $this->webDriverElementInspectorCallFactory->createGetValueCall($collectionPlaceholder);

        return (new VariableAssignment($valuePlaceholder))
            ->withPredecessors([$collectionAssignment, $getValueAssignment]);
    }

    /**
     * @param DomIdentifierInterface $attributeIdentifier
     * @param VariablePlaceholder $valuePlaceholder
     *
     * @return VariableAssignment
     */
    private function createForAttributeExistence(
        DomIdentifierInterface $attributeIdentifier,
        VariablePlaceholder $valuePlaceholder
    ): CompilableSourceInterface {
        $assignment = $this->createForAttribute(
            $attributeIdentifier,
            $this->createElementLocatorPlaceholder(),
            $this->createElementPlaceholder(),
            $valuePlaceholder
        );

        return (new VariableAssignment($valuePlaceholder))
            ->withPredecessors([$assignment])
            ->withStatements([
                $valuePlaceholder . ' !== null'
            ]);
    }


    /**
     * @param DomIdentifierInterface $elementIdentifier
     * @param VariablePlaceholder $elementLocatorPlaceholder
     * @param VariablePlaceholder $returnValuePlaceholder
     * @param CompilableSourceInterface $hasCall
     * @param CompilableSourceInterface $findCall
     *
     * @return CompilableSourceInterface
     */
    private function createForElementOrCollection(
        DomIdentifierInterface $elementIdentifier,
        VariablePlaceholder $elementLocatorPlaceholder,
        VariablePlaceholder $returnValuePlaceholder,
        CompilableSourceInterface $hasCall,
        CompilableSourceInterface $findCall
    ) {
        $variableExports = new VariablePlaceholderCollection();
        $variableExports = $variableExports->withAdditionalItems([
            $elementLocatorPlaceholder,
            $returnValuePlaceholder,
        ]);

        $elementLocatorAssignment = VariableAssignment::fromCompilableSource(
            $this->elementLocatorCallFactory->createConstructorCall($elementIdentifier),
            $elementLocatorPlaceholder
        );

        $hasAssignment = VariableAssignment::fromCompilableSource(
            $hasCall,
            $variableExports->create('HAS')
        );

        $elementExistsAssertion = $this->assertionCallFactory->createValueIsTrueAssertionCall($hasAssignment);

        return (new VariableAssignment($returnValuePlaceholder))
            ->withPredecessors([
                $elementLocatorAssignment,
                $elementExistsAssertion,
                $findCall,
            ]);
    }

    /**
     * @param ValueInterface $value
     * @param VariablePlaceholder $variablePlaceholder
     * @param string $default
     *
     * @return CompilableSourceInterface
     *
     * @throws NonTranspilableModelException
     */
    private function createForScalar(
        ValueInterface $value,
        VariablePlaceholder $variablePlaceholder,
        string $default = 'null'
    ): CompilableSourceInterface {
        $accessCall = $this->valueTranspiler->transpile($value);
        $accessCall->appendStatement(0, ' ?? ' . $default);

        return (new VariableAssignment($variablePlaceholder))
            ->withPredecessors([$accessCall]);
    }

    /**
     * @param ValueInterface $value
     * @param VariablePlaceholder $variablePlaceholder
     *
     * @return CompilableSourceInterface
     *
     * @throws NonTranspilableModelException
     */
    private function createForScalarExistence(ValueInterface $value, VariablePlaceholder $variablePlaceholder)
    {
        $assignment = $this->createForScalar($value, $variablePlaceholder);

        return (new VariableAssignment($variablePlaceholder))
            ->withStatements([$variablePlaceholder . ' !== null'])
            ->withPredecessors([$assignment]);
    }

    private function createElementLocatorPlaceholder(): VariablePlaceholder
    {
        return new VariablePlaceholder(self::DEFAULT_ELEMENT_LOCATOR_PLACEHOLDER_NAME);
    }

    private function createElementPlaceholder(): VariablePlaceholder
    {
        return new VariablePlaceholder(self::DEFAULT_ELEMENT_PLACEHOLDER_NAME);
    }
}
