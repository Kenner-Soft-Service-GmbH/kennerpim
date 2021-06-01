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

namespace Pim\Import\Types\Simple\FieldConverters;

use Espo\ORM\Entity;
use Import\Types\Simple\FieldConverters\Unit as DefaultUnit;

/**
 * Class Unit
 *
 * @author r.zablodskiy@treolabs.com
 */
class Unit extends DefaultUnit
{
    /**
     * @inheritDoc
     */
    public function convert(\stdClass $inputRow, string $entityType, array $config, array $row, string $delimiter, string $decimalMark = '.')
    {
        // prepare values
        $value = (!empty($config['column']) && $row[$config['column']] != '') ? $row[$config['column']] : $config['default'];
        $unit = (!empty($config['columnUnit']) && $row[$config['columnUnit']] != '') ? $row[$config['columnUnit']] : $config['defaultUnit'];

        $name = isset($config['attributeId']) ? $config['attribute']->get('name') : $config['name'];

        // validate unit float value
        if (!is_null($value) && filter_var($value, FILTER_VALIDATE_FLOAT, ["options" => ["default" => $decimalMark]]) === false) {
            throw new \Exception("Incorrect value for attribute '$name'");
        }

        // validate measuring unit
        if (!$this->validateUnit($unit, $entityType, $config)) {
            throw new \Exception("Incorrect measuring unit for attribute '$name'");
        }

        $value = str_replace($decimalMark, '.', $value);
        if (isset($config['attributeId'])) {
            // prepare input row for attribute
            $inputRow->{$config['name']} = $value;
            $inputRow->data = (object)['unit' => $unit];
        } else {
            // set values to input row
            $inputRow->{$config['name']} = (float)$value;
            $inputRow->{$config['name'] . 'Unit'} = $unit;
        }
    }

    /**
     * @inheritDoc
     */
    public function prepareValue(\stdClass $restore, Entity $entity, array $item)
    {
        parent::prepareValue($restore, $entity, $item);
        if (isset($item['attributeId'])) {
            // prepare restore row for attribute
            $restore->data = $entity->get('data');
            unset($restore->{$item['name'].'Unit'});
        }
    }

    /**
     * @inheritDoc
     */
    protected function getMeasure(string $entityType, array $config): string
    {
        if (!isset($config['attributeId'])) {
            return parent::getMeasure($entityType, $config);
        } else {
            return $config['attribute']->get('typeValue')[0];
        }
    }
}