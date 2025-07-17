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
        ?string $unfiltered_value,
    ) {
        $min_value = $option_spec->min_value;

        if ( $blob_value === null ) {
            $blob_value = $option_spec->default_blob_value;
        }

        if ( $unfiltered_value === null ) {
            $unfiltered_value = $option_spec->default_value;
        }

        $this->blob_value = $blob_value;
        $this->option_spec = $option_spec;
        $this->unfiltered_blob_value = $blob_value;

        // If value is not in allowed values, set to default value
        $allowed_values = $this->option_spec->allowed_values;
        if ( $allowed_values !== null && ! in_array( $unfiltered_value, $allowed_values, true ) ) {
            WsLog::w(
                "Value $unfiltered_value not in allowed values" .
                " for option {$this->option_spec->name}." .
                " Setting to default value {$this->option_spec->default_value}",
            );
            $unfiltered_value = $this->option_spec->default_value;
        }

        $unfiltered_value = $unfiltered_value;
        if ( $min_value !== null && intval( $unfiltered_value ) < $min_value ) {
            $unfiltered_value = (string) $min_value;
        }
        $this->unfiltered_value = $unfiltered_value;

        $value = $unfiltered_value;

        if ( $this->option_spec->type === 'password' ) {
            $value = Options::encrypt_decrypt( 'decrypt', $value );
        }

        // default deploymentURL is '/', else remove trailing slash
        if ( $this->option_spec->name === 'deploymentURL' ) {
            if ( $value !== '/' ) {
                $value = untrailingslashit( $value );
            }
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
