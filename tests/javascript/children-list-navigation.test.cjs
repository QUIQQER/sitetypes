const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const test = require('node:test');
const vm = require('node:vm');

test('native links, mobile pagination and GET form preserve the live state of other lists', () => {
    const window = new EventTarget();
    const Form = new EventTarget();
    const prefix = 'list-brick-42-';
    window.location = new URL('https://example.test/news?list-q=old');
    const tag = {tagName: 'A', href: '?list-brick-42-tag=music&list-q=old'};
    const page = {tagName: 'A', href: '?list-brick-42-page=2&list-q=old'};
    const option = {tagName: 'OPTION', value: page.href};
    const links = [tag, page, option];
    const list = {dataset: {listPrefix: prefix}, isConnected: true, querySelectorAll: () => links};
    for (const link of links) {
        link.closest = () => list;
    }

    let control;
    const file = path.join(__dirname, '../../bin/Controls/frontend/ChildrenListNavigation.js');
    vm.runInNewContext(fs.readFileSync(file, 'utf8'), {
        define(name, deps, factory) { control = factory({}); },
        Class: function (value) { return value; },
        window, URL, AbortController
    });
    control.getElm = () => ({closest: () => list, querySelector: () => Form});
    control.$onImport();
    window.location.search = '?list-q=Berlin&extra[]=1&extra[]=2';
    window.dispatchEvent(new Event('quiqqer:children-list-location-change'));

    for (const target of [tag.href, page.href, option.value]) {
        const params = new URL(target).searchParams;
        assert.equal(params.get('list-q'), 'Berlin');
        assert.deepEqual(params.getAll('extra[]'), ['1', '2']);
    }
    assert.equal(new URL(tag.href).searchParams.get(prefix + 'tag'), 'music');
    assert.equal(new URL(option.value).searchParams.get(prefix + 'page'), '2');
    const submit = new Event('formdata');
    submit.formData = new FormData();
    submit.formData.set('list-q', 'old');
    submit.formData.set(prefix + 'q', 'Jazz');
    Form.dispatchEvent(submit);
    assert.equal(submit.formData.get(prefix + 'q'), 'Jazz');
    assert.equal(submit.formData.get('list-q'), 'Berlin');
    assert.deepEqual(submit.formData.getAll('extra[]'), ['1', '2']);

    window.location.search = '';
    window.dispatchEvent(new Event('popstate'));
    assert.equal(new URL(page.href).searchParams.has('list-q'), false);
    assert.equal(new URL(page.href).searchParams.get(prefix + 'page'), '2');
    control.$onDestroy();
});
