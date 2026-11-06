define([
    'Magento_PageBuilder/js/utils/object'
], function (object) {
    'use strict';

    var DIMENSIONS = ['width', 'height'],
        FLAGS = ['aspect_ratio', 'frame', 'constrain', 'transparency'],
        TEXTS = ['background'],
        ENABLED_SUFFIX = '_enabled',
        USE_DEFAULT_SUFFIX = '_use_default',
        isTrue = function (value) {
            return Number(value) === 1;
        },
        isEmpty = function (value) {
            return value === undefined || value === null || value === '';
        },
        toDimension = function (value) {
            var number = parseInt(value, 10);

            return number > 0 ? number : null;
        };

    /**
     * @constructor
     */
    function ImageResize() {}

    /**
     * Unpack the saved settings into the form fields. Options that were not saved keep the form defaults.
     *
     * @param {Object} data
     * @param {Object} config
     * @returns {Object}
     */
    ImageResize.prototype.fromDom = function (data, config) {
        var name = config.attribute_name,
            saved = object.get(data, name),
            settings = saved ? JSON.parse(saved.replace(/\\(.)/mg, '$1')) || {} : {};

        object.set(data, name + ENABLED_SUFFIX, saved ? '1' : '0');

        DIMENSIONS.forEach(function (key) {
            object.set(data, name + '_' + key, isEmpty(settings[key]) ? '' : String(settings[key]));
        });

        FLAGS.forEach(function (key) {
            if (!isEmpty(settings[key])) {
                object.set(data, name + '_' + key, isTrue(settings[key]) ? '1' : '0');
            }
        });

        TEXTS.forEach(function (key) {
            if (!isEmpty(settings[key])) {
                object.set(data, name + '_' + key, String(settings[key]));
            }
        });

        delete data[name];

        return data;
    };

    /**
     * Pack the form fields into one attribute, or remove it when resizing is off or no size is set.
     * Options that use the default value are left out, so the storefront applies its own defaults.
     *
     * @param {Object} data
     * @param {Object} config
     * @returns {Object}
     */
    ImageResize.prototype.toDom = function (data, config) {
        var name = config.attribute_name,
            settings = {};

        DIMENSIONS.forEach(function (key) {
            var value = toDimension(object.get(data, name + '_' + key));

            if (value !== null) {
                settings[key] = value;
            }
        });

        if (!isTrue(object.get(data, name + ENABLED_SUFFIX)) || (!settings.width && !settings.height)) {
            delete data[name];

            return data;
        }

        FLAGS.concat(TEXTS).forEach(function (key) {
            var field = name + '_' + key,
                value = object.get(data, field);

            if (isTrue(object.get(data, field + USE_DEFAULT_SUFFIX)) || isEmpty(value)) {
                return;
            }

            settings[key] = FLAGS.includes(key) ? (isTrue(value) ? 1 : 0) : String(value);
        });

        object.set(
            data,
            name,
            JSON.stringify(settings).replace(/[\\"']/g, '\\$&').replace(/\u0000/g, '\\0')
        );

        return data;
    };

    return ImageResize;
});
