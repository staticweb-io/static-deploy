<?php
/**
 * OptionData
 *
 * Specifies an option that has a configured or
 * default value.
 */

declare(strict_types=1);

namespace StaticDeploy;

final class OptionData {

    // TODO: Add readonly keyword to all properties
    // once we can strictly require PHP 8.1
    public ?string $blob_value;
    public OptionSpec $option_spec;
    public ?string $unfiltered_blob_value;
    public string $unfiltered_value;
    public string $value;

    public function __construct(
        OptionSpec $option_spec,
        ?string $blob_value,
        string $unfiltered_value,
    ) {
        $min_value = $option_spec->min_value;

        $this->blob_value = $blob_value;
        $this->option_spec = $option_spec;
        $this->unfiltered_blob_value = $blob_value;

        $unfiltered_value = $unfiltered_value;
        if ( $min_value !== null && intval( $unfiltered_value ) < $min_value ) {
            $unfiltered_value = (string) $min_value;
        }
        $this->unfiltered_value = $unfiltered_value;

        $value = $unfiltered_value;

        if ( $this->option_spec->type === 'password' ) {
            $value = Options::encrypt_decrypt( 'decrypt', $value );
        }

        $value = apply_filters(
            $this->option_spec->filter_name,
            $value
        );

        if ( $min_value !== null && intval( $value ) < $min_value ) {
            $value = (string) $min_value;
        }

        $this->value = $value;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'blob_value' => $this->blob_value,
            'option_spec' => $this->option_spec->toArray(),
            'unfiltered_value' => $this->unfiltered_value,
            'value' => $this->value,
        ];
    }
}
