/**
 * Keep regular list navigation in sync with other lists that update the URL via AJAX.
 */
define('package/quiqqer/sitetypes/bin/Controls/frontend/ChildrenListNavigation', [
    'qui/controls/Control'
], function (QUIControl) {
    'use strict';

    const listSelector = '[data-name="filterableList"]';

    function copyOtherParameters(target, current, prefix) {
        for (const name of new Set(target.keys())) {
            if (!name.startsWith(prefix)) {
                target.delete(name);
            }
        }
        for (const [name, value] of current) {
            if (name !== '_url' && !name.startsWith(prefix)) {
                target.append(name, value);
            }
        }
    }

    return new Class({
        Extends: QUIControl,
        Type: 'package/quiqqer/sitetypes/bin/Controls/frontend/ChildrenListNavigation',
        Binds: ['$onImport', '$onDestroy'],

        initialize: function (options) {
            this.parent(options);
            this.addEvents({onImport: this.$onImport, onDestroy: this.$onDestroy});
        },

        $onImport: function () {
            const Filters = this.getElm();
            const List = Filters.closest(listSelector);
            if (!List) {
                return;
            }

            const prefix = List.dataset.listPrefix;
            this.listeners = new AbortController();
            const options = {signal: this.listeners.signal};
            const Form = Filters.querySelector('[data-name="searchForm"]');
            Form?.addEventListener('formdata', event => {
                copyOtherParameters(event.formData, new URL(window.location.href).searchParams, prefix);
            }, options);

            const updateLinks = () => {
                if (!List.isConnected) {
                    this.$onDestroy();
                    return;
                }

                const current = new URL(window.location.href).searchParams;
                const selector = '[data-name="filters"] a[data-name="tag"], ' +
                    '[data-name="pagination"] a[data-name="page"], ' +
                    '[data-name="pagination"] [data-name="pageSelect"] option';
                for (const element of List.querySelectorAll(selector)) {
                    if (element.closest(listSelector) !== List) {
                        continue;
                    }

                    const property = element.tagName === 'OPTION' ? 'value' : 'href';
                    const url = new URL(element[property], window.location.href);
                    copyOtherParameters(url.searchParams, current, prefix);
                    element[property] = url.href;
                }
            };

            window.addEventListener('quiqqer:children-list-location-change', updateLinks, options);
            window.addEventListener('popstate', updateLinks, options);
            updateLinks();
        },

        $onDestroy: function () {
            this.listeners?.abort();
        }
    });
});
