<?php
/**
 * OptionSpec
 *
 * Specifies a configurable option
 */

declare(strict_types=1);

namespace StaticDeploy;

final class OptionSpec {

    public readonly string $name;

    public readonly string $default_value;

    public readonly ?array $allowed_values;

    public readonly string $filter_name;

    public readonly string $input_type;

    public function __construct(
        public readonly string $type,
        string $name,
        string $default_value,
        public readonly string $label,
        public readonly string $description,
        public readonly ?string $default_blob_value = null,
        ?string $filter_name = null,
        ?array $allowed_values = null,
        ?string $input_type = null,
        public readonly ?int $min_value = null,
        /**
         * Used to import from WP2Static options
         */
        public readonly ?string $wp2static_name = null,
        /**
         * Used to import from WP2Static options
         */
        public readonly ?string $wp2static_table = null,
    ) {
        if ( $allowed_values !== null && ! in_array( $default_value, $allowed_values, true ) ) {
            $msg = "Default value {$default_value} not in allowed values for option {$name}";
            if ( defined( 'STATIC_DEPLOY_ESCAPE_EXCEPTIONS' ) && STATIC_DEPLOY_ESCAPE_EXCEPTIONS ) {
                throw WsLog::ex( esc_html( $msg ) );
            }
            throw WsLog::ex( $msg );
        }

        if ( $input_type === null && $allowed_values !== null ) {
            $input_type = 'select';
        }
        $this->name = $name;
        $this->default_value = $default_value;
        $this->allowed_values = $allowed_values;
        $this->filter_name = $filter_name ?? Controller::getHookName( "option_{$name}" );
        $this->input_type = $input_type ?? $this->type;
    }

    public function hasBlobValue(): bool {
        return $this->type === 'array' || $this->type === 'object';
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
