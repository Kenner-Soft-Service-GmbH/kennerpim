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

namespace Pim\SelectManagers;

use Pim\Core\SelectManagers\AbstractSelectManager;

/**
 * Class of AttributeGroup
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class AttributeGroup extends AbstractSelectManager
{

    /**
     * @param array $result
     */
    protected function boolFilterWithNotLinkedAttributesToProduct(array &$result)
    {
        // get product attributes
        $productAttributes = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->select(['attributeId'])
            ->where([
                'productId' => (string)$this->getSelectCondition('withNotLinkedAttributesToProduct'),
                'scope' => 'Global'
            ])
            ->find()
            ->toArray();

        if (count($productAttributes) > 0) {
            $result['whereClause'][] = [
                'id' => $this->getNotLinkedAttributeGroups($productAttributes)
            ];
        }
    }

    /**
     * @param array $result
     */
    protected function boolFilterWithNotLinkedAttributesToProductFamily(array &$result)
    {
        // get product family attributes
        $productFamilyAttributes = $this
            ->getEntityManager()
            ->getRepository('ProductFamilyAttribute')
            ->select(['attributeId'])
            ->where([
                'productFamilyId' => (string)$this->getSelectCondition('withNotLinkedAttributesToProductFamily'),
                'scope' => 'Global'
            ])
            ->find()
            ->toArray();

        if (count($productFamilyAttributes) > 0) {
            $result['whereClause'][] = [
                'id' => $this->getNotLinkedAttributeGroups($productFamilyAttributes)
            ];
        }
    }

    /**
     * Get attributeGroups with not linked all related attributes to product or productFamily
     *
     * @param array $attributes
     *
     * @return array
     */
    protected function getNotLinkedAttributeGroups(array $attributes): array
    {
        // prepare result
        $result = [];

        // get all attribute groups
        $attributeGroups = $this
            ->getEntityManager()
            ->getRepository('AttributeGroup')
            ->select(['id'])
            ->find();

        foreach ($attributeGroups as $attributeGroup) {
            $attr = $attributeGroup->get('attributes')->toArray();

            if (!empty(array_diff(
                array_column($attr, 'id'),
                array_column($attributes, 'attributeId')
            ))) {
                $result[] = $attributeGroup->get('id');
            }
        }

        return $result;
    }
}
