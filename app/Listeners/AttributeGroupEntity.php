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

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Pim\Entities\Attribute;
use Treo\Core\EventManager\Event;

/**
 * Class AttributeGroupEntity
 *
 * @author r.ratsun@treolabs.com
 */
class AttributeGroupEntity extends AbstractEntityListener
{
    /**
     * Before save action
     *
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        if (!$this->isCodeValid($event->getArgument('entity'))) {
            throw new BadRequest(
                $this->translate(
                    'Code is invalid',
                    'exceptions',
                    'Global'
                )
            );
        }
    }

    /**
     * Before remove action
     *
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeRemove(Event $event)
    {
        if (count($event->getArgument('entity')->get('attributes')) > 0) {
            throw new BadRequest(
                $this->translate(
                    'Attribute group is linked with attribute(s). Please, unlink attribute(s) first',
                    'exceptions',
                    'AttributeGroup'
                )
            );
        }
    }

    /**
     * @param Event $event
     * @throws BadRequest
     */
    public function beforeRelate(Event $event)
    {
        if ($event->getArgument('foreign') instanceof Attribute
                && !empty($event->getArgument('foreign')->get('attributeGroup'))){
            throw new BadRequest(
                $this->translate(
                    'The attribute already has a group',
                    'exceptions',
                    'Attribute'
                )
            );
        }
    }
}
