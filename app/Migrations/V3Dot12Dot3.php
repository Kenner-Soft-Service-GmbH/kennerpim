<?php
/**
 * Pim
 * Free Extension
 * Copyright (c) TreoLabs GmbH
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
//        (new Auth($this->getContainer()))->useNoAuth();
//
//        if (class_exists(Completeness::class)
//            && method_exists(Completeness::class, 'runUpdateCompleteness')
//        ) {
//            $hasCompleteness = $this->getContainer()->get('metadata')->get(['scopes', 'Product', 'hasCompleteness'], false);
//            if (!empty($hasCompleteness)) {
//                $this->recalcEntities('Product');
//                if (method_exists(ProductCompleteness::class, 'setHasCompleteness')) {
//                    ProductCompleteness::setHasCompleteness($this->getContainer(), 'ProductAttributeValue', true);
//                }
//            }
//        }
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
