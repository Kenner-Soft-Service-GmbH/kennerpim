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

namespace Espo\Modules\Pim\Listeners;

use Espo\Core\ORM\Entity;
use Espo\Core\Utils\Json;
use Espo\Core\Utils\Util;
use Treo\Core\EventManager\Event;

/**
 * Class ProductController
 *
 * @author r.ratsun@treolabs.com
 */
class ProductController extends AbstractPimListener
{

    /**
     * @param Event $event
     */
    public function beforeActionList(Event $event)
    {
        // get where
        $where = $event->getArgument('request')->get('where', []);

        // prepare where
        $where = $this
            ->getContainer()
            ->get('eventManager')
            ->dispatch('ProductController', 'prepareForProductType', new Event(['where' => $where]))
            ->getArgument('where');

        $where = $this
            ->getContainer()
            ->get('eventManager')
            ->dispatch('ProductController', 'prepareForAttributes', new Event(['where' => $where]))
            ->getArgument('where');

        // set where
        $event->getArgument('request')->setQuery('where', $where);
    }

    /**
     * @param Event $event
     */
    public function updateAttribute(Event $event)
    {
        // get arguments
        $data = $event->getArguments();

        if (isset($data['attributeValue']) && isset($data['post']) && isset($data['productId'])) {
            // create note
            if (!empty($noteData = $this->getNoteData($data['attributeValue'], $data['post']))) {
                $note = $this->getEntityManager()->getEntity('Note');
                $note->set('type', 'Update');
                $note->set('parentId', $data['productId']);
                $note->set('parentType', 'Product');
                $note->set('data', $noteData);
                $note->set('attributeId', $data['post']['attributeId']);

                $this->getEntityManager()->saveEntity($note);
            }
        }
    }

    /**
     * After create link
     *
     * @param Event $event
     */
    public function afterActionCreateLink(Event $event)
    {
        // get arguments
        $data = $event->getArguments();

        if ($data['params']['link'] == 'attributes') {
            $attributeIds = $this->prepareAttributeIds(Json::decode(Json::encode($data['data']), true));

            $this->setProductAttributeValueUser($attributeIds, (array)$data['params']['id']);
        }
    }

    /**
     * Prepare attributes ids data
     *
     * @param array $data
     *
     * @return array
     */
    protected function prepareAttributeIds(array $data): array
    {
        $ids = [];

        if (isset($data['ids'])) {
            $ids = $data['ids'];
        } elseif (isset($data['where'])) {
            $selectManager = $this
                ->getContainer()
                ->get('selectManagerFactory')
                ->create('Product');

            $result = $this
                ->getEntityManager()
                ->getRepository('Attribute')
                ->select(['id'])
                ->find($selectManager->getSelectParams(['where' => $data['where']]))
                ->toArray();

            if (!empty($result)) {
                $ids = array_column($result, 'id');
            }
        }

        return $ids;
    }

    /**
     * Get note data
     *
     * @param Entity $attributeValue
     * @param array  $post
     *
     * @return array
     */
    protected function getNoteData(Entity $attributeValue, array $post): array
    {
        // get attribute
        $attribute = $this
            ->getEntityManager()
            ->getEntity('Attribute', $attributeValue->get('attributeId'));

        // prepare field name
        $fieldName = $this->getLanguage()->translate('Attribute', 'custom', 'ProductAttributeValue');
        $fieldName .= ' ' . $attribute->get('name');

        // prepare result
        $result = [];

        $arrayTypes = ['array', 'arrayMultiLang', 'enum', 'enumMultiLang', 'multiEnum', 'multiEnumMultiLang'];

        // for value
        if ($post['value'] != $attributeValue->get('value')
            || (isset($post['data']['unit']) && $post['data']['unit'] != $attributeValue->get('data')->unit)) {
            $result['fields'][] = $fieldName;

            if (in_array($attribute->get('type'), $arrayTypes)) {
                $result['attributes']['was'][$fieldName] = Json::decode($attributeValue->get('value'), true);
            } else {
                $result['attributes']['was'][$fieldName] = $attributeValue->get('value');
            }

            $result['attributes']['became'][$fieldName] = $post['value'];

            if (isset($post['data']['unit'])) {
                $result['attributes']['was'][$fieldName . 'Unit'] = $attributeValue->get('data')->unit;
                $result['attributes']['became'][$fieldName . 'Unit'] = $post['data']['unit'];
            }
        }

        // for multilang value
        if ($this->getConfig()->get('isMultilangActive')) {
            foreach ($this->getConfig()->get('inputLanguageList') as $locale) {
                // prepare field
                $field = Util::toCamelCase('value_' . strtolower($locale));

                if (isset($post[$field]) && $post[$field] != $attributeValue->get($field)) {
                    // prepare field name
                    $localeFieldName = $fieldName . " ($locale)";

                    $result['fields'][] = $localeFieldName;

                    if (in_array($attribute->get('type'), $arrayTypes)) {
                        $result['attributes']['was'][$localeFieldName]
                            = Json::decode($attributeValue->get($field), true);
                    } else {
                        $result['attributes']['was'][$localeFieldName] = $attributeValue->get($field);
                    }

                    $result['attributes']['became'][$localeFieldName] = $post[$field];
                }
            }
        }

        return $result;
    }

    /**
     * @param Event $event
     */
    public function prepareForProductType(Event $event)
    {
        $where = $event->getArgument('where');

        // prepare types
        $types = $this
            ->getContainer()
            ->get('metadata')
            ->get('pim.productType');

        // prepare where
        $where[] = [
            'type'      => 'in',
            'attribute' => 'type',
            'value'     => array_keys($types)
        ];

        $event->setArgument('where', $where);
    }

    /**
     * @param Event $event
     */
    public function prepareForAttributes(Event $event)
    {
        $data = $event->getArgument('where');

        $event->setArgument('where', $this->prepareAttributesWhere($data));
    }

    /**
     * Get products filtered by attributes
     *
     * @param array $where
     *
     * @return array
     */
    protected function getProductIds(array $where = []): array
    {
        // prepare result
        $result = ['empty-id-filter'];

        // get data
        $data = $this
            ->getContainer()
            ->get('serviceFactory')
            ->create('ProductAttributeValue')
            ->findEntities(['where' => $where]);

        if ($data['total'] > 0) {
            $result = [];
            foreach ($data['collection'] as $entity) {
                if (!empty($entity->get('product')) && !in_array($entity->get('productId'), $result)) {
                    $result[] = $entity->get('productId');
                }
            }
        }

        return $result;
    }

    /**
     * @param array $data
     *
     * @return array
     */
    protected function prepareAttributesWhere(array $data): array
    {
        foreach ($data as $k => $row) {
            // check if exists array by key value
            $isValueArray = !empty($row['value']) && is_array($row['value']);

            if (empty($row['isAttribute']) && $isValueArray) {
                $data[$k]['value'] = $this->prepareAttributesWhere($row['value']);
            } elseif (!empty($row['isAttribute'])) {
                // prepare attribute where
                switch ($row['type']) {
                    case 'isTrue':
                        $where = [
                            'type'  => 'and',
                            'value' => [
                                [
                                    'type'      => 'equals',
                                    'attribute' => 'attributeId',
                                    'value'     => $row['attribute']
                                ],
                                [
                                    'type'      => 'equals',
                                    'attribute' => 'value',
                                    'value'     => 'TreoBoolIsTrue'
                                ]
                            ]
                        ];

                        break;
                    case 'isFalse':
                        $where = [
                            'type'  => 'and',
                            'value' => [
                                [
                                    'type'      => 'equals',
                                    'attribute' => 'attributeId',
                                    'value'     => $row['attribute']
                                ],
                                [
                                    'type'  => 'or',
                                    'value' => [
                                        [
                                            'type'      => 'isNull',
                                            'attribute' => 'value'
                                        ],
                                        [
                                            'type'      => 'equals',
                                            'attribute' => 'value',
                                            'value'     => 'TreoBoolIsFalse'
                                        ]
                                    ]
                                ],
                            ]
                        ];

                        break;
                    default:
                        $where = [
                            'type'  => 'and',
                            'value' => [
                                [
                                    'type'      => 'equals',
                                    'attribute' => 'attributeId',
                                    'value'     => $row['attribute']
                                ],
                                [
                                    'type'      => $row['type'],
                                    'attribute' => 'value',
                                    'value'     => $row['value']
                                ]
                            ]
                        ];

                        break;
                }

                $productWhere = [
                    'type'      => 'equals',
                    'attribute' => 'id',
                    'value'     => $this->getProductIds([$where])
                ];

                // prepare where clause
                $data[$k] = $productWhere;
            }
        }

        return $data;
    }
}
