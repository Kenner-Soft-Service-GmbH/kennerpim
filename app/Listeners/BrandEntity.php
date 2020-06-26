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

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Pim\Services\Product;
use Treo\Core\EventManager\Event;

/**
 * Class BrandEntity
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class BrandEntity extends AbstractEntityListener
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
     * After save action
     *
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        $this->updateProductActivation($event->getArgument('entity'));
    }

    /**
     * Deactivate Product if Brand deactivated
     *
     * @param Entity $entity
     */
    protected function updateProductActivation(Entity $entity)
    {
        if ($entity->isAttributeChanged('isActive') && !$entity->get('isActive')) {
            // prepare condition for Product filter
            $params = [
                'where' => [
                    [
                        'type'      => 'equals',
                        'attribute' => 'brandId',
                        'value'     => $entity->get('id')
                    ],
                    [
                        'type'      => 'isTrue',
                        'attribute' => 'isActive'
                    ]
                ]
            ];

            $this->getProductService()->massUpdate(['isActive' => false], $params);
        }
    }

    /**
     * Create Product service
     *
     * @return Product
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function getProductService(): Product
    {
        return $this->getServiceFactory()->create('Product');
    }
}
