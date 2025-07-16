<?php

namespace StaticDeploy;

class OptionRenderer {

    const INPUT_TYPE_FNS = [
        'array' => 'optionInputArray',
        'boolean' => 'optionInputBoolean',
        'integer' => 'optionInputInteger',
        'password' => 'optionInputPassword',
        'string' => 'optionInputString',
    ];

    public static function optionInput( OptionData $option ): string {
        $option_input = call_user_func(
            [
                'StaticDeploy\OptionRenderer',
                self::INPUT_TYPE_FNS[ $option->option_spec->type ],
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

    public static function optionInputPassword( OptionData $option ): string {
        $decrypted = Options::encrypt_decrypt( 'decrypt', $option->unfiltered_value );
        return '<input class="widefat" id="' . $option->option_spec->name .
            '" name="' . $option->option_spec->name .
            '" type="password" value="' . esc_html( strval( $decrypted ) ) . '">';
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
