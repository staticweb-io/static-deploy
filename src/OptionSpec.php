<?php
/**
 * OptionSpec
 *
 * Specifies a configurable option for WP2Static
 */

declare(strict_types=1);

namespace WP2Static;

final class OptionSpec {

    public readonly string $type;
    public readonly string $name;
    public readonly string $default_value;
    public readonly string $label;
    public readonly string $description;
    public readonly ?string $default_blob_value;
    public readonly string $filter_name;
    public readonly ?int $min_value;

    public function __construct(
        string $type,
        string $name,
        string $default_value,
        string $label,
        string $description,
        ?string $default_blob_value = null,
        ?string $filter_name = null,
        ?int $min_value = null,
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->default_value = $default_value;
        $this->label = $label;
        $this->description = $description;
        $this->default_blob_value = $default_blob_value;
        $this->filter_name = $filter_name ?? Controller::getHookName( "option_{$name}" );
        $this->min_value = $min_value;
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
