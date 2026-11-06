define([
    'Magento_Ui/js/form/element/single-checkbox',
    'mage/translate'
], function (Checkbox, $t) {
    'use strict';

    return Checkbox.extend({
        defaults: {
            resizePrefix: '',
            active: false,
            resizeWidth: '',
            resizeHeight: '',
            imports: {
                resizeWidth: '${ $.provider }:${ $.parentScope }.${ $.resizePrefix }_width',
                resizeHeight: '${ $.provider }:${ $.parentScope }.${ $.resizePrefix }_height'
            },
            exports: {
                active: '${ $.provider }:params.${ $.resizePrefix }_active'
            },
            listens: {
                'checked visible': 'updateActive',
                'resizeWidth resizeHeight': 'revalidate'
            }
        },

        /**
         * @returns {Object}
         */
        initObservable: function () {
            this._super().observe('active');

            return this;
        },

        /**
         * @returns {Object}
         */
        setInitialValue: function () {
            this._super();
            this.updateActive();

            return this;
        },

        /**
         * Show the resize fields only while the image field is shown and resizing is on.
         */
        updateActive: function () {
            this.active(this.isActive());
            this.revalidate();
        },

        /**
         * @returns {Boolean}
         */
        isActive: function () {
            return Boolean(this.visible() && this.checked());
        },

        /**
         * Report a missing size on save only. A change re-checks an error that is already shown.
         */
        onUpdate: function () {
            this.bubble('update', this.hasChanged());
            this.revalidate();
        },

        /**
         * Clear or update a shown error when the width or the height changes.
         */
        revalidate: function () {
            if (this.error()) {
                this.validate();
            }
        },

        /**
         * Resizing needs at least one side.
         *
         * @returns {Object}
         */
        validate: function () {
            var result = this._super(),
                message = $t('Enter a width or a height to resize the image.');

            if (!result.valid || !this.isActive() || this.resizeWidth || this.resizeHeight) {
                return result;
            }

            this.error(message);
            this.error.valueHasMutated();
            this.bubble('error', message, this);

            if (this.source) {
                this.source.set('params.invalid', true);
            }

            return {
                valid: false,
                target: this
            };
        }
    });
});
