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

use Completeness\Services\ProductCompleteness;
use Espo\Core\Exceptions\Error;
use Espo\ORM\EntityCollection;
use Treo\Composer\PostUpdate;
use Treo\Core\Migration\AbstractMigration;
use Treo\Core\Utils\Auth;
use Completeness\Services\Completeness;
/**
 * Migration class for version 3.12.3
 *
 * @author m.kokhanskyi@treolabs.com
 */
class V3Dot12Dot3 extends AbstractMigration
{
    public const LIMIT = 10000;

    /**
     * @inheritdoc
     * @throws Error
     */
    public function up(): void
    {
        (new Auth($this->getContainer()))->useNoAuth();

        if (class_exists(Completeness::class)
            && method_exists(Completeness::class, 'runUpdateCompleteness')
        ) {
            $hasCompleteness = $this->getContainer()->get('metadata')->get(['scopes', 'Product', 'hasCompleteness'], false);
            if (!empty($hasCompleteness)) {
                $this->recalcEntities('Product');
                if (method_exists(ProductCompleteness::class, 'setHasCompleteness')) {
                    ProductCompleteness::setHasCompleteness($this->getContainer(), 'ProductAttributeValue', true);
                }
            }
        }
    }


    /**
     * @param string $entityName
     */
    protected function recalcEntities(string $entityName): void
    {
        /** @var Completeness $service */
        $service = $this->getContainer()->get('serviceFactory')->create('Completeness');
        $count = $this->getEntityManager()->getRepository($entityName)->count();
        PostUpdate::renderLine('Update complete fields in ' . $entityName);
        if ($count > 0) {
            for ($j = 0; $j <= $count; $j += self::LIMIT) {
                $entities = $this->selectLimitById($entityName, self::LIMIT, $j);
                foreach ($entities as $entity) {
                    $service->runUpdateCompleteness($entity);
                }
            }
        }
    }

    /**
     * @param string $entityName
     *
     * @param int $limit
     * @param int $offset
     * @return EntityCollection
     */
    protected function selectLimitById(string $entityName, $limit = 2000, $offset = 0): EntityCollection
    {
        return $this->getEntityManager()
            ->getRepository($entityName)
            ->limit($offset, $limit)
            ->find();
    }

    /**
     * @inheritdoc
     */
    public function down(): void {}
}
