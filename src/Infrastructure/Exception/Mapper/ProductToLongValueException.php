<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\ExporterShopware6\Infrastructure\Exception\Mapper;

use Ergonode\Attribute\Domain\ValueObject\AttributeCode;
use Ergonode\Core\Domain\ValueObject\Language;
use Ergonode\ExporterShopware6\Infrastructure\Exception\Shopware6ExporterException;
use Ergonode\Product\Domain\ValueObject\Sku;

class ProductToLongValueException extends Shopware6ExporterException
{
    private const MESSAGE = 'Attribute {code} is too long max {length}, required for product {sku} language {language}';

    public function __construct(AttributeCode $code, Sku $sku, int $length, Language $language, \Throwable $previous = null)
    {
        parent::__construct(
            self::MESSAGE,
            [
                '{code}' => $code->getValue(),
                '{sku}' => $sku->getValue(),
                '{length}' => $length,
                '{language}' => $language->getCode()
            ],
            $previous
        );
    }
}
