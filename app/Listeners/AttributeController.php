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

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Treo\Listeners\AbstractListener;
use Treo\Core\EventManager\Event;

/**
 * Class AttributeController
 *
 * @author r.ratsun@treolabs.com
 */
class AttributeController extends AbstractListener
{
    /**
     * @param Event $event
     */
    public function beforeActionDelete(Event $event)
    {
        // get data
        $arguments = $event->getArguments();

        if (empty($arguments['data']->force) && !empty($arguments['params']['id'])) {
            $this->validRelationsWithProduct([$arguments['params']['id']]);
            $this->validRelationsWithProductFamilies([$arguments['params']['id']]);
        }
    }

    /**
     * @param Event $event
     */
    public function beforeActionMassDelete(Event $event)
    {
        // get data
        $data = $event->getArgument('data');

        if (empty($data->force) && !empty($data->ids)) {
            $this->validRelationsWithProduct($data->ids);
            $this->validRelationsWithProductFamilies($data->ids);
        }
    }

    /**
     * @param array $idsAttribute
     *
     * @throws BadRequest
     */
    protected function validRelationsWithProductFamilies(array $idsAttribute): void
    {
        if ($this->hasProductFamilies($idsAttribute)) {
            throw new BadRequest(
                $this->getLanguage()->translate(
                    'Attribute is used in product families. Please, update product families first',
                    'exceptions',
                    'Attribute'
                )
            );
        }
    }

    /**
     * @param array $idsAttribute
     *
     * @throws BadRequest
     */
    protected function validRelationsWithProduct(array $idsAttribute): void
    {
        if ($this->hasProduct($idsAttribute)) {
            throw new BadRequest(
                $this->getLanguage()->translate(
                    'Attribute is used in products. Please, update products first',
                    'exceptions',
                    'Attribute'
                )
            );
        }
    }

    /**
     * Is attribute used in products
     *
     * @param array $idsAttribute
     *
     * @return bool
     */
    protected function hasProduct(array $idsAttribute): bool
    {
        $count = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['attributeId' => $idsAttribute])
            ->count();

        return !empty($count);
    }

    /**
     * Is attribute used in Product Families
     *
     * @param array $idsAttribute
     *
     * @return bool
     */
    protected function hasProductFamilies(array $idsAttribute): bool
    {
        $count = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->where(['attributeId' => $idsAttribute])
            ->count();

        return !empty($count);
    }
}
