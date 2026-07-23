<?php

namespace Hexa\PluginCore\WpAdminComponents;

use Hexa\PluginCore\Typography\TypographyPreservation;

final class TypographyControl {
    public static function render( array $args ): string {
        $prefix = sanitize_key( (string) ( $args["prefix"] ?? "" ) );
        if ( "" === $prefix ) {
            return "";
        }

        $settings = isset( $args["settings"] ) && is_array( $args["settings"] ) ? $args["settings"] : [];
        $defaults = $args["defaults"] ?? true;
        $input_class = (string) ( $args["input_class"] ?? "" );
        $title = (string) ( $args["title"] ?? "Typography" );
        $description = (string) ( $args["description"] ?? "Choose typography values or keep the current theme values." );
        $control_class = trim( "hpc-typography-control " . (string) ( $args["control_class"] ?? "" ) );
        $family = self::config( $args["font_family"] ?? [] );
        $weight = self::config( $args["font_weight"] ?? [] );
        $color = self::config( $args["font_color"] ?? [] );
        $sizes = self::configs( $args["font_size"] ?? [] );
        if ( [] === $family ) {
            $weight = [];
        }
        $properties = [];

        if ( [] !== $family ) {
            $properties[] = "font_family";
        }
        if ( [] !== $weight ) {
            $properties[] = "font_weight";
        }
        if ( [] !== $color ) {
            $properties[] = "font_color";
        }
        if ( [] !== $sizes ) {
            $properties[] = "font_size";
        }
        if ( [] === $properties ) {
            return "";
        }

        $values = TypographyPreservation::values( $settings, $prefix, $defaults, $properties );
        ob_start();
        CoreUi::render_assets();
        $html = (string) ob_get_clean() . TypographyPreservationControl::assets() . self::assets();
        $html .= '<section class="' . esc_attr( $control_class ) . '" data-hpc-typography-control data-hpc-typography-prefix="' . esc_attr( $prefix ) . '">';
        $html .= '<header class="hpc-typography-control-head"><h3>' . esc_html( $title ) . '</h3>';
        if ( "" !== $description ) {
            $html .= '<p>' . esc_html( $description ) . '</p>';
        }
        $html .= '</header><div class="hpc-typography-control-fields">';

        if ( [] !== $family ) {
            $family["value"] = (string) ( $family["value"] ?? $settings[ $family["key"] ] ?? "template" );
            $family["select_class"] = trim( (string) ( $family["select_class"] ?? $input_class ) );
            $family["family_action_html"] = self::toggle( $prefix, "font_family", $values, $family, $input_class );
            if ( [] !== $weight ) {
                $family["weight_key"] = $weight["key"];
                $family["weight_value"] = (string) ( $weight["value"] ?? $settings[ $weight["key"] ] ?? "inherit" );
                $family["weight_label"] = (string) ( $weight["label"] ?? "Font weight" );
                $family["weight_select_class"] = trim( (string) ( $weight["select_class"] ?? $input_class ) );
                $family["weight_action_html"] = self::toggle( $prefix, "font_weight", $values, $weight, $input_class );
            }
            $html .= '<div class="hpc-typography-control-block hpc-typography-control-font">' . FontFamilyControl::render( $family ) . '</div>';
        }

        if ( [] !== $color ) {
            $color["value"] = (string) ( $color["value"] ?? $settings[ $color["key"] ] ?? $color["default"] ?? "#2d5277" );
            $color["hex_input_class"] = trim( (string) ( $color["hex_input_class"] ?? $input_class ) );
            $color["header_action_html"] = self::toggle( $prefix, "font_color", $values, $color, $input_class );
            $html .= '<div class="hpc-typography-control-block">' . ColorControl::render( $color ) . '</div>';
        }

        if ( [] !== $sizes ) {
            $html .= '<div class="hpc-typography-control-block hpc-typography-control-row"><div class="hpc-typography-number-fields">';
            $size_targets = [];
            foreach ( $sizes as $size ) {
                $key = $size["key"];
                $min = isset( $size["min"] ) ? (int) $size["min"] : 8;
                $max = isset( $size["max"] ) ? (int) $size["max"] : 200;
                $value = isset( $size["value"] ) ? (int) $size["value"] : (int) ( $settings[ $key ] ?? $min );
                $value = max( $min, min( $max, $value ) );
                $size_targets[] = $key;
                $html .= '<div class="hpc-typography-number-control"><div class="hpc-typography-number-head"><h3>' . esc_html( (string) ( $size["label"] ?? "Font size" ) ) . '</h3>';
                if ( ! empty( $size["description"] ) ) {
                    $html .= '<p>' . esc_html( (string) $size["description"] ) . '</p>';
                }
                $html .= '</div><label class="hpc-typography-number-field"><input type="number" min="' . esc_attr( (string) $min ) . '" max="' . esc_attr( (string) $max ) . '" step="' . esc_attr( (string) ( $size["step"] ?? 1 ) ) . '" class="' . esc_attr( trim( "hpc-typography-number-input " . (string) ( $size["input_class"] ?? $input_class ) ) ) . '" data-key="' . esc_attr( $key ) . '" value="' . esc_attr( (string) $value ) . '"><span>' . esc_html( (string) ( $size["suffix"] ?? "" ) ) . '</span></label></div>';
            }
            $size_toggle = $sizes[0];
            $size_toggle["targets"] = $size_targets;
            $html .= '</div><div class="hpc-typography-control-action">' . self::toggle( $prefix, "font_size", $values, $size_toggle, $input_class ) . '</div></div>';
        }

        return $html . '</div></section>';
    }

    private static function toggle( string $prefix, string $property, array $values, array $config, string $input_class ): string {
        $targets = array_key_exists( "targets", $config )
            ? (array) $config["targets"]
            : ( ! array_key_exists( "disable_when_preserved", $config ) || ! empty( $config["disable_when_preserved"] ) ? [ $config["key"] ] : [] );

        return TypographyPreservationControl::render_toggle(
            [
                "prefix" => $prefix,
                "property" => $property,
                "checked" => ! empty( $values[ $property ] ),
                "label" => (string) ( $config["preserve_label"] ?? self::preserve_label( $property ) ),
                "targets" => $targets,
                "input_class" => $input_class,
                "class" => "hpc-typography-adjacent-toggle",
            ]
        );
    }

    private static function preserve_label( string $property ): string {
        return [
            "font_family" => "Leave font as is",
            "font_size" => "Leave font size as is",
            "font_color" => "Leave font color as is",
            "font_weight" => "Leave font weight as is",
        ][ $property ] ?? "Leave as is";
    }

    private static function config( $config ): array {
        return is_array( $config ) && isset( $config["key"] ) && "" !== sanitize_key( (string) $config["key"] )
            ? array_merge( $config, [ "key" => sanitize_key( (string) $config["key"] ) ] )
            : [];
    }

    private static function configs( $configs ): array {
        if ( ! is_array( $configs ) || [] === $configs ) {
            return [];
        }
        if ( isset( $configs["key"] ) ) {
            $configs = [ $configs ];
        }
        return array_values( array_filter( array_map( [ self::class, "config" ], $configs ) ) );
    }

    private static function assets(): string {
        static $rendered = false;
        if ( $rendered ) {
            return "";
        }
        $rendered = true;

        return <<<'HTML'
<style>.hpc-typography-control{border:1px solid #d8dee8;border-radius:6px;display:grid;gap:0;overflow:hidden}.hpc-typography-control-head{padding:14px 16px}.hpc-typography-control-head h3{font-size:15px;letter-spacing:0;margin:0 0 4px}.hpc-typography-control-head p{color:#64748b;margin:0}.hpc-typography-control-block{border-top:1px solid #e4e9f0;padding:16px}.hpc-typography-control-row{align-items:end;display:flex;flex-wrap:wrap;gap:18px}.hpc-typography-control-main{min-width:0}.hpc-typography-control-action{align-items:center;display:flex;min-height:40px;white-space:nowrap}.hpc-typography-number-fields{display:flex;flex-wrap:wrap;gap:18px}.hpc-typography-number-control{display:grid;gap:9px}.hpc-typography-number-head h3{font-size:13px;letter-spacing:0;margin:0 0 4px;text-transform:uppercase}.hpc-typography-number-head p{color:#64748b;margin:0}.hpc-typography-number-field{align-items:center;display:flex;gap:8px}.hpc-typography-number-input{background:#fff;border:1px solid #a9b4c3;border-radius:6px;min-height:40px;padding:8px 10px;width:110px}.hpc-typography-adjacent-toggle{margin:0}@media(max-width:900px){.hpc-typography-control-action{min-height:0;white-space:normal}}</style>
HTML;
    }
}
