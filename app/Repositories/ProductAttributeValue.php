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
declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;

/**
 * Class ProductAttributeValue
 *
 * @author r.ratsun@treolabs.com
 */
class ProductAttributeValue extends Base
{
    /**
     * @param string $productFamilyAttributeId
     */
    public function removeCollectionByProductFamilyAttribute(string $productFamilyAttributeId)
    {
        $this
            ->where(['productFamilyAttributeId' => $productFamilyAttributeId])
            ->removeCollection(['skipProductAttributeValueHook' => true]);
    }

    /**
     * @param Entity $entity
     * @param array  $options
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        parent::beforeSave($entity, $options);

        // get attribute
        $attribute = $entity->get('attribute');

        // get fields
        $fields = $this->getMetadata()->get(['entityDefs', 'ProductAttributeValue', 'fields'], []);

        if ($attribute->get('type') == 'enum' && !empty($attribute->get('isMultilang')) && $entity->isAttributeChanged('value')) {
            // find key
            $key = array_search($entity->get('value'), $attribute->get('typeValue'));

            foreach ($fields as $mField => $mData) {
                if (isset($mData['multilangField']) && $mData['multilangField'] == 'value') {
                    $data = $attribute->get('type' . ucfirst($mField));
                    if (isset($data[$key])) {
                        $entity->set($mField, $data[$key]);
                    } else {
                        $entity->set($mField, $entity->get('value'));
                    }
                }
            }
        }

        if ($attribute->get('type') == 'multiEnum' && !empty($attribute->get('isMultilang')) && $entity->isAttributeChanged('value')) {
            $values = Json::decode($entity->get('value'), true);

            $keys = [];
            foreach ($values as $value) {
                $keys[] = array_search($value, $attribute->get('typeValue'));
            }

            foreach ($fields as $mField => $mData) {
                if (isset($mData['multilangField']) && $mData['multilangField'] == 'value') {
                    $data = $attribute->get('type' . ucfirst($mField));
                    $values = [];
                    foreach ($keys as $key) {
                        $values[] = isset($data[$key]) ? $data[$key] : null;
                    }
                    $entity->set($mField, Json::encode($values));
                }
            }
        }
    }
}
