<?php
/**
 * This file is part of EspoCRM and/or TreoCore.
 *
 * EspoCRM - Open Source CRM application.
 * Copyright (C) 2014-2019 Yuri Kuznetsov, Taras Machyshyn, Oleksiy Avramenko
 * Website: http://www.espocrm.com
 *
 * TreoCore is EspoCRM-based Open Source application.
 * Copyright (C) 2017-2019 TreoLabs GmbH
 * Website: https://treolabs.com
 *
 * KennerPIM is Pim-based Open Source application.
 * Copyright (C) 2020 KenerSoft Service GmbH
 * Website: https://kennersoft.de
 *
 * TreoCore as well as EspoCRM is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * TreoCore as well as EspoCRM is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with EspoCRM. If not, see http://www.gnu.org/licenses/.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "EspoCRM" word
 * and "TreoCore" word.
 */

declare(strict_types=1);

namespace Pim\Migrations;

use Treo\Core\Migration\AbstractMigration;

/**
 * Migration class for version 3.10.1
 *
 * @author r.ratsun@treolabs.com
 */
class V3Dot10Dot1 extends AbstractMigration
{
    /**
     * @inheritdoc
     */
    public function up(): void
    {
        // migrate bundleProduct
        try {
            $this->execute("UPDATE product SET type='simpleProduct' WHERE type='bundleProduct'");
            $this->execute("DROP TABLE product_type_bundle");
        } catch (\PDOException $e) {
        }

        // migrate packageProduct
        try {
            $this->execute("UPDATE product AS p1 SET p1.measuring_unit_id=(SELECT measuring_unit_id FROM product_type_package WHERE package_product_id=p1.id)");
            $this->execute("UPDATE product SET type='simpleProduct' WHERE type='packageProduct'");
            $this->execute("DROP TABLE product_type_package");
        } catch (\PDOException $e) {
        }
    }

    /**
     * @inheritdoc
     */
    public function down(): void
    {
    }

    /**
     * @param string $sql
     *
     * @return mixed
     */
    private function execute(string $sql)
    {
        $sth = $this
            ->getEntityManager()
            ->getPDO()
            ->prepare($sql);
        $sth->execute();

        return $sth;
    }
}
