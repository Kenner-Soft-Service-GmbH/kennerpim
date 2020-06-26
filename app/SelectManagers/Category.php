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

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class of Category
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Category extends AbstractSelectManager
{
    /**
     * @inheritDoc
     */
    public function applyAdditional(array &$result, array $params)
    {
        // prepare additional select columns
        $additionalSelectColumns = [
            'childrenCount' => '(SELECT COUNT(c1.id) FROM category AS c1 WHERE c1.category_parent_id=category.id AND c1.deleted=0)'
        ];

        // add additional select columns
        foreach ($additionalSelectColumns as $alias => $sql) {
            $result['additionalSelectColumns'][$sql] = $alias;
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterOnlyRootCategory(array &$result)
    {
        if ($this->hasBoolFilter('onlyRootCategory')) {
            $result['whereClause'][] = [
                'categoryParentId' => null
            ];
        }
    }

    /**
     * @param array $result
     *
     * @return mixed
     */
    protected function boolFilterOnlyCatalogCategories(array &$result)
    {
        // get id
        $value = $this->getSelectCondition('onlyCatalogCategories');

        // get catalog
        if (empty($value)) {
            return null;
        }

        // get catalog trees
        $catalogs = $this
            ->getEntityManager()
            ->getRepository('Catalog')
            ->where([
                'id' => $value
            ])
            ->find();

        $catalogsTrees = [];

        if (count($catalogs) > 0) {
            foreach ($catalogs as $catalog) {
                $catalogsTrees = array_merge($catalogsTrees, array_column($catalog->get('categories')->toArray(), 'id'));
            }
        }

        if (!empty($catalogsTrees)) {
            // prepare where
            $where[] = ['id' => $catalogsTrees];
            foreach ($catalogsTrees as $catalogTree) {
                $where[] = ['categoryRoute*' => "%|" . $catalogTree . "|%"];
            }

            $result['whereClause'][] = ['OR' => $where];
        } else {
            $result['whereClause'][] = ['id' => -1];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterNotChildCategory(array &$result)
    {
        // prepare category id
        $categoryId = (string)$this->getSelectCondition('notChildCategory');

        $result['whereClause'][] = [
            'categoryRoute!*' => "%|$categoryId|%"
        ];
    }

    /**
     * @param array $result
     */
    protected function boolFilterNotLinkedProductCategories(array &$result)
    {
        $data = $this->getSelectCondition('notLinkedProductCategories');

        // prepare product categories
        $productCategories = $this
            ->getEntityManager()
            ->getRepository('ProductCategory')
            ->select(['categoryId'])
            ->where(
                [
                    'productId' => $data['productId'],
                    'scope'     => $data['scope']
                ]
            )
            ->find()
            ->toArray();

        if (!empty($productCategories)) {
            $result['whereClause'][] = ['id!=' => array_column($productCategories, 'categoryId')];
        }
    }
}
