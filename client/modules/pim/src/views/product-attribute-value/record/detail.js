/*
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

Espo.define('pim:views/product-attribute-value/record/detail', 'views/record/detail',
    Dep => Dep.extend({

        setup() {
            Dep.prototype.setup.call(this);

            this.handleValueModelDefsUpdating();
        },

        handleValueModelDefsUpdating() {
            this.updateModelDefs();
            this.listenTo(this.model, 'change:attributeId', () => {
                this.updateModelDefs();
                if (this.model.get('attributeId')) {
                    this.clearView('middle');
                    this.gridLayout = null;
                    this.createMiddleView(() => this.reRender());
                }
            });
        },

        updateModelDefs() {
            this.changeFieldsReadOnlyStatus(['attribute', 'channels', 'product', 'scope'], !this.model.get('isCustom'));
            if (this.model.get('attributeId')) {
                let type = this.model.get('attributeType');
                let typeValue = this.model.get('typeValue');
                if (type) {
                    let fieldDefs = {
                        type: type,
                        options: typeValue,
                        view: type !== 'bool' ? this.getFieldManager().getViewName(type) : 'pim:views/fields/bool-required',
                        required: !!this.model.get('isRequired')
                    };
                    if (['varcharMultiLang', 'textMultiLang', 'enumMultiLang', 'multiEnumMultiLang', 'arrayMultiLang', 'wysiwygMultiLang'].includes(type)) {
                        fieldDefs.isMultilang = true;
                        this.getFieldManager().getActualAttributeList(type, 'typeValue').splice(1).forEach(item => {
                            fieldDefs[`options${item.replace('typeValue', '')}`] = this.model.get(item);
                        });
                    }
                    if (type === 'unit') {
                        fieldDefs.measure = (typeValue || ['Length'])[0];
                    }
                    this.model.defs.fields.value = fieldDefs;
                }
            }
        },

        changeFieldsReadOnlyStatus(fields, condition) {
            fields.forEach(field => this.model.defs.fields[field].readOnly = condition);
        },

        fetch() {
            let data = Dep.prototype.fetch.call(this);
            let view = this.getFieldView('value');
            if (view) {
                this.extendFieldData(view, data);
            }
            return data;
        },

        extendFieldData(view, data) {
            let additionalData = false;

            if (view.type === 'unit') {
                let actualFieldDefs = this.getMetadata().get(['fields', view.type, 'actualFields']) || [];
                let actualFieldValues = this.getFieldManager().getActualAttributes(view.type, view.name) || [];
                actualFieldDefs.forEach((field, i) => {
                    if (field) {
                        additionalData = additionalData || {};
                        additionalData[field] = data[actualFieldValues[i]];
                    }
                });
            }

            if (view.type === 'image') {
                _.extend((data || {}), {value: (data || {}).valueId});
            }

            if (additionalData) {
                _.extend((data || {}), {data: additionalData});
            }
        }

    })
);

