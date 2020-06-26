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

Espo.define('pim:views/product/detail', 'pim:views/detail',
    Dep => Dep.extend({

        selectRelatedFilters: {},

        selectBoolFilterLists: {
            attributes: ['notLinkedWithProduct'],
        },

        boolFilterData: {
            attributes: {
                notLinkedWithProduct() {
                    return this.model.id;
                },
            },
        },

        actionNavigateToRoot(data, e) {
            e.stopPropagation();

            this.getRouter().checkConfirmLeaveOut(function () {
                const rootUrl = this.options.rootUrl || this.options.params.rootUrl || '#' + this.scope;
                if (rootUrl !== `#${this.scope}`) {
                    this.getRouter().navigate(rootUrl,  {trigger: true});
                } else {
                    const options = {
                        isReturn: true
                    };
                    this.getRouter().navigate(rootUrl, {trigger: false});
                    this.getRouter().dispatch(this.scope, null, options);
                }
            }, this);
        }

    })
);

