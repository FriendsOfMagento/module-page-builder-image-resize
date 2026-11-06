# Add resize support to a content type

How much work a content type needs depends on how it stores its image.

| The content type stores its image in | Work needed |
|---|---|
| `data-background-images`, through Page Builder's `background-images` mass converter | None, if its form extends a Page Builder background form. Otherwise, add the form fields. |
| `data-video-fallback-src` | None, if its form extends `pagebuilder_base_form_with_background_video`. Otherwise, add the form fields. |
| The `src` of its own `<img>` | A content type file and form fields |
| Any other attribute | A rewriter in `di.xml`, a content type file and form fields |

Run `bin/magento cache:flush` after each change. Page Builder caches its content type configuration.

The [examples module](https://github.com/FriendsOfMagento/module-page-builder-image-resize-examples) shows the first and third cases on the official FAQ and Quote content types.

## Names

One resize setting uses one storage key, which is also the prefix of its form fields:

| Storage key | Saved as | Read by the storefront as |
|---|---|---|
| `image_resize_desktop` | `data-image-resize-desktop` | `desktop` |
| `image_resize_mobile` | `data-image-resize-mobile` | `mobile` |
| `image_resize_fallback` | `data-image-resize-fallback` | `fallback` |

The storefront strips every `data-image-resize-*` attribute from the output.

## Background images

When Page Builder loads its configuration in the admin, `Fom\PageBuilderImageResize\Model\ContentType\ResizeAttributeInjector` checks every appearance element of every content type:

- An element with a `data-background-images` attribute gets `image_resize_desktop` and `image_resize_mobile`.
- An element with a `data-video-fallback-src` attribute gets `image_resize_fallback`.
- A converter is added for each of them.
- An attribute or converter the content type already declares under the same name is kept as is.

Forms that extend `pagebuilder_base_form_with_background_attributes` or `pagebuilder_base_form_with_background_video` already have the fields, inside the `background` fieldset. A form that builds its own background fieldset needs the fields from [Form fields](#form-fields).

To leave a content type out, add it to `excludedContentTypes` in your `etc/di.xml`:

```xml
<type name="Fom\PageBuilderImageResize\Model\ContentType\ResizeAttributeInjector">
    <arguments>
        <argument name="excludedContentTypes" xsi:type="array">
            <item name="quote" xsi:type="string">Quote_Custom</item>
        </argument>
    </arguments>
</type>
```

The forms still show the fields for an excluded content type. Remove them from its form if you exclude it.

## An image with its own uploader

### Content type

Add the resize attribute to the element that renders the `<img>`, and the converter to the same appearance. This is the FAQ Item from the examples module:

```xml
<config xmlns:xsi="http://www.w3.org/2001/XMLSchema-instance" xsi:noNamespaceSchemaLocation="urn:magento:module:Magento_PageBuilder:etc/content_type.xsd">
    <type name="faq-item">
        <appearances>
            <appearance name="default">
                <elements>
                    <element name="image">
                        <attribute name="image_resize_desktop" source="data-image-resize-desktop"/>
                    </element>
                </elements>
                <converters>
                    <converter name="image_resize_desktop" component="Fom_PageBuilderImageResize/js/mass-converter/image-resize">
                        <config>
                            <item name="attribute_name" value="image_resize_desktop"/>
                        </config>
                    </converter>
                </converters>
            </appearance>
        </appearances>
    </type>
</config>
```

Repeat this for every appearance that renders the image.

Use `image_resize_mobile` only when the content type has its own mobile image in a second `<img>`. Put it on that element, as `view/adminhtml/pagebuilder/content_type/image.xml` does for the Image content type.

The master template must output the element attributes on the `<img>`, for example `attr="data.image.attributes"`.

### Form fields

Put the fields in the fieldset of the uploader. Give the uploader a `sortOrder` and number the resize fields right after it, so they appear under its preview.

| Field | Type | Needed config |
|---|---|---|
| `<prefix>_enabled` | checkbox, component `Fom_PageBuilderImageResize/js/form/element/resize-toggle` | `resizePrefix` = `<prefix>`, `default` = `0` |
| `<prefix>_width`, `<prefix>_height` | input, component `Fom_PageBuilderImageResize/js/form/element/resize-dimension` | `resizePrefix` = `<prefix>` |
| `<prefix>_options` | collapsible fieldset | class `fom-image-resize-options` |
| `<prefix>_aspect_ratio`, `<prefix>_constrain`, `<prefix>_transparency` | checkbox, component `Fom_PageBuilderImageResize/js/form/element/use-default-checkbox` | `default` = `1` |
| `<prefix>_frame` | checkbox, component `Fom_PageBuilderImageResize/js/form/element/frame-toggle` | `dimensionPrefix` = `<prefix>`, `default` = `0` |
| `<prefix>_background` | colorPicker, component `Fom_PageBuilderImageResize/js/form/element/use-default-color-picker` | |

Rules:

- The checkbox fields use the value map `0` and `1`.
- Every field except `<prefix>_enabled` starts hidden and imports `visible` from `${ $.provider }:params.<prefix>_active`. The checkbox sets that value. The import is needed on each field, because Magento only skips validation for fields that are hidden themselves.
- Each field's `dataScope` is its name.
- The defaults must stay as listed. The storefront applies the same defaults to options that are not saved.
- The options take a `tooltip` config with `image` and `description`. The images are in `Fom_PageBuilderImageResize/images/resize/`. The option components render them in a tooltip next to the field.
- While **Keep Frame** is on, `<prefix>_frame` sets `params.<prefix>_frame_required`, and the width and the height become required.

`view/adminhtml/ui_component/pagebuilder_faq_item_form.xml` in the examples module is a complete form for one image. Copy it, then change the fieldset, the prefix and the sort orders.

To show the checkbox only with one background type, import `visible` on `<prefix>_enabled` from the background type, as `pagebuilder_base_form_with_background_video.xml` does.

## Other image attributes

The storefront side is a list of image rewriters in `Fom\PageBuilderImageResize\Model\Html\ResizeAttributeProcessor`. For each tag with resize settings, the processor removes the settings from the tag, then passes the tag and the settings to every rewriter.

| Rewriter | Rewrites |
|---|---|
| `image` | `src` of an `<img>`, from `desktop` or `mobile`, and adds `width` and `height` when the tag has none |
| `background_images` | `desktop_image` and `mobile_image` in `data-background-images` |
| `video_fallback` | `data-video-fallback-src`, from `fallback` |

For an image URL in another attribute, add a rewriter that is a virtual type of `AttributeSourceRewriter`:

```xml
<virtualType name="Vendor\Module\Model\Html\ImageRewriter\PosterRewriter"
             type="Fom\PageBuilderImageResize\Model\Html\ImageRewriter\AttributeSourceRewriter">
    <arguments>
        <argument name="attribute" xsi:type="string">data-poster-src</argument>
        <argument name="settingName" xsi:type="string">poster</argument>
    </arguments>
</virtualType>
<type name="Fom\PageBuilderImageResize\Model\Html\ResizeAttributeProcessor">
    <arguments>
        <argument name="rewriters" xsi:type="array">
            <item name="poster" xsi:type="object">Vendor\Module\Model\Html\ImageRewriter\PosterRewriter</item>
        </argument>
    </arguments>
</type>
<virtualType name="DefaultWYSIWYGValidator">
    <arguments>
        <argument name="allowedAttributes" xsi:type="array">
            <item name="data-image-resize-poster" xsi:type="string">data-image-resize-poster</item>
        </argument>
    </arguments>
</virtualType>
```

The setting name is the attribute suffix, so this rewriter reads `data-image-resize-poster`. Its storage key is `image_resize_poster`. A new resize attribute must be added to `DefaultWYSIWYGValidator`, or Magento's HTML validation flags the content on save.

To add the attribute to content types automatically, add a source to `ResizeAttributeInjector`:

```xml
<type name="Fom\PageBuilderImageResize\Model\ContentType\ResizeAttributeInjector">
    <arguments>
        <argument name="sources" xsi:type="array">
            <item name="poster" xsi:type="array">
                <item name="attribute" xsi:type="string">data-poster-src</item>
                <item name="settings" xsi:type="array">
                    <item name="image_resize_poster" xsi:type="string">data-image-resize-poster</item>
                </item>
            </item>
        </argument>
    </arguments>
</type>
```

For anything `AttributeSourceRewriter` cannot express, implement `Fom\PageBuilderImageResize\Model\Html\ImageRewriterInterface`. Use `Fom\PageBuilderImageResize\Model\Html\TagAttributes` to read and write attributes, and `Fom\PageBuilderImageResize\Model\Image\VariantGenerator` to get the resized URL.
