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
 * along with this program.  If not, see <https://www.gnu.org/licenses/>.
 *
 * The interactive user interfaces in modified source and object code versions
 * of this program must display Appropriate Legal Notices, as required under
 * Section 5 of the GNU General Public License version 3.
 *
 * In accordance with Section 7(b) of the GNU General Public License version 3,
 * these Appropriate Legal Notices must retain the display of the "KennerPIM"
 * word.
 */

declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\ORM\Entity;

/**
 * Class AttributeGroup
 *
 * @author r.ratsun@treolabs.com
 */
class AttributeGroup extends Base
{
    /**
     * @inheritDoc
     *
     * @throws BadRequest
     */
    public function beforeRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName == 'attributes' && !empty($foreign->get('locale'))) {
            throw new BadRequest("Locale attribute can't be linked");
        }

        parent::beforeRelate($entity, $relationName, $foreign, $data, $options);
    }

    /**
     * @inheritDoc
     *
     * @throws BadRequest
     */
    public function beforeUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName == 'attributes' && !empty($foreign->get('locale'))) {
            throw new BadRequest("Locale attribute can't be unlinked");
        }

        parent::beforeUnrelate($entity, $relationName, $foreign, $options);
    }

    /**
     * @inheritDoc
     */
    public function afterRelate(Entity $entity, $relationName, $foreign, $data = null, array $options = [])
    {
        if ($relationName == 'attributes' && !empty($foreign->get('isMultilang'))) {
            /** @var string $id */
            $id = $entity->get('id');

            /** @var string $parentId */
            $parentId = $foreign->get('id');

            $this->getEntityManager()->nativeQuery("UPDATE attribute SET attribute_group_id='$id' WHERE parent_id='$parentId' AND locale IS NOT NULL");
        }

        parent::afterRelate($entity, $relationName, $foreign, $data, $options);
    }

    /**
     * @inheritDoc
     */
    public function afterUnrelate(Entity $entity, $relationName, $foreign, array $options = [])
    {
        if ($relationName == 'attributes' && !empty($foreign->get('isMultilang'))) {
            /** @var string $parentId */
            $parentId = $foreign->get('id');

            $this->getEntityManager()->nativeQuery("UPDATE attribute SET attribute_group_id=null WHERE parent_id='$parentId' AND locale IS NOT NULL");
        }

        parent::afterUnrelate($entity, $relationName, $foreign, $options);
    }
}
