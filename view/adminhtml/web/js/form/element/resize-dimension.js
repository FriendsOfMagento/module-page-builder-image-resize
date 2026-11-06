define([
    'underscore',
    'Magento_Ui/js/form/element/abstract'
], function (_, Abstract) {
    'use strict';

    return Abstract.extend({
        defaults: {
            resizePrefix: '',
            frameRequired: false,
            imports: {
                frameRequired: '${ $.provider }:params.${ $.resizePrefix }_frame_required'
            },
            listens: {
                frameRequired: 'updateRequired'
            }
        },

        /**
         * Keep Frame needs both sides, so it makes the width and the height required.
         *
         * @param {Boolean} state
         */
        updateRequired: function (state) {
            var required = Boolean(state),
                validation = _.omit(this.validation, 'required-entry');

            if (required) {
                validation['required-entry'] = true;
            }

            this.validation = validation;
            this.required(required);

            if (this.error()) {
                this.validate();
            }
        }
    });
});
