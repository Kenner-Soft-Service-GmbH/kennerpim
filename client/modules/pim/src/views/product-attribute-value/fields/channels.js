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

Espo.define('pim:views/product-attribute-value/fields/channels', 'treo-core:views/fields/filtered-link-multiple',
    Dep => Dep.extend({

        selectBoolFilterList: ['notAllowedForProduct'],

        boolFilterData: {
            notAllowedForProduct() {
                return {
                    productId: this.model.get('productId'),
                    attributeId: this.model.get('attributeId')
                };
            }
        },

        setup() {
            Dep.prototype.setup.call(this);

            this.listenTo(this.model, 'change:attributeId change:scope', () => {
                if (this.model.get('scope') !== 'Channel' || !this.model.get('attributeId')) {
                    this.model.set({
                        [this.idsName]: null,
                        [this.nameHashName]: null
                    });
                }
            });
        }

    })
);

