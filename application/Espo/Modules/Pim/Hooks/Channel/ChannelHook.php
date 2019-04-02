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

namespace Espo\Modules\Pim\Hooks\Channel;

use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\Pim\Core\Hooks\AbstractHook;
use Espo\Core\Exceptions;
use Espo\Modules\Pim\Entities\Channel as ChannelEntity;

/**
 * Channel hook
 *
 * @author r.ratsun <r.ratsun@treolabs.com>
 */
class ChannelHook extends AbstractHook
{

    /**
     * @param ChannelEntity $entity
     * @param array         $params
     *
     * @throws BadRequest
     */
    public function beforeSave(ChannelEntity $entity, $params = [])
    {
        if (!$this->isCodeValid($entity)) {
            throw new Exceptions\BadRequest(
                $this->translate(
                    'Code is invalid',
                    'exceptions',
                    'Global'
                )
            );
        }
    }
}
