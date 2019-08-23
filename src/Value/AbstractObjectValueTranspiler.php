<?php declare(strict_types=1);

namespace webignition\BasilTranspiler\Value;

use webignition\BasilModel\Value\ObjectValueInterface;
use webignition\BasilTranspiler\Model\TranspilationResult;
use webignition\BasilTranspiler\NonTranspilableModelException;
use webignition\BasilTranspiler\TranspilerInterface;
use webignition\BasilTranspiler\UnknownObjectPropertyException;

abstract class AbstractObjectValueTranspiler implements TranspilerInterface
{
    abstract protected function getTranspiledValueMap(array $variableIdentifiers = []): array;

    /**
     * @param object $model
     * @param array $variableIdentifiers
     *
     * @return TranspilationResult
     *
     * @throws NonTranspilableModelException
     * @throws UnknownObjectPropertyException
     */
    public function transpile(object $model, array $variableIdentifiers = []): TranspilationResult
    {
        if ($this->handles($model) && $model instanceof ObjectValueInterface) {
            $transpiledValue = $this->getTranspiledValueMap($variableIdentifiers)[$model->getObjectProperty()] ?? null;

            if (is_string($transpiledValue)) {
                return new TranspilationResult($transpiledValue);
            }

            throw new UnknownObjectPropertyException($model);
        }

        throw new NonTranspilableModelException($model);
    }
}
