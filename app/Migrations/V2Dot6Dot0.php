<?php
/**
 * Pim
 * Free Extension
 * Copyright (c) TreoLabs GmbH
 * Copyright (c) Kenner Soft Service GmbH
 *
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\AbstractMigration;

/**
 * Migration class for version 2.6.0
 *
 * @author r.ratsun@treolabs.com
 */
class V2Dot6Dot0 extends AbstractMigration
{
    /**
     * Up to current
     */
    public function up(): void
    {
        // drop old trigger
        $sth = $this
            ->getEntityManager()
            ->getPDO()
            ->prepare("DROP TRIGGER IF EXISTS trigger_before_insert_product_attribute_value");
        $sth->execute();
    }
}
