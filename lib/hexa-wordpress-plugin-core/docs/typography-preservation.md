# Typography Preservation

`Hexa\PluginCore\Typography\TypographyPreservation` defines reusable, prefix-scoped settings for preserving font family, font size, font color, and font weight while a host applies a visual template.

Use `defaults()` when defining host settings and `setting_keys()` when building persistence allowlists. Use `values()` or `preserves()` when deciding which CSS declarations the host should emit.

`Hexa\PluginCore\WpAdminComponents\TypographyPreservationControl` renders all four toggles. Place the component inside an element with `data-hpc-typography-scope` and pass target setting keys for controls that should be disabled while a value is preserved.

`Hexa\PluginCore\WpAdminComponents\TypographyControl` is the preferred complete UI. It composes the Core font family, font weight, color, and size fields into one control and places each preservation toggle beside the field it governs. A host supplies setting keys, values, labels, save classes, and optional field limits; it does not rebuild the layout.

The color preservation toggle is part of the Core color heading, so it remains attached to the picker when the detailed color values wrap on narrower screens.

Core adds a prefix-scoped class such as `hpc-typography-article-heading-preserve-font-family` to the scope and dispatches `hexa-typography-preserve-change`. Host plugins keep responsibility for AJAX persistence, template selectors, and the CSS declarations specific to their output.
