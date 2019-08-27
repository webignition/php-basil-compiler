<?php declare(strict_types=1);

namespace webignition\BasilTranspiler\Identifier;

use webignition\BasilModel\Identifier\AttributeIdentifierInterface;
use webignition\BasilTranspiler\Model\TranspilationResult;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\SingleQuotedStringEscaper;
use webignition\BasilTranspiler\TranspilerInterface;

class AttributeIdentifierTranspiler implements TranspilerInterface
{
    const TEMPLATE = '%s->getAttribute(\'%s\')';

    private $elementIdentifierTranspiler;
    private $singleQuotedStringEscaper;

    public function __construct(
        ElementIdentifierTranspiler $elementIdentifierTranspiler,
        SingleQuotedStringEscaper $singleQuotedStringEscaper
    ) {
        $this->elementIdentifierTranspiler = $elementIdentifierTranspiler;
        $this->singleQuotedStringEscaper = $singleQuotedStringEscaper;
    }

    public static function createTranspiler(): AttributeIdentifierTranspiler
    {
        return new AttributeIdentifierTranspiler(
            ElementIdentifierTranspiler::createTranspiler(),
            SingleQuotedStringEscaper::create()
        );
    }

    public function handles(object $model): bool
    {
        if (!$model instanceof AttributeIdentifierInterface) {
            return false;
        }

        return '' !== trim((string) $model->getAttributeName());
    }

    /**
     * @param object $model
     * @param array $variableIdentifiers
     *
     * @return TranspilationResult
     *
     * @throws NonTranspilableModelException
     */
    public function transpile(object $model, array $variableIdentifiers = []): TranspilationResult
    {
        if (!$model instanceof AttributeIdentifierInterface) {
            throw new NonTranspilableModelException($model);
        }

        $attributeName = trim((string) $model->getAttributeName());
        if ('' === $attributeName) {
            throw new NonTranspilableModelException($model);
        }

        $elementIdentifier = $model->getElementIdentifier();
        $elementIdentifierTranspilationResult = $this->elementIdentifierTranspiler->transpile($elementIdentifier);

        $content = sprintf(
            self::TEMPLATE,
            $elementIdentifierTranspilationResult,
            $this->singleQuotedStringEscaper->escape($attributeName)
        );

        return new TranspilationResult($content, $elementIdentifierTranspilationResult->getUseStatements());
    }
}