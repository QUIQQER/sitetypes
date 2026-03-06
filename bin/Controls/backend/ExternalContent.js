define('package/quiqqer/sitetypes/bin/Controls/backend/ExternalContent', [

    'qui/controls/Control',
    'Locale',
    'Mustache',

    'text!package/quiqqer/sitetypes/bin/Controls/backend/ExternalContent.html',

], function(
    QUIControl,
    QUILocale,
    Mustache,
    template
) {
    'use strict';

    const lg = 'quiqqer/sitetypes';

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/sitetypes/bin/Controls/backend/ExternalContent',

        Binds: [
            '$onImport'
        ],

        initialize: function(options) {
            this.parent(options);

            this.Select = null;
            this.variableOptionsNode = null; // NodeList of <tr> elements
            this.data = {};

            this.addEvents({
                onImport: this.$onImport
            });
        },

        /**
         * event: on import
         */
        $onImport: function() {
            this.$Input = this.getElm();

            this.$loadDataFromInput();
            this.$renderLayout();

            this.Select.addEventListener('change', (e) => {
                this.selectedType = e.target.value;
                this.toggleVariableOption();
            });

            this.$setValuesToInputs();

            this.variableOptionsNode = this.$Elm.querySelectorAll('[data-ref="variable-option"]');

            this.toggleVariableOption();
        },

        /**
         * Loads data from the hidden input field.
         */
        $loadDataFromInput: function () {
            const jsonData = this.$Input.get('value');

            if (jsonData) {
                try {
                    this.data = JSON.parse(jsonData);
                } catch (e) {
                    this.data = {};
                    console.warn('Invalid JSON in ExternalContent input:', e);
                }
            } else {
                this.data = {};
            }
        },

        /**
         * Generates the HTML structure of the component.
         */
        $renderLayout: function () {
            this.$Elm = new Element('div', {
                'class': 'quiqqer-sitetypes-backend-settings-externalContent',
                styles: {
                    width: '100%'
                }
            }).wraps(this.$Input);

            const prefix = 'sitetypes.externalContent.settings.';
            const texts = {
                typeTitle: QUILocale.get(lg, prefix + 'type.title'),
                typeOptionText: QUILocale.get(lg, prefix + 'type.option.text'),
                typeOptionIFrame: QUILocale.get(lg, prefix + 'type.option.iframe'),
                textTitle: QUILocale.get(lg, prefix + 'text.title'),
                urlTitle: QUILocale.get(lg, prefix + 'url.title'),
                heightDesktopTitle: QUILocale.get(lg, prefix + 'heightDesktop.title'),
                heightDesktopDesc: QUILocale.get(lg, prefix + 'heightDesktop.desc'),
                heightMobileTitle: QUILocale.get(lg, prefix + 'heightMobile.title'),
                heightMobileDesc: QUILocale.get(lg, prefix + 'heightMobile.desc'),
            };

            new Element('div', {
                html: Mustache.render(template, texts)
            }).inject(this.$Elm);

            this.Select = this.$Elm.querySelector('[data-ref="select"]');
            this.selectedType = 'text';
        },

        /**
         * Sets the values of the input fields, select and textarea based on the current data.
         */
        $setValuesToInputs: function () {
            const inputs = this.$Elm.getElements('input, select, textarea');

            inputs.each((input) => {
                input.addEventListener('blur', this.onInputBlur.bind(this));

                const name = input.getAttribute('name');
                const value = this.data[name];

                if (!value) return;

                if (input.type === 'select-one') {
                    input.value = value;
                    this.selectedType = value;
                } else {
                    input.set('value', value);
                }
            });
        },

        /**
         * Toggles the visibility of variable options based on the selected type.
         */
        toggleVariableOption: function() {
            this.variableOptionsNode.forEach((Node) => {
                if (Node.getAttribute('data-type') === this.selectedType) {
                    Node.style.display = null;
                } else {
                    Node.style.display = 'none';
                }
            });
        },

        /**
         * Handles the blur event of input fields, select, and textarea.
         * Updates the data object with the new value and sets the hidden input field.
         *
         * @param {Event} event - The blur event object.
         */
        onInputBlur: function(event) {
            const input = event.target;
            this.data[input.getAttribute('name')] = input.get('value');
            const jsonData = JSON.stringify(this.data);
            this.$Input.set('value', jsonData);
        }
    });
});
