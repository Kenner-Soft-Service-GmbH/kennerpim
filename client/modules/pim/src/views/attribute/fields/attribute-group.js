/*
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

Espo.define('pim:views/attribute/fields/attribute-group', 'treo-core:views/fields/filtered-link',
    Dep => Dep.extend({

        selectBoolFilterList:  ['onlyActive'],

        setup() {
            Dep.prototype.setup.call(this);

            this.updateReadOnlyStatus();

            this.listenTo(this.model, 'sync', () => {
                this.updateReadOnlyStatus()
            });
        },

        updateReadOnlyStatus() {
            if (!this.model.isNew() && this.model.get('attributeGroupId')) {
                this.setReadOnly();
            } else {
                this.setNotReadOnly();
            }
        }

    })
);
