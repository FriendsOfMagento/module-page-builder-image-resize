define([
    'require'
], function (require) {
    'use strict';

    var isTrue = function (value) {
        return Number(value) === 1;
    };

    return {
        defaults: {
            useDefaultSuffix: '_use_default',
            tooltipTpl: 'Fom_PageBuilderImageResize/form/element/helper/resize-tooltip',
            service: {
                template: 'ui/form/element/helper/service'
            },
            links: {
                isUseDefault: '${ $.provider }:${ $.dataScope }${ $.useDefaultSuffix }'
            }
        },

        /**
         * Resolve the tooltip image to a static URL.
         *
         * @returns {Object}
         */
        initConfig: function () {
            this._super();

            if (this.tooltip && this.tooltip.image) {
                this.tooltip.imageUrl = require.toUrl(this.tooltip.image);
            }

            return this;
        },

        /**
         * Start with "Use Default Value" checked when no choice is stored and the value is empty or the default.
         * Page Builder passes an empty value for every option that was not saved with the content.
         *
         * @returns {Object}
         */
        setInitialValue: function () {
            var stored = this.source ? this.source.get(this.dataScope + this.useDefaultSuffix) : undefined,
                isStored = stored !== undefined && stored !== null && stored !== '';

            if (!isStored && this.toText(this.value()) === '') {
                this.value(this.toText(this.default));
            }

            this._super();
            this.isUseDefault(isStored ? isTrue(stored) : this.hasDefaultValue());

            return this;
        },

        /**
         * @returns {Boolean}
         */
        hasDefaultValue: function () {
            return this.toText(this.value()) === this.toText(this.default);
        },

        /**
         * Restore the default when "Use Default Value" is checked, and lock the field while it stays checked.
         *
         * @param {Boolean} state
         */
        toggleUseDefault: function (state) {
            if (state && !this.hasDefaultValue()) {
                this.value(this.toText(this.default));
            }

            this.disabled(Boolean(state));
        },

        /**
         * @param {*} value
         * @returns {String}
         */
        toText: function (value) {
            return value === undefined || value === null ? '' : String(value);
        }
    };
});
