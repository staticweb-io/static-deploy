<?php
/**
 * OptionSpec
 *
 * Specifies a configurable option
 */

declare(strict_types=1);

namespace StaticDeploy;

final class OptionSpec {

    // TODO: Add readonly keyword to all properties
    // once we can strictly require PHP 8.1
    public string $type;
    public string $name;
    public string $default_value;
    public string $label;
    public string $description;
    public ?string $default_blob_value;
    public string $filter_name;
    public ?int $min_value;

    // Used to import from WP2Static options
    public ?string $wp2static_name;
    public ?string $wp2static_table;

    public function __construct(
        string $type,
        string $name,
        string $default_value,
        string $label,
        string $description,
        ?string $default_blob_value = null,
        ?string $filter_name = null,
        ?int $min_value = null,
        ?string $wp2static_name = null,
        ?string $wp2static_table = null,
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->default_value = $default_value;
        $this->label = $label;
        $this->description = $description;
        $this->default_blob_value = $default_blob_value;
        $this->filter_name = $filter_name ?? Controller::getHookName( "option_{$name}" );
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
