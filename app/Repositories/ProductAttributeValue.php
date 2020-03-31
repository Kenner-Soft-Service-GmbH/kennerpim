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
declare(strict_types=1);

namespace Pim\Repositories;

use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Templates\Repositories\Base;
use Espo\Core\Utils\Json;
use Espo\ORM\Entity;
use Treo\Core\Utils\Util;

/**
 * Class ProductAttributeValue
 *
 * @author r.ratsun@treolabs.com
 */
class ProductAttributeValue extends Base
{
    /**
     * @param string $id
     */
    public function removeCollectionByProductFamilyAttribute(string $id)
    {
        $this
            ->where(['productFamilyAttributeId' => $id])
            ->removeCollection(['skipProductAttributeValueHook' => true]);
    }

    /**
     * @inheritDoc
     *
     * @throws BadRequest
     */
    public function beforeSave(Entity $entity, array $options = [])
    {
        if (empty($options['skipValidation'])) {
            if ($entity->isNew() && !empty($entity->get('attribute')->get('locale'))) {
                throw new BadRequest("Locale attribute can't be linked");
            }
            if ($entity->get('attributeType') == 'enum' && !empty($entity->get('locale')) && $entity->isAttributeChanged('value')) {
                throw new BadRequest("Locale enum attribute can't be changed");
            }
            if ($entity->get('attributeType') == 'multiEnum' && !empty($entity->get('locale')) && $entity->isAttributeChanged('value')) {
                throw new BadRequest("Locale multiEnum attribute can't be changed");
            }
            if (!$entity->isNew() && !empty($entity->get('locale')) && ($entity->isAttributeChanged('scope') || !empty($entity->get('channelsIds')))) {
                throw new BadRequest("Locale attribute scope can't be changed");
            }
        }

        if ($entity->isNew()) {
            $entity->set('attributeType', $entity->get('attribute')->get('type'));
        }

        parent::beforeSave($entity, $options);
    }

    /**
     * @inheritDoc
     */
    public function afterSave(Entity $entity, array $options = [])
    {
        // update locales attributes
        $this->updateLocaleAttributes($entity);

        parent::afterSave($entity, $options);
    }

    /**
     * @inheritDoc
     *
     * @throws BadRequest
     */
    public function beforeRemove(Entity $entity, array $options = [])
    {
        if (empty($options['skipProductAttributeValueHook']) && !empty($entity->get('locale'))) {
            throw new BadRequest("Locale attribute can't be deleted");
        }

        parent::beforeRemove($entity, $options);
    }

    /**
     * @inheritDoc
     */
    public function afterRemove(Entity $entity, array $options = [])
    {
        $this->deleteLocaleAttributes($entity);

        parent::afterRemove($entity, $options);
    }

    /**
     * @param Entity $productFamilyAttribute
     * @param Entity $product
     *
     * @return string|null
     */
    public function getLocaleParentId(Entity $productFamilyAttribute, Entity $product): ?string
    {
        $localeParentId = null;
        if (!empty($productFamilyAttribute->get('locale'))) {
            $localeParentId = $this
                ->getEntityManager()
                ->getRepository('ProductAttributeValue')
                ->select(['id'])
                ->where(
                    [
                        'productFamilyAttributeId' => $productFamilyAttribute->get('localeParentId'),
                        'productId'                => $product->get('id'),
                        'locale'                   => null
                    ]
                )
                ->findOne();

            if (!empty($localeParentId)) {
                $localeParentId = (string)$localeParentId->get('id');
            }
        }

        return $localeParentId;
    }

    /**
     * @param Entity $entity
     */
    protected function updateLocaleAttributes(Entity $entity): void
    {
        if ($entity->isNew() && empty($entity->get('productFamilyAttributeId')) && empty($entity->get('locale'))) {
            $localeAttributes = $entity->get('attribute')->get('attributes');
            if (count($localeAttributes) > 0) {
                foreach ($localeAttributes as $localeAttribute) {
                    $newEntity = $this->get();
                    $newEntity->set($entity->toArray());
                    $newEntity->id = Util::generateId();
                    $newEntity->set('attributeId', $localeAttribute->get('id'));
                    $newEntity->set('locale', $localeAttribute->get('locale'));
                    $newEntity->set('localeParentId', $entity->get('id'));
                    $this->getEntityManager()->saveEntity($newEntity, ['skipValidation' => true]);

                    if ($entity->get('scope') == 'Channel') {
                        $channels = $entity->get('channels');
                        if (count($channels) > 0) {
                            foreach ($channels as $channel) {
                                $this->relate($newEntity, 'channels', $channel);
                            }
                        }
                    }
                }
            }
        }

        if (!$entity->isNew() && !empty($entity->get('attribute')->get('isMultilang')) && ($entity->isAttributeChanged('scope') || $entity->isAttributeChanged('channelsIds'))) {
            /** @var \Pim\Entities\ProductAttributeValue[] $children */
            $children = $entity->get('localeChildren');
            if (count($children) > 0) {
                foreach ($children as $child) {
                    $child->set('scope', $entity->get('scope'));
                    $child->set('channelsIds', $entity->get('channelsIds'));
                    $this->getEntityManager()->saveEntity($child, ['skipValidation' => true]);
                }
            }
        }

        if ($entity->isAttributeChanged('value') && $entity->get('attribute')->get('isMultilang')) {
            // update locales enum fields
            if ($entity->get('attributeType') == 'enum') {
                $this->updateLocalesEnum($entity);
            }

            // update locales multiEnum fields
            if ($entity->get('attributeType') == 'multiEnum') {
                $this->updateLocalesMultiEnum($entity);
            }
        }
    }

    /**
     * @param Entity $entity
     */
    protected function deleteLocaleAttributes(Entity $entity): void
    {
        $this
            ->getEntityManager()
            ->nativeQuery("UPDATE product_attribute_value SET deleted=1 WHERE locale_parent_id=:id", ['id' => $entity->get('id')]);
    }

    /**
     * @param Entity $entity
     */
    protected function updateLocalesEnum(Entity $entity): void
    {
        if (!empty($attribute = $entity->get('attribute')) && !empty($localeAttributes = $attribute->get('attributes')->toArray())) {
            /** @var int $key */
            $key = array_search($entity->get('value'), $attribute->get('typeValue'));

            if (is_int($key) || $entity->get('value') == '') {
                foreach ($localeAttributes as $localeAttribute) {
                    if ($entity->get('value') == '') {
                        $value = $entity->get('value');
                    } elseif (isset($localeAttribute['typeValue'][$key])) {
                        $value = $localeAttribute['typeValue'][$key];
                    }

                    if (isset($value)) {
                        $pav = $this
                            ->getEntityManager()
                            ->getRepository('ProductAttributeValue')
                            ->where(['attributeId' => $localeAttribute['id'], 'localeParentId' => $entity->get('id')])
                            ->findOne();

                        if (!empty($pav)) {
                            $pav->set('value', $value);
                            $this->getEntityManager()->saveEntity($pav, ['skipValidation' => true]);
                        }
                    }
                }
            }
        }
    }

    /**
     * @param Entity $entity
     */
    protected function updateLocalesMultiEnum(Entity $entity): void
    {
        if (!empty($attribute = $entity->get('attribute')) && !empty($localeAttributes = $attribute->get('attributes')->toArray())) {
            $keys = [];
            foreach (Json::decode($entity->get('value'), true) as $value) {
                $key = array_search($value, $attribute->get('typeValue'));
                if (is_int($key)) {
                    $keys[] = $key;
                }
            }

            if (!empty($keys)) {
                foreach ($localeAttributes as $localeAttribute) {
                    $value = [];
                    foreach ($keys as $key) {
                        if (isset($localeAttribute['typeValue'][$key])) {
                            $value[] = $localeAttribute['typeValue'][$key];
                        }
                    }

                    if (!empty($value)) {
                        $pav = $this
                            ->getEntityManager()
                            ->getRepository('ProductAttributeValue')
                            ->where(['attributeId' => $localeAttribute['id'], 'localeParentId' => $entity->get('id')])
                            ->findOne();

                        if (!empty($pav)) {
                            $pav->set('value', Json::encode($value));
                            $this->getEntityManager()->saveEntity($pav, ['skipValidation' => true]);
                        }
                    }
                }
            }
        }
    }
}
