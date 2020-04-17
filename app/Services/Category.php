<?php
/**
 * Pim
 * Free Extension
 * Copyright (c) 2020 Kenner Soft Service GmbH
 * Website: https://kennersoft.de
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
 * along with this program. If not, see <https://www.gnu.org/licenses/>.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "KennerPIM"
 * word.
 */

namespace Pim\Services;

use Espo\Core\Exceptions\Forbidden;

/**
 * Service of Category
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class Category extends AbstractService
{
    /**
     * Get category entity
     *
     * @param string $id
     *
     * @return array
     * @throws Forbidden
     */
    public function getEntity($id = null)
    {
        // call parent
        $entity = parent::getEntity($id);

        // set hasChildren param
        $entity->set('hasChildren', $entity->hasChildren());

        return $entity;
    }

    /**
     * Is child category
     *
     * @param string $categoryId
     * @param string $selectedCategoryId
     *
     * @return bool
     */
    public function isChildCategory(string $categoryId, string $selectedCategoryId): bool
    {
        // get category
        if (empty($category = $this->getEntityManager()->getEntity('Category', $selectedCategoryId))) {
            return false;
        }

        return in_array($categoryId, explode("|", (string)$category->get('categoryRoute')));
    }

    /**
     * Get id parent category and ids children category
     *
     * @param string $id
     *
     * @return array
     * @throws \Espo\Core\Exceptions\Error
     */
    public function getIdsTree(string $id): array
    {
        /** @var \Pim\Entities\Category $category */
        $category = $this->getEntityManager()->getEntity('Category', $id);

        $categoriesIds = [];
        $categoriesChild = $category->getChildren()->toArray();

        if (!empty($categoriesChild)) {
            $categoriesChild = $category->getChildren()->toArray();
            $categoriesIds = array_column($categoriesChild, 'id');
        }

        $categoriesIds[] = $category->id;

        return $categoriesIds;
    }

    /**
     * Remove ProductCategory by ID category
     *
     * @param string $categoryId
     */
    public function removeProductCategoryByCategory(string $categoryId): void
    {
        $productsCategory = $this
            ->getEntityManager()
            ->getRepository('ProductCategory')
            ->where(['categoryId' => $categoryId])
            ->find()
            ->toArray();

        $serviceProduct = $this->getServiceFactory()->create('ProductCategory');

        foreach ($productsCategory as $productCategory) {
            $serviceProduct->deleteEntity($productCategory['id']);
        }
    }

    /**
     * @param array $ids
     * @param array $foreignIds
     *
     * @return bool
     */
    public function massRelateProductCategories(array $ids, array $foreignIds): bool
    {
        // prepare productCategory repository
        $repository = $this->getEntityManager()->getRepository('ProductCategory');

        // get exists productCategories
        $productCategories = $repository
            ->select(['productId', 'categoryId'])
            ->where([
                'productId' => $foreignIds,
                'categoryId' => $ids,
                'scope' => 'Global'
            ])
            ->find()
            ->toArray();

        $exists = [];

        // prepare exists
        if (!empty($productCategories)) {
            foreach ($productCategories as $productCategory) {
                if (isset($exists[$productCategory['categoryId']])) {
                    $exists[$productCategory['categoryId']][] = $productCategory['productId'];
                } else {
                    $exists[$productCategory['categoryId']] = [$productCategory['productId']];
                }
            }
        }

        // create ProductCategory entity where needed
        foreach ($ids as $categoryId) {
            foreach ($foreignIds as $productId) {
                if (!isset($exists[$categoryId]) || !in_array($productId, $exists[$categoryId])) {
                    $category = $repository->get();
                    $category->set([
                        'productId' => $productId,
                        'categoryId' => $categoryId,
                        'scope' => 'Global'
                    ]);
                    $this->getEntityManager()->saveEntity($category);
                }
            }
        }

        return true;
    }

    /**
     * @param array $ids
     * @param array $foreignIds
     *
     * @return bool
     */
    public function massUnrelateProductCategories(array $ids, array $foreignIds): bool
    {
        // get exists productCategories
        $productCategories = $this
            ->getEntityManager()
            ->getRepository('ProductCategory')
            ->where([
                'productId' => $foreignIds,
                'categoryId' => $ids,
                'scope' => 'Global'
            ])
            ->find();

        // remove related categories
        if (count($productCategories) > 0) {
            foreach ($productCategories as $productCategory) {
                $this->getEntityManager()->removeEntity($productCategory);
            }
        }

        return true;
    }

    /**
     * After mass delete action
     *
     * @param array $idList
     *
     * @return void
     */
    protected function afterMassRemove(array $idList): void
    {
        foreach ($idList as $id) {
            $this->removeProductCategoryByCategory($id);
        }
        parent::afterMassRemove($idList);
    }
}
