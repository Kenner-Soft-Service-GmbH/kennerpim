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

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Treo\Core\EventManager\Event;

/**
 * Class ProductEntity
 *
 * @package Pim\Listeners
 * @author  m.kokhanskyi@treolabs.com
 */
class CategoryEntity extends AbstractEntityListener
{
    /**
     * Get category route
     *
     * @param Entity $entity
     * @param bool   $isName
     *
     * @return string
     */
    public static function getCategoryRoute(Entity $entity, bool $isName = false): string
    {
        // prepare result
        $result = '';

        // prepare data
        $data = [];

        while (!empty($parent = $entity->get('categoryParent'))) {
            // push id
            if (!$isName) {
                $data[] = $parent->get('id');
            } else {
                $data[] = trim($parent->get('name'));
            }

            // to next category
            $entity = $parent;
        }

        if (!empty($data)) {
            if (!$isName) {
                $result = '|' . implode('|', array_reverse($data)) . '|';
            } else {
                $result = implode(' > ', array_reverse($data));
            }
        }

        return $result;
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        // is code valid
        if (!$this->isCodeValid($entity)) {
            throw new BadRequest($this->translate('Code is invalid', 'exceptions', 'Global'));
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('categoryParentId') && count($entity->getTreeProducts()) > 0) {
            throw new BadRequest($this->exception('Category has linked products'));
        }

        if ((count($entity->get('catalogs')) > 0 || !empty($entity->get('catalogsIds')))
            && !empty($entity->get('categoryParent'))) {
            throw new BadRequest($this->translate('Only root category can be linked with catalog', 'exceptions', 'Catalog'));
        }

        if (!empty($parent = $entity->get('categoryParent'))
            && !empty($parent->get('products'))
            && !empty(count($parent->get('products')))) {
            throw new BadRequest(
                $this->translate(
                    'Parent category has products',
                    'exceptions',
                    'Category'
                )
            );
        }
    }

    /**
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        // build tree
        $this->updateCategoryTree($entity);

        // activate parents
        $this->activateParents($entity);

        // deactivate children
        $this->deactivateChildren($entity);
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRemove(Event $event)
    {
        if (count($event->getArgument('entity')->get('categories')) > 0) {
            throw new BadRequest($this->exception("Category has child category and can't be deleted"));
        }
    }

    /**
     * @param Event $event
     */
    public function afterRemove(Event $event)
    {
        $this
            ->getService('Category')
            ->removeProductCategoryByCategory($event->getArgument('entity')->id);
    }

    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRelate(Event $event)
    {
        if ($event->getArgument('relationName') == 'catalogs'
            && !empty($event->getArgument('entity')->get('categoryParent'))) {
            throw new BadRequest($this->translate('Only root category can be linked with catalog', 'exceptions', 'Catalog'));
        }
    }

    /**
     * @inheritdoc
     */
    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions', 'Category');
    }

    /**
     * Update category tree
     *
     * @param Entity $entity
     */
    protected function updateCategoryTree(Entity $entity)
    {
        // is has changes
        if ((empty($entity->categoryListener)
            && ($entity->isAttributeChanged('categoryParentId')
                || $entity->isNew()
                || $entity->isAttributeChanged('name')))) {
            // set route for current category
            $entity->set('categoryRoute', self::getCategoryRoute($entity));
            $entity->set('categoryRouteName', self::getCategoryRoute($entity, true));

            $this->saveEntity($entity);

            // update all children
            if (!$entity->isNew()) {
                $children = $this->getEntityChildren($entity->get('categories'), []);
                foreach ($children as $child) {
                    // set route for child category
                    $child->set('categoryRoute', self::getCategoryRoute($child));
                    $child->set('categoryRouteName', self::getCategoryRoute($child, true));
                    $this->saveEntity($child);
                }
            }
        }
    }

    /**
     * Activate parents categories if it needs
     *
     * @param Entity $entity
     */
    protected function activateParents(Entity $entity)
    {
        // is activate action
        $isActivate = $entity->isAttributeChanged('isActive') && $entity->get('isActive');

        if (empty($entity->categoryListener) && $isActivate && !$entity->isNew()) {
            // update all parents
            foreach ($this->getEntityParents($entity, []) as $parent) {
                $parent->set('isActive', true);
                $this->saveEntity($parent);
            }
        }
    }

    /**
     * Deactivate children categories if it needs
     *
     * @param Entity $entity
     */
    protected function deactivateChildren(Entity $entity)
    {
        // is deactivate action
        $isDeactivate = $entity->isAttributeChanged('isActive') && !$entity->get('isActive');

        if (empty($entity->categoryListener) && $isDeactivate && !$entity->isNew()) {
            // update all children
            $children = $this->getEntityChildren($entity->get('categories'), []);
            foreach ($children as $child) {
                $child->set('isActive', false);
                $this->saveEntity($child);
            }
        }
    }

    /**
     * Save entity
     *
     * @param Entity $entity
     */
    protected function saveEntity(Entity $entity)
    {
        // set flag
        $entity->categoryListener = true;

        $this
            ->getEntityManager()
            ->saveEntity($entity);
    }

    /**
     * Get entity parents
     *
     * @param Entity $category
     * @param array  $parents
     *
     * @return array
     */
    protected function getEntityParents(Entity $category, array $parents): array
    {
        $parent = $category->get('categoryParent');
        if (!empty($parent)) {
            $parents[] = $parent;
            $parents = $this->getEntityParents($parent, $parents);
        }

        return $parents;
    }

    /**
     * Get all children by recursive
     *
     * @param array $entities
     * @param array $children
     *
     * @return array
     */
    protected function getEntityChildren($entities, array $children)
    {
        if (!empty($entities)) {
            foreach ($entities as $entity) {
                $children[] = $entity;
            }
            foreach ($entities as $entity) {
                $children = $this->getEntityChildren($entity->get('categories'), $children);
            }
        }

        return $children;
    }
}
