<?php declare(strict_types=1);

namespace webignition\BasilTranspiler\Action;

use webignition\BasilModel\Action\InputActionInterface;
use webignition\BasilModel\Identifier\DomIdentifierInterface;
use webignition\BasilTranspiler\CallFactory\VariableAssignmentCallFactory;
use webignition\BasilTranspiler\CallFactory\WebDriverElementMutatorCallFactory;
use webignition\BasilTranspiler\Model\Call\VariableAssignmentCall;
use webignition\BasilTranspiler\Model\CompilableSource;
use webignition\BasilTranspiler\Model\CompilableSourceInterface;
use webignition\BasilTranspiler\Model\CompilationMetadata;
use webignition\BasilTranspiler\Model\VariablePlaceholderCollection;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\TranspilerInterface;

class SetActionTranspiler implements TranspilerInterface
{
    private $variableAssignmentCallFactory;
    private $webDriverElementMutatorCallFactory;

    public function __construct(
        VariableAssignmentCallFactory $variableAssignmentCallFactory,
        WebDriverElementMutatorCallFactory $webDriverElementMutatorCallFactory
    ) {
        $this->variableAssignmentCallFactory = $variableAssignmentCallFactory;
        $this->webDriverElementMutatorCallFactory = $webDriverElementMutatorCallFactory;
    }

    public static function createTranspiler(): SetActionTranspiler
    {
        return new SetActionTranspiler(
            VariableAssignmentCallFactory::createFactory(),
            WebDriverElementMutatorCallFactory::createFactory()
        );
    }

    public function handles(object $model): bool
    {
        return $model instanceof InputActionInterface;
    }

    /**
     * @param object $model
     *
     * @return CompilableSourceInterface
     *
     * @throws NonTranspilableModelException
     */
    public function transpile(object $model): CompilableSourceInterface
    {
        if (!$model instanceof InputActionInterface) {
            throw new NonTranspilableModelException($model);
        }

        $identifier = $model->getIdentifier();

        if (!$identifier instanceof DomIdentifierInterface) {
            throw new NonTranspilableModelException($model);
        }

        if (null !== $identifier->getAttributeName()) {
            throw new NonTranspilableModelException($model);
        }

        $variableExports = new VariablePlaceholderCollection();
        $elementLocatorPlaceholder = $variableExports->create('ELEMENT_LOCATOR');
        $collectionPlaceholder = $variableExports->create('COLLECTION');
        $valuePlaceholder = $variableExports->create('VALUE');

        $collectionAssignmentCall = $this->variableAssignmentCallFactory->createForElementCollection(
            $identifier,
            $elementLocatorPlaceholder,
            $collectionPlaceholder
        );

        $valueAssignmentCall = $this->variableAssignmentCallFactory->createForValue(
            $model->getValue(),
            $valuePlaceholder
        );

        $mutationCall = $this->webDriverElementMutatorCallFactory->createSetValueCall(
            $collectionPlaceholder,
            $valuePlaceholder
        );

        $statements = array_merge(
            $collectionAssignmentCall->getStatements(),
            null === $valueAssignmentCall ? [] : $valueAssignmentCall->getStatements(),
            $mutationCall->getStatements()
        );

        $compilationMetadata = (new CompilationMetadata())
            ->merge([
                $collectionAssignmentCall->getCompilationMetadata(),
                $mutationCall->getCompilationMetadata(),
            ]);

        if ($valueAssignmentCall instanceof VariableAssignmentCall) {
            $compilationMetadata = $compilationMetadata->merge([
                $valueAssignmentCall->getCompilationMetadata()
            ]);
        }

        return new CompilableSource($statements, $compilationMetadata);
    }
}
