<?php

namespace StaticDeploy;

class OptionRenderer {

    const INPUT_TYPE_FNS = [
        'array' => 'optionInputArray',
        'boolean' => 'optionInputBoolean',
        'integer' => 'optionInputInteger',
        'object' => 'optionInputObject',
        'password' => 'optionInputPassword',
        'select' => 'optionInputSelect',
        'string' => 'optionInputString',
    ];

    const KSES_ALLOWED_HTML = [
        'br' => [],
        'input' => [
            'class' => [],
            'id' => [],
            'name' => [],
            'type' => [],
            'value' => [],
        ],
        'label' => [
            'for' => [],
            'style' => [],
        ],
        'option' => [
            'selected' => [],
            'value' => [],
        ],
        'select' => [
            'class' => [],
            'id' => [],
            'name' => [],
        ],
        'textarea' => [
            'class' => [],
            'cols' => [],
            'id' => [],
            'name' => [],
            'rows' => [],
        ],
    ];

    public static function echoInput( OptionData $option_data ): void {
        if ( defined( 'STATIC_DEPLOY_WP_ORG_MODE' ) && STATIC_DEPLOY_WP_ORG_MODE ) {
            // This is completely unnecessary but is required by
            // the Plugin Check Plugin, so we have to take the performance
            // hit.
            echo wp_kses(
                self::optionInput( $option_data ),
                self::KSES_ALLOWED_HTML,
            );
        } else {
            echo self::optionInput( $option_data );
        }
    }

    public static function echoLabel(
        OptionData $option_data,
        bool $description = false,
    ): void {
        if ( defined( 'STATIC_DEPLOY_WP_ORG_MODE' ) && STATIC_DEPLOY_WP_ORG_MODE ) {
            // This is completely unnecessary but is required by
            // the Plugin Check Plugin, so we have to take the performance
            // hit.
            echo wp_kses(
                self::optionLabel( $option_data, $description ),
                self::KSES_ALLOWED_HTML,
            );
        } else {
            echo self::optionLabel( $option_data, $description );
        }
    }

    public static function optionInput( OptionData $option ): string {
        $option_input = call_user_func(
            [
                self::class,
                self::INPUT_TYPE_FNS[ $option->option_spec->input_type ],
            ],
            $option
        );

        return strval( $option_input );
    }

    public static function optionInputArray( OptionData $option ): string {
        return '<textarea class="widefat" cols=30 rows=10 id="' . $option->option_spec->name .
            '" name="' . $option->option_spec->name . '">' .
            $option->blob_value .
            '</textarea>';
    }

    public static function optionInputBoolean( OptionData $option ): string {
        $checked = (int) $option->unfiltered_value === 1 ? ' checked' : '';
        return '<input id="' . $option->option_spec->name .
            '" name="' . $option->option_spec->name . '" value="1"' .
            ' type="checkbox"' . $checked . '>';
    }

    public static function optionInputInteger( OptionData $option ): string {
        return '<input class="widefat" id="' . $option->option_spec->name .
            '" name="' . $option->option_spec->name .
            '" type="number" value="' . esc_html( strval( $option->unfiltered_value ) ) . '">';
    }

    public static function optionInputObject( OptionData $option ): string {
        return '<textarea class="widefat" cols=30 rows=10 id="' . $option->option_spec->name .
            '" name="' . $option->option_spec->name . '">' .
            $option->blob_value .
            '</textarea>';
    }

    public static function optionInputPassword( OptionData $option ): string {
        $decrypted = Options::encrypt_decrypt( 'decrypt', $option->unfiltered_value );
        return '<input class="widefat" id="' . $option->option_spec->name .
            '" name="' . $option->option_spec->name .
            '" type="password" value="' . esc_html( strval( $decrypted ) ) . '">';
    }

    public static function optionInputSelect( OptionData $option ): string {
        $options = [];
        foreach ( $option->option_spec->allowed_values as $value ) {
            $options[] = '<option value="' . $value . '"' .
                ( $value === $option->unfiltered_value ? ' selected' : '' ) .
                '>' . $value . '</option>';
        }

        return '<select id="' . $option->option_spec->name .
            '" name="' . $option->option_spec->name . '">' .
            implode( '', $options ) .
            '</select>';
    }

    public static function optionInputString( OptionData $option ): string {
        return '<input class="widefat" id="' . $option->option_spec->name .
            '" name="' . $option->option_spec->name .
            '" type="text" value="' . esc_html( strval( $option->unfiltered_value ) ) . '">';
    }

    public static function optionLabel( OptionData $option, bool $description = false ): string {
        $descr = $description && $option->option_spec->description
            ? '<br>' . $option->option_spec->description
            : '';
        return '<label for="' . $option->option_spec->name . '" style="font-weight: bold">' .
                $option->option_spec->label . '</label>' . $descr;
    }
}
