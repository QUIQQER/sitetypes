/**
 * QUIQQER ChildrenListFilter Control
 */
define('package/quiqqer/sitetypes/bin/Controls/frontend/ChildrenListRemoteFilter', [
    'qui/QUI',
    'qui/controls/Control',
    'Ajax'
], function (QUI, QUIControl, QUIAjax) {
    "use strict";

    const listSelector = '[data-name="filterableList"]';
    const locationChange = 'quiqqer:children-list-location-change';

    function findInList(Root, name) {
        return Array.from(Root.querySelectorAll('[data-name="' + name + '"]'))
            .find(Element => Element.closest(listSelector) === Root);
    }

    return new Class({
        Extends: QUIControl,
        Type: 'package/quiqqer/sitetypes/bin/Controls/frontend/ChildrenListRemoteFilter',
        Binds: ['$onImport', '$onDestroy'],

        initialize: function (options) {
            this.parent(options);
            this.addEvents({
                onImport: this.$onImport,
                onDestroy: this.$onDestroy
            });
        },

        $onImport: function () {
            const Filters = this.getElm();

            this.root = Filters.closest(listSelector);
            if (!this.root) {
                return;
            }

            this.results = findInList(this.root, 'results');
            this.pagination = findInList(this.root, 'pagination');
            if (!this.results || !this.pagination) {
                return;
            }

            this.prefix = this.root.dataset.listPrefix;
            this.filterPrefix = Filters.dataset.filterPrefix;
            this.context = {
                project: JSON.stringify({name: Filters.dataset.project, lang: Filters.dataset.lang}),
                lang: Filters.dataset.lang,
                siteId: Filters.dataset.siteId,
                placement: Filters.dataset.placement
            };
            this.pageParameter = this.context.placement === 'site' ? 'sheet' : this.prefix + 'page';
            this.tags = Filters.querySelectorAll('[data-name="tag"]');
            this.input = Filters.querySelector('[data-name="textSearch"]');
            this.status = Filters.querySelector('[data-name="status"]');
            this.error = Filters.querySelector('[data-name="error"]');
            const url = new URL(window.location.href);
            this.state = this.readState(url);
            this.renderedState = this.state;
            this.inputSession = false;

            this.root.addEventListener('click', event => this.onLinkClick(event));

            this.pagination.addEventListener('change', event => {
                if (event.target.dataset.name === 'pageSelect') {
                    const page = Number(event.target.selectedOptions[0].dataset.page);
                    this.showState({...this.state, page}, true);
                }
            });

            if (this.input) {
                this.input.addEventListener('input', event => {
                    if (!event.isComposing) {
                        this.scheduleSearch();
                    }
                });
                this.input.addEventListener('compositionend', () => this.scheduleSearch());
                this.input.addEventListener('blur', () => this.endSearch());

                Filters.querySelector('[data-name="searchForm"]').addEventListener('submit', event => {
                    event.preventDefault();
                    this.commitSearch();
                    this.inputSession = false;
                });
            }

            this.listeners = new AbortController();
            const options = {signal: this.listeners.signal};

            const onLocationChange = event => {
                if (!this.root.isConnected) {
                    this.$onDestroy();
                    return;
                }

                const url = new URL(window.location.href);
                if (event.type === 'popstate' || (event.detail !== this && event.detail?.prefix === this.prefix)) {
                    clearTimeout(this.searchTimer);
                    this.searchTimer = null;
                    this.inputSession = false;
                    const state = this.readState(url);
                    if (this.input) {
                        this.input.value = state.query;
                    }
                    this.showState(state);
                }
                this.updateLinks(url);
            };
            window.addEventListener('popstate', onLocationChange, options);
            window.addEventListener(locationChange, onLocationChange, options);

            this.updateLinks(url);

            if (this.input && this.input.value.trim() !== this.state.query) {
                this.scheduleSearch();
            }
        },

        $onDestroy: function () {
            this.listeners?.abort();
            this.request?.abort();
            clearTimeout(this.searchTimer);
        },

        readState: function (url) {
            const params = url.searchParams;
            const tag = params.get(this.filterPrefix + 'tag') || '';
            const page = params.get(this.prefix + 'page') ?? params.get(this.pageParameter);

            return {
                query: this.input ? (params.get(this.filterPrefix + 'q') || '').trim() : '',
                tag: Array.from(this.tags).some(Link => Link.dataset.tag === tag) ? tag : '',
                page: /^\d+$/.test(page || '') ? Math.max(1, Number(page)) : 1
            };
        },

        writeState: function (url, state) {
            const params = url.searchParams;
            if (this.pageParameter === 'sheet') {
                params.delete(this.prefix + 'page');
                params.delete(this.prefix + 'q');
                params.delete(this.prefix + 'tag');
            }
            for (const [name, value] of [
                [this.filterPrefix + 'q', state.query],
                [this.filterPrefix + 'tag', state.tag],
                [this.pageParameter, state.page > 1 ? state.page : '']
            ]) {
                if (value === '') {
                    params.delete(name);
                } else {
                    params.set(name, value);
                }
            }
            return url;
        },

        updateLinks: function (url) {
            for (const Link of this.tags) {
                Link.href = this.writeState(new URL(url), {...this.state, tag: Link.dataset.tag, page: 1});
            }
            for (const Link of this.pagination.querySelectorAll('[data-name="page"]')) {
                Link.href = this.writeState(new URL(url), {...this.state, page: Number(Link.dataset.page)});
            }

            const Select = this.pagination.querySelector('[data-name="pageSelect"]');
            if (Select) {
                // The server-rendered select navigates until this control handles changes.
                Select.removeAttribute('onchange');

                for (const Option of Select.options) {
                    Option.value = this.writeState(new URL(url), {...this.state, page: Number(Option.dataset.page)});
                }
            }
        },

        onLinkClick: function (event) {
            if (event.defaultPrevented || event.button !== 0 || event.metaKey ||
                event.ctrlKey || event.shiftKey || event.altKey) {
                return;
            }

            const Link = event.target.closest('[data-name="tag"], [data-name="page"]');
            if (!Link || Link.closest(listSelector) !== this.root ||
                (Link.dataset.name === 'page' && !this.pagination.contains(Link))) {
                return;
            }

            event.preventDefault();
            clearTimeout(this.searchTimer);
            this.searchTimer = null;
            this.inputSession = false;

            if (Link.dataset.name === 'tag') {
                this.navigate({
                    query: this.input ? this.input.value.trim() : '',
                    tag: Link.dataset.tag,
                    page: 1
                });
                return;
            }

            this.showState({...this.state, page: Number(Link.dataset.page)}, true);
        },

        scheduleSearch: function () {
            clearTimeout(this.searchTimer);
            this.searchTimer = setTimeout(() => this.commitSearch(), 250);
        },

        endSearch: function () {
            if (this.searchTimer) {
                this.commitSearch();
            }
            this.inputSession = false;
        },

        commitSearch: function () {
            clearTimeout(this.searchTimer);
            this.searchTimer = null;

            const query = this.input.value.trim();
            if (query === this.state.query && !this.error.hidden) {
                this.showState(this.state);
                return;
            }
            if (query === this.state.query) {
                return;
            }

            if (this.navigate({...this.state, query, page: 1}, this.inputSession)) {
                this.inputSession = true;
            }
        },

        navigate: function (state, replace = false) {
            this.showState(state);
            return this.updateLocation(state, replace);
        },

        updateLocation: function (state, replace = false) {
            const url = new URL(window.location.href);
            if (this.stateKey(this.readState(url)) === this.stateKey(state)) {
                return false;
            }

            this.writeState(url, state);
            if (replace) {
                window.history.replaceState(window.history.state, '', url);
            } else {
                window.history.pushState(window.history.state, '', url);
            }
            window.dispatchEvent(new CustomEvent(locationChange, {detail: this}));
            return true;
        },

        stateKey: function ({query, tag, page}) {
            return JSON.stringify([query, tag, page]);
        },

        showState: async function (state, pageNavigation = false) {
            const key = this.stateKey(state);
            if (this.request && this.stateKey(this.state) === key) {
                return;
            }

            this.state = state;
            const pending = this.request;
            this.request = null;
            pending?.abort();

            for (const Link of this.tags) {
                const current = Link.dataset.tag === state.tag;
                Link.classList.toggle('active', current);
                if (current) {
                    Link.setAttribute('aria-current', 'true');
                } else {
                    Link.removeAttribute('aria-current');
                }
            }

            this.error.hidden = true;
            if (this.stateKey(this.renderedState) === key) {
                this.results.removeAttribute('aria-busy');
                if (pageNavigation) {
                    this.updateLocation(state);
                }
                return;
            }

            const request = new AbortController();
            const previousFocus = document.activeElement;
            this.request = request;
            this.results.setAttribute('aria-busy', 'true');

            try {
                const response = await this.fetchFragment(state, request.signal);
                if (request.signal.aborted) {
                    return;
                }
                if (!response || typeof response.html !== 'string') {
                    throw new Error('Unable to load the list.');
                }

                const Fragment = document.createElement('template');
                Fragment.innerHTML = response.html;

                const selector = listSelector + '[data-list-prefix="' + CSS.escape(this.prefix) + '"]';
                const NextList = Fragment.content.querySelector(selector);
                if (!NextList) {
                    throw new Error('List not found in the response.');
                }

                const Results = findInList(NextList, 'results');
                const Pagination = findInList(NextList, 'pagination');
                const Filters = findInList(NextList, 'filters');
                if (!Results || !Pagination || !Filters) {
                    throw new Error('Incomplete list in the response.');
                }

                Results.querySelectorAll('script').forEach(Script => Script.remove());
                const showResults = pageNavigation && document.activeElement === previousFocus &&
                    document.activeElement !== this.input;
                this.results.replaceChildren(...Results.childNodes);
                this.pagination.replaceChildren(...Pagination.childNodes);
                this.status.textContent = Filters.querySelector('[data-name="status"]').textContent.trim();
                this.renderedState = state;
                if (pageNavigation) {
                    this.updateLocation(state);
                }
                this.updateLinks(new URL(window.location.href));
                QUI.parse(this.results);

                if (showResults) {
                    this.results.setAttribute('tabindex', '-1');
                    this.results.focus({preventScroll: true});
                    this.results.scrollIntoView({block: 'start'});
                }
            } catch (error) {
                if (request.signal.aborted) {
                    return;
                }
                if (pageNavigation) {
                    window.location.assign(this.writeState(new URL(window.location.href), state));
                } else {
                    this.error.hidden = false;
                }
            } finally {
                if (this.request === request) {
                    this.request = null;
                    this.results.removeAttribute('aria-busy');
                }
            }
        },

        fetchFragment: function ({query, tag, page}, signal) {
            return new Promise((resolve, reject) => {
                const Request = QUIAjax.get('package_quiqqer_sitetypes_ajax_childrenList', resolve, {
                    ...this.context,
                    package: 'quiqqer/sitetypes',
                    filter: JSON.stringify({q: query, tag, page}),
                    bundle: false,
                    showError: false,
                    showLogin: false,
                    onError: reject,
                    onCancel: reject
                });

                signal.addEventListener('abort', () => Request.cancel(), {once: true});
            });
        }
    });
});
