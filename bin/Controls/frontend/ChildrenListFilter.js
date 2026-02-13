/**
 * QUIQQER ChildrenListFilter Control
 */
define('package/quiqqer/sitetypes/bin/Controls/frontend/ChildrenListFilter', [

    'qui/QUI',
    'qui/controls/Control'

], function (QUI, QUIControl) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type   : 'package/quiqqer/sitetypes/bin/Controls/frontend/ChildrenListFilter',

        options: {
            itemsData: '' // JSON data for search
        },

        Binds: [
            '$onImport'
        ],

        initialize: function (options) {
            this.parent(options);

            this.TagContainer = null;
            this.itemsData = false;
            this.entries = null; // items (HTML Elements)
            this.activeTag = null;
            this.noResultsContainer = null;

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function () {
            const Elm = this.getElm();

            this.itemsData = Elm.getAttribute('data-itemsData');

            if (!this.itemsData) {
                this.itemsData = this.getAttribute('itemsData');
            }

            if (!this.itemsData) {
                console.error("No JSON Data to search in, control is no needed.");
                return;
            }

            const ChildrenListContainer = Elm.parentNode;
            this.items = ChildrenListContainer.querySelectorAll('[data-name="item"]');

            this.noResultsContainer = ChildrenListContainer.querySelector('[data-name="noResults"]');

            this.TagContainer = Elm.querySelector('[data-name="tags"]');
            this.Input = Elm.querySelector('[data-name="textSearch"]');

            if (this.TagContainer) {
                this.initTags();
            }

            if (this.Input) {
                let debounceTimeout = null;
                const self = this;
                // Keycodes, die ignoriert werden sollen
                const ignoreKeys = [
                    'Shift', 'Control', 'Alt', 'Meta', 'CapsLock',
                    'Tab', 'Escape', 'ArrowLeft', 'ArrowRight', 'ArrowUp', 'ArrowDown',
                    'Enter', 'PageUp', 'PageDown', 'End', 'Home', 'Insert', 'Delete'
                ];
                this.Input.addEventListener('keyup', function (e) {
                    if (ignoreKeys.includes(e.key)) {
                        return;
                    }
                    clearTimeout(debounceTimeout);
                    debounceTimeout = setTimeout(function () {
                        self.filterEntries(self.activeTag || null, self.Input.value);
                    }, 250);
                });
            }
        },

        /**
         * init tags
         */
        initTags: function () {
            if (!this.TagContainer) {
                return;
            }

            const tags = this.TagContainer.querySelectorAll('button')

            tags.forEach(Tag => {
                Tag.addEventListener('click', (e) => {
                    e.preventDefault();

                    tags.forEach(Btn => {
                        Btn.classList.remove('active');
                    });

                    Tag.classList.add('active');

                    this.activeTag = Tag.getAttribute('data-name');
                    this.filterEntries(this.activeTag, this.Input.value);

                });
            });
        },

        /**
         * Filter entries.
         * Empty parameter means no filter (matches all).
         *
         * @param activeTag
         * @param searchText
         */
        filterEntries: function(activeTag, searchText) {
            let itemsData = this.itemsData;
            if (typeof itemsData === 'string') {
                try {
                    itemsData = JSON.parse(itemsData);
                } catch (e) {
                    console.error('Invalid JSON in itemsData', e);
                    return;
                }
            }

            // Normalisiere Suchtext
            searchText = (searchText || '').trim().toLowerCase();

            let hasVisible = false;

            // Iteriere über alle Items
            this.items.forEach((item) => {
                const id = parseInt(item.getAttribute('data-id'), 10);
                const data = itemsData.find(d => d.id === id);

                if (!data) {
                    item.style.display = 'none';
                    return;
                }

                // tag filter
                let matchesTag = true;
                if (activeTag && Array.isArray(data.tags)) {
                    matchesTag = data.tags.includes(activeTag);
                } else if (activeTag && !Array.isArray(data.tags)) {
                    matchesTag = false;
                }

                // text search
                let matchesText = true;
                if (searchText) {
                    matchesText =
                        (data.title && data.title.toLowerCase().includes(searchText)) ||
                        (data.description && data.description.toLowerCase().includes(searchText)) ||
                        (Array.isArray(data.tags) && data.tags.join(' ').toLowerCase().includes(searchText));
                }

                // set display if match
                const visible = matchesTag && matchesText;
                item.style.display = visible ? '' : 'none';
                if (visible) {
                    hasVisible = true;
                }
            });

            if (hasVisible) {
                this.hideNoResultsInfo();
            } else {
                this.showNoResultsInfo();
            }
        },

        showNoResultsInfo: function() {
            if (!this.noResultsContainer) {
                return;
            }

            this.noResultsContainer.style.display = '';
        },

        hideNoResultsInfo: function() {
            if (!this.noResultsContainer) {
                return;
            }

            this.noResultsContainer.style.display = 'none';
        }

    });
});
