require('jsdom-global')();

global.expect = require('expect');

global.$ = global.jQuery = require('jquery');

const MutationObserver = require('mutationobserver-shim');
global.MutationObserver = MutationObserver;