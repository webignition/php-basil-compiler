<?php declare(strict_types=1);

namespace webignition\BasilTranspiler\Value;

use webignition\BasilModel\Value\ObjectNames;
use webignition\BasilModel\Value\ObjectValueInterface;
use webignition\BasilModel\Value\ValueTypes;
use webignition\BasilTranspiler\Model\VariablePlaceholderCollection;
use webignition\BasilTranspiler\TranspilerInterface;
use webignition\BasilTranspiler\VariableNames;
use webignition\BasilTranspiler\Model\VariablePlaceholder;

class BrowserObjectValueTranspiler extends AbstractObjectValueTranspiler implements TranspilerInterface
{
    const PROPERTY_NAME_SIZE = 'size';

    private $pantherClientVariablePlaceholder;

    public function __construct()
    {
        $this->pantherClientVariablePlaceholder = new VariablePlaceholder(VariableNames::PANTHER_CLIENT);
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
                (string) $this->pantherClientVariablePlaceholder .
                '->getWebDriver()->manage()->window()->getSize()',
        ];
    }

    protected function getVariablePlaceholders(): VariablePlaceholderCollection
    {
        return new VariablePlaceholderCollection([
            $this->pantherClientVariablePlaceholder,
        ]);
    }
}
