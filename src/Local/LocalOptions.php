<?php

namespace StaticDeploy\Local;

use StaticDeploy\Controller;
use StaticDeploy\OptionsControllerTrait;
use StaticDeploy\OptionSpec;

/*
 * Options for the local deployer
*/
class LocalOptions {

    use OptionsControllerTrait;

    /**
     * @var array<string, OptionSpec>
     */
    private static ?array $cached_option_specs = null;

    public static function getAdminAction(): string {
        return Controller::getHookName( 'local_save_options' );
    }

    public static function getOptionsPageSlug(): string {
        return 'static-deploy-addon-local';
    }

    /**
     * Returns namespaced option name from a slug
     *
     * @var string $slug
     */

    public static function getName( string $slug ): string {
        return 'local_' . $slug;
    }

    /**
     * @return array<string, OptionSpec>
     */
    public static function getSpecs(): array {
        if ( isset( self::$cached_option_specs ) ) {
            return self::$cached_option_specs;
        }

        $specs = [
            new OptionSpec(
                'string',
                self::getName( 'dirPath' ),
                '',
                'Directory path',
                'Path to a local directory where static files will be written.' .
                ' It must be a directory outside of the WordPress installation.',
            ),
        ];

        $ret = [];
        foreach ( $specs as $spec ) {
            $ret[ $spec->name ] = $spec;
        }
        self::$cached_option_specs = $ret;
        return $ret;
    }

    /**
     * @return array<string, array<int, array<string, string[]|string>>|string>
     */
    public static function getPageData(): array {
        return [
            'title' => 'Local Deployment Options',
            'sections' => [
                [
                    'title' => 'Local',
                    'options' => [
                        self::getName( 'dirPath' ),
                    ],
                ],
            ],
        ];
    }
}
