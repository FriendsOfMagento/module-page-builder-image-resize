define([
    'Magento_Ui/js/form/element/single-checkbox',
    'Fom_PageBuilderImageResize/js/form/element/use-default'
], function (Checkbox, useDefault) {
    'use strict';

    return Checkbox.extend(useDefault);
});
