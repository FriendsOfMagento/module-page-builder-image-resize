# Changelog

All notable changes to this project are documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.1.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## [Unreleased]

## [1.0.0] - 2026-11-07

### Added

- Per-image resize settings under each Page Builder image uploader: desktop and mobile images of Image, Row, Banner, Slide, Column, Column Group and Tab Item, and the video fallback image of Row, Banner and Slide.
- Width and height, plus the native options Keep Aspect Ratio, Keep Frame, Do Not Upscale, Keep Transparency and Background Color, each with **Use Default Value** and a tooltip that shows its effect.
- Image URLs replaced on the server before Page Builder renders the page, so themes need no changes. A screen without its own settings gets the original file.
- Resized files in `pub/media/pagebuilder/cache`, hidden from the Media Gallery. They are regenerated when the source file changes and removed by **Flush Catalog Images Cache**.
- The original image kept, and the reason logged, for URLs outside the media URL or in private media folders, for missing or unsupported sources, and for a source or result above the pixel limit.
- Resize settings added automatically to third-party content types with Page Builder background images.
- `ImageRewriterInterface` and a guide for adding resize support to other content types, in [docs/extending.md](docs/extending.md).
