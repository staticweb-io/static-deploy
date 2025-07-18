<?php
/**
 * OptionSpec
 *
 * Specifies a configurable option
 */

declare(strict_types=1);

namespace StaticDeploy;

final class OptionSpec {

    public readonly string $type;
    public readonly string $name;
    public readonly string $default_value;
    public readonly string $label;
    public readonly ?array $allowed_values;
    public readonly string $description;
    public readonly ?string $default_blob_value;
    public readonly string $filter_name;
    public readonly string $input_type;
    public readonly ?int $min_value;

    // Used to import from WP2Static options
    public readonly ?string $wp2static_name;
    public readonly ?string $wp2static_table;

    public function __construct(
        string $type,
        string $name,
        string $default_value,
        string $label,
        string $description,
        ?string $default_blob_value = null,
        ?string $filter_name = null,
        ?array $allowed_values = null,
        ?string $input_type = null,
        ?int $min_value = null,
        ?string $wp2static_name = null,
        ?string $wp2static_table = null,
    ) {
        if ( $allowed_values !== null && ! in_array( $default_value, $allowed_values ) ) {
            throw WsLog::ex(
                "Default value $default_value not in allowed values for option $name"
            );
        }

        if ( $input_type === null && $allowed_values !== null ) {
            $input_type = 'select';
        }

        $this->type = $type;
        $this->name = $name;
        $this->default_value = $default_value;
        $this->label = $label;
        $this->allowed_values = $allowed_values;
        $this->description = $description;
        $this->default_blob_value = $default_blob_value;
        $this->filter_name = $filter_name ?? Controller::getHookName( "option_{$name}" );
        $this->input_type = $input_type ?? $type;
        $this->min_value = $min_value;
        $this->wp2static_name = $wp2static_name;
        $this->wp2static_table = $wp2static_table;
    }

    /**
     * @return array<string, ?string>
     */
    public function toArray(): array
    {
        return [
            'type' => $this->type,
            'name' => $this->name,
            'default_value' => $this->default_value,
            'label' => $this->label,
            'description' => $this->description,
            'default_blob_value' => $this->default_blob_value,
            'filter_name' => $this->filter_name,
            'min_value' => $this->min_value,
        ];
    }
}
