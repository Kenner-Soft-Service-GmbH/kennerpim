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

Espo.define('pim:views/fields/overview-channels-filter', 'treo-core:views/fields/dropdown-enum',
    Dep => Dep.extend({

        channels: [],

        optionsList: [
            {
                name: '',
                selectable: true
            },
            {
                name: 'onlyGlobalScope',
                selectable: true
            }
        ],

        setup() {
            this.baseOptionList = Espo.Utils.cloneDeep(this.optionsList);
            this.wait(true);
            this.updateChannels(() => this.wait(false));

            Dep.prototype.setup.call(this);
        },

        updateChannels(callback) {
            this.channels = [];
            this.optionsList = Espo.Utils.cloneDeep(this.baseOptionList);
            this.getFullEntityList(`Product/${this.model.id}/channels`, {select: 'name'}, list => {
                this.setChannelsFromList(list);
                this.prepareOptionsList();
                this.updateSelected();
                this.modelKey = this.options.modelKey || this.modelKey;
                this.setDataToModel({[this.name]: this.selected});
                callback();
            });
        },

        updateSelected() {
            if (this.storageKey) {
                let selected = ((this.getStorage().get(this.storageKey, this.scope) || {})[this.name] || {}).selected;
                if (this.optionsList.find(option => option.name === selected)) {
                    this.selected = selected;
                }
            }
            this.selected = this.selected || (this.optionsList.find(option => option.selectable) || {}).name;
        },

        getFullEntityList(url, params, callback, container) {
            if (url) {
                container = container || [];

                let options = params || {};
                options.maxSize = options.maxSize || 200;
                options.offset = options.offset || 0;

                this.ajaxGetRequest(url, options).then(response => {
                    container = container.concat(response.list || []);
                    options.offset = container.length;
                    if (response.total > container.length || response.total === -1) {
                        this.getFullEntity(url, options, callback, container);
                    } else {
                        callback(container);
                    }
                });
            }
        },

        setChannelsFromList(list) {
            list.forEach(item => {
                if (!this.channels.find(channel => channel.id === item.id)) {
                    this.channels.push({
                        id: item.id,
                        name: item.name
                    });
                }
            });
        },

        prepareOptionsList() {
            this.channels.forEach(channel => {
                if (!this.optionsList.find(option => option.name === channel.id)) {
                    this.optionsList.push({
                        name: channel.id,
                        label: channel.name,
                        selectable: true
                    });
                }
            });

            Dep.prototype.prepareOptionsList.call(this);
        }

    })
);