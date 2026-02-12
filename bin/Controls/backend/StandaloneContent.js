require.config({
    paths: {
        'ace/ace': URL_OPT_DIR + 'bin/quiqqer-asset/ace-builds/ace-builds/src-min/ace',
        'ace/theme/twilight': URL_OPT_DIR + 'bin/quiqqer-asset/ace-builds/ace-builds/src-min/theme-twilight',
        'ace/mode/html': URL_OPT_DIR + 'bin/quiqqer-asset/ace-builds/ace-builds/src-min/mode-html',
        'ace/mode/html_worker': URL_OPT_DIR + 'bin/quiqqer-asset/ace-builds/ace-builds/src-min/worker-html'
    }
});

define('package/quiqqer/sitetypes/bin/Controls/backend/StandaloneContent', [

    'qui/controls/Control',
    'Locale',
    'ace/ace'

], function (QUIControl, QUILocale, Ace) {
    "use strict";

    return new Class({

        Extends: QUIControl,
        Type: 'package/quiqqer/sitetypes/bin/Controls/backend/StandaloneContent',

        options: {},

        Binds: [
            '$onImport'
        ],

        initialize: function (options) {
            this.parent(options);

            this.addEvents({
                onImport: this.$onImport
            });
        },

        $onImport: function () {
            const input = this.getElm();
            const table = input.closest('table');
            const form = input.closest('form');
            const editorNode = document.createElement('div');
            const aceBasePath = URL_OPT_DIR + 'bin/quiqqer-asset/ace-builds/ace-builds/src-min';

            // get panel content size
            const panelContent = input.closest('.qui-panel-content');
            const panelStyle = panelContent ? window.getComputedStyle(panelContent) : null;
            const paddingTop = panelStyle ? parseFloat(panelStyle.paddingTop) || 0 : 0;
            const paddingRight = panelStyle ? parseFloat(panelStyle.paddingRight) || 0 : 0;
            const paddingBottom = panelStyle ? parseFloat(panelStyle.paddingBottom) || 0 : 0;
            const paddingLeft = panelStyle ? parseFloat(panelStyle.paddingLeft) || 0 : 0;

            // form
            form.style.height = '100%';
            form.style.width = '100%';
            form.style.position = 'relative';
            form.appendChild(input);
            table.parentNode.removeChild(table);
            form.appendChild(editorNode);

            editorNode.style.position = 'absolute';
            editorNode.style.top = '-' + paddingTop + 'px';
            editorNode.style.left = '-' + paddingLeft + 'px';
            editorNode.style.width = 'calc(100% + ' + (paddingLeft + paddingRight) + 'px)';
            editorNode.style.height = 'calc(100% + ' + (paddingTop + paddingBottom) + 'px)';

            Ace.config.set('basePath', aceBasePath);
            Ace.config.set('modePath', aceBasePath);
            Ace.config.set('themePath', aceBasePath);
            Ace.config.set('workerPath', aceBasePath);

            const aceEditor = Ace.edit(editorNode);

            aceEditor.session.setValue(input.value || '');
            aceEditor.session.setMode('ace/mode/html');
            //aceEditor.setTheme('ace/theme/twilight');

            aceEditor.session.on('change', function () {
                input.value = aceEditor.getValue();
            });
        }
    });
});
