define([
    'Fom_PageBuilderImageResize/js/form/element/use-default-checkbox'
], function (Toggle) {
    'use strict';

    return Toggle.extend({
        defaults: {
            dimensionPrefix: '',
            frameRequired: false,
            exports: {
                frameRequired: '${ $.provider }:params.${ $.dimensionPrefix }_frame_required'
            },
            listens: {
                'value disabled visible': 'updateFrameRequired'
            }
        },

        /**
         * @returns {Object}
         */
        initObservable: function () {
            this._super().observe('frameRequired');

            return this;
        },

        /**
         * @returns {Object}
         */
        setInitialValue: function () {
            this._super();
            this.updateFrameRequired();

            return this;
        },

        /**
         * Keep Frame makes a box of exactly width by height, so the width and the height are required while it is on.
         */
        updateFrameRequired: function () {
            this.frameRequired(Boolean(this.visible() && !this.disabled() && Number(this.value()) === 1));
        }
    });
});
