<?php declare(strict_types=1);

namespace webignition\BasilTranspiler\Value;

use webignition\BasilModel\Value\ValueInterface;
use webignition\BasilTranspiler\TranspilerInterface;
use webignition\BasilTranspiler\UnknownValueTypeException;

class ValueTranspiler implements TranspilerInterface
{
    /**
     * @var TranspilerInterface[]
     */
    private $valueTypeTranspilers = [];

    public function __construct(array $valueTypeTranspilers = [])
    {
        foreach ($valueTypeTranspilers as $valueTypeTranspiler) {
            if ($valueTypeTranspiler instanceof TranspilerInterface) {
                $this->addValueTypeTranspiler($valueTypeTranspiler);
            }
        }
    }

    public static function createTranspiler(): ValueTranspiler
    {
        return new ValueTranspiler([
            LiteralValueTranspiler::createTranspiler(),
            BrowserObjectValueTranspiler::createTranspiler(),
            PageObjectValueTranspiler::createTranspiler(),
            EnvironmentParameterValueTranspiler::createTranspiler(),
        ]);
    }

    public function addValueTypeTranspiler(TranspilerInterface $transpiler)
    {
        $this->valueTypeTranspilers[] = $transpiler;
    }

    public function handles(object $model): bool
    {
        if ($model instanceof ValueInterface) {
            return null !== $this->findValueTypeTranspiler($model);
        }

        return false;
    }

    /**
     * @param object $model
     *
     * @return string|null
     *
     * @throws UnknownValueTypeException
     */
    public function transpile(object $model): ?string
    {
        if ($model instanceof ValueInterface) {
            $valueTypeTranspiler = $this->findValueTypeTranspiler($model);

            if ($valueTypeTranspiler instanceof TranspilerInterface) {
                $transpiledValue = $valueTypeTranspiler->transpile($model);

                if (is_string($transpiledValue)) {
                    return $transpiledValue;
                }
            }

            throw new UnknownValueTypeException($model);
        }

        return null;
    }

    private function findValueTypeTranspiler(ValueInterface $value): ?TranspilerInterface
    {
        foreach ($this->valueTypeTranspilers as $valueTypeTranspiler) {
            if ($valueTypeTranspiler->handles($value)) {
                return $valueTypeTranspiler;
            }
        }

        return null;
    }
}