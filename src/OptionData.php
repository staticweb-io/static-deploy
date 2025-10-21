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
    public const CACHE_GROUP = 'static_deploy_option_data';
    // It's very cheap to fetch options, so there is no point
    // caching them for long.
    public const CACHE_TTL_SEC = 300;

    public readonly ?string $blob_value;
    public readonly OptionSpec $option_spec;
    public readonly ?string $unfiltered_blob_value;
    public readonly string $unfiltered_value;
    public readonly string $value;

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

        if ( $option_spec->hasBlobValue() ) {
            if ( $blob_value === null ) {
                $msg = 'Option ' . $option_spec->name .
                    ' must have a blob value, but a blob value was not provided.';
                if ( defined( 'STATIC_DEPLOY_ESCAPE_EXCEPTIONS' )
                && STATIC_DEPLOY_ESCAPE_EXCEPTIONS ) {
                    throw WsLog::ex( esc_html( $msg ) );
                }
                throw WsLog::ex( $msg );
            }
        } else {
            if ( $blob_value !== null && $blob_value !== '' ) {
                $msg = 'Option ' . $option_spec->name .
                    ' cannot have a blob value, but a blob value was provided.';
                if ( defined( 'STATIC_DEPLOY_ESCAPE_EXCEPTIONS' )
                && STATIC_DEPLOY_ESCAPE_EXCEPTIONS ) {
                    throw WsLog::ex( esc_html( $msg ) );
                }
                throw WsLog::ex( $msg );
            }
            // We get blank strings instead of null from MySQL,
            // so we have to set null ourselves.
            $blob_value = null;
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
        if ( $min_value !== null && intval( $unfiltered_value ) < $min_value ) {
            WsLog::w(
                "Value $unfiltered_value below min_value for option {$this->option_spec->name}." .
                " Setting to min_value $min_value",
            );
            $unfiltered_value = (string) $min_value;
        }
        $this->unfiltered_value = $unfiltered_value;

        $value = $unfiltered_value;

        if ( $this->option_spec->type === 'password' ) {
            $value = Options::encrypt_decrypt( 'decrypt', $value );
        }

        // default deploymentURL is '/', else remove trailing slash
        if ( $this->option_spec->name === 'deploymentURL' && $value !== '/' ) {
            $value = untrailingslashit( $value );
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

    public function save(): void {
        global $wpdb;

        $table_name = Options::getTableName();

        $value = $this->value;

        if ( $value !== '' && $this->option_spec->type === 'password' ) {
            $value = Options::encrypt_decrypt( 'encrypt', $value );
        }

        $updates = [
            'value' => $value,
            'blob_value' => $this->blob_value,
        ];

        if ( STATIC_DEPLOY_DEBUG ) {
            WsLog::d(
                'Saving option ' . $this->option_spec->name .
                ' with values ' . $value . ' and blob_value ' . $this->blob_value
            );
        }

        $cacheable = ! $this->option_spec->hasBlobValue();

        if ( $cacheable ) {
            wp_cache_delete(
                $this->option_spec->name . '_blob_value',
                self::CACHE_GROUP,
            );
        }

        // phpcs:ignore WordPress.DB.DirectDatabaseQuery.DirectQuery
        $wpdb->update(
            $table_name,
            $updates,
            [ 'name' => $this->option_spec->name ],
        );

        if ( $cacheable ) {
            wp_cache_set(
                $this->option_spec->name . '_blob_value',
                $this->blob_value,
                self::CACHE_GROUP,
                self::CACHE_TTL_SEC,
            );
        }
    }

    public static function fromUserInput(
        OptionSpec $option_spec,
        string $user_input,
    ) {
        $blob_value = null;

        switch ( $option_spec->type ) {
            case 'array':
                $blob_value = preg_replace(
                    '/^\s+|\s+$/m',
                    '',
                    strval( $user_input )
                );
                $value = '1';
                break;
            case 'boolean':
                $value = in_array( $user_input, [ '', '0', 'false' ], true ) ? '0' : '1';
                break;
            case 'integer':
                $value = (string) intval( $user_input );
                break;
            case 'object':
                $json = json_decode( stripcslashes( strval( $user_input ) ) );
                if ( ! is_object( $json ) ) {
                    $msg = 'Option ' . $option_spec->name . ' must be an object.';
                    if ( defined( 'STATIC_DEPLOY_ESCAPE_EXCEPTIONS' )
                    && STATIC_DEPLOY_ESCAPE_EXCEPTIONS ) {
                        throw WsLog::ex( esc_html( $msg ) );
                    }
                    throw WsLog::ex( $msg );
                }
                $blob_value = json_encode( $json );
                $value = '1';
                break;
            case 'password':
            case 'string':
                $value = sanitize_text_field( strval( $user_input ) );
                break;
            case 'url':
                $value = esc_url_raw( strval( $user_input ) );
                break;
            default:
                $msg = 'Unknown option type: ' . $option_spec->type
                    . ' for option: ' . $option_spec->name;
                if ( defined( 'STATIC_DEPLOY_ESCAPE_EXCEPTIONS' )
                && STATIC_DEPLOY_ESCAPE_EXCEPTIONS ) {
                    throw WsLog::ex( esc_html( $msg ) );
                }
                throw WsLog::ex( $msg );
        }

        return new self(
            $option_spec,
            $blob_value,
            $value,
        );
    }
}
