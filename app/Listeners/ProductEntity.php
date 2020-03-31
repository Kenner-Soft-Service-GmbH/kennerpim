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

namespace Pim\Listeners;

use Espo\Core\Exceptions\BadRequest;
use Espo\ORM\Entity;
use Pim\Repositories\ProductAttributeValue;
use Treo\Core\EventManager\Event;
use Pim\Entities\Channel;

/**
 * Class ProductEntity
 *
 * @package Pim\Listeners
 * @author  m.kokhanskyi@treolabs.com
 */
class ProductEntity extends AbstractEntityListener
{
    /**
     * @param Event $event
     *
     * @throws BadRequest
     */
    public function beforeSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        // is sku valid
        if (!$this->isSkuUnique($entity)) {
            throw new BadRequest($this->exception('Product with such SKU already exist'));
        }

        if (!$entity->isNew() && $entity->isAttributeChanged('type')) {
            throw new BadRequest($this->exception('You can\'t change field of Type'));
        }
        if ($entity->isAttributeChanged('productFamilyId') && !$entity->isNew()) {
            throw new BadRequest($this->exception('You can\'t change Product Family in Product'));
        }
    }

    /**
     * @param Event $event
     */
    public function afterSave(Event $event)
    {
        // get entity
        $entity = $event->getArgument('entity');

        // get options
        $options = $event->getArgument('options');

        $skipUpdate = empty($entity->skipUpdateProductAttributesByProductFamily)
            && empty($options['skipProductFamilyHook']);

        if ($skipUpdate && !empty($entity->get('productFamily'))) {
            $this->updateProductAttributesByProductFamily($entity, $options);
        }
    }

    /**
     * @param Event $event
     */
    public function afterUnrelate(Event $event)
    {
        //set default value in isActive for channel after deleted link
        if ($event->getArgument('relationName') == 'channels' && $event->getArgument('foreign') instanceof Channel) {
            $dataEntity = new \StdClass();
            $dataEntity->entityName = 'Product';
            $dataEntity->entityId = $event->getArgument('entity')->get('id');
            $dataEntity->value = (int)!empty(
            $event
                ->getArgument('entity')
                ->getRelations()['channels']['additionalColumns']['isActive']['default']
            );

            $this
                ->getService('Channel')
                ->setIsActiveEntity($event->getArgument('foreign')->get('id'), $dataEntity, true);
        }
    }

    /**
     * Before action delete
     *
     * @param Event $event
     */
    public function afterRemove(Event $event)
    {
        $id = $event->getArgument('entity')->id;
        $this->removeProductAttributeValue($id);
    }

    /**
     * @param string $id
     */
    protected function removeProductAttributeValue(string $id)
    {
        $productAttributes = $this
            ->getEntityManager()
            ->getRepository('ProductAttributeValue')
            ->where(['productId' => $id])
            ->find();

        foreach ($productAttributes as $attr) {
            $this->getEntityManager()->removeEntity($attr, ['skipProductAttributeValueHook' => true]);
        }
    }

    /**
     * @param Entity $product
     * @param string $field
     *
     * @return bool
     */
    protected function isSkuUnique(Entity $product): bool
    {
        $products = $this
            ->getEntityManager()
            ->getRepository('Product')
            ->where(['sku' => $product->get('sku'), 'catalogId' => $product->get('catalogId')])
            ->find();

        if (count($products) > 0) {
            foreach ($products as $item) {
                if ($item->get('id') != $product->get('id')) {
                    return false;
                }
            }
        }

        return true;
    }

    /**
     * @param Entity $entity
     * @param array  $options
     *
     * @return bool
     *
     * @throws \Espo\Core\Exceptions\Error
     */
    protected function updateProductAttributesByProductFamily(Entity $entity, array $options): bool
    {
        // get product family
        $productFamily = $entity->get('productFamily');

        // get product family attributes
        $productFamilyAttributes = $productFamily->get('productFamilyAttributes');

        if ($entity->isNew()) {
            if (count($productFamilyAttributes) > 0) {
                /** @var ProductAttributeValue $repository */
                $repository = $this->getEntityManager()->getRepository('ProductAttributeValue');

                foreach ($productFamilyAttributes as $productFamilyAttribute) {
                    // create
                    $productAttributeValue = $repository->get();
                    $productAttributeValue->set(
                        [
                            'productId'                => $entity->get('id'),
                            'attributeId'              => $productFamilyAttribute->get('attributeId'),
                            'productFamilyAttributeId' => $productFamilyAttribute->get('id'),
                            'isRequired'               => $productFamilyAttribute->get('isRequired'),
                            'scope'                    => $productFamilyAttribute->get('scope'),
                            'locale'                   => $productFamilyAttribute->get('locale'),
                            'localeParentId'           => $repository->getLocaleParentId($productFamilyAttribute, $entity)
                        ]
                    );
                    // save
                    $this->getEntityManager()->saveEntity($productAttributeValue, ['skipValidation' => true]);

                    // relate channels if it needs
                    if ($productFamilyAttribute->get('scope') == 'Channel') {
                        $channels = $productFamilyAttribute->get('channels');
                        if (count($channels) > 0) {
                            foreach ($channels as $channel) {
                                $this
                                    ->getEntityManager()
                                    ->getRepository('ProductAttributeValue')
                                    ->relate($productAttributeValue, 'channels', $channel);
                            }
                        }
                    }
                }
            }

            return true;
        }

        return false;
    }

    /**
     * @param string $key
     *
     * @return string
     */
    protected function exception(string $key): string
    {
        return $this->translate($key, 'exceptions', 'Product');
    }
}
