/*
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

Espo.define('pim:views/product/record/list', 'pim:views/record/list',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            let massActionsList = this.getMetadata().get(['clientDefs', this.scope, 'additionalMassActions']) || {};
            Object.keys(massActionsList).forEach((item) => {
                this.massActionList.push(massActionsList[item].name);
                if (massActionsList[item].pushToCheckAllResultMassActionList) {
                    this.checkAllResultMassActionList.push(massActionsList[item].name);
                }
                let method = 'massAction' + Espo.Utils.upperCaseFirst(massActionsList[item].name);
                this[method] = function () {
                    let path = massActionsList[item].actionViewPath;
                    let o = {};
                    (massActionsList[item].optionsToPass || []).forEach((option) => {
                        if (option in this) {
                            o[option] = this[option];
                        }
                    });
                    this.createView(item, path, o, (view) => {
                        if (typeof view[massActionsList[item].action] === 'function') {
                            view[massActionsList[item].action]();
                        }
                    });
                };
            }, this);
        },
    })
);