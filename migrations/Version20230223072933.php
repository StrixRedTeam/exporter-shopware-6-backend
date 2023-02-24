<?php
/**
 * Copyright © Ergonode Sp. z o.o. All rights reserved.
 * See LICENSE.txt for license details.
 */

declare(strict_types=1);

namespace Ergonode\Migration;

use Doctrine\DBAL\Schema\Schema;

/**
 * Auto-generated Ergonode Migration Class:
 */
final class Version20230223072933 extends AbstractErgonodeMigration
{
    public function up(Schema $schema): void
    {
        $this->addSql('ALTER TABLE exporter.shopware6_category DROP CONSTRAINT shopware6_category_pkey;');
        $this->addSql('ALTER TABLE exporter.shopware6_category ADD PRIMARY KEY (channel_id, category_id, category_tree_id);');
    }
}
