<?php
/**
 * GitHub Releases-based plugin updater.
 *
 * Lets WordPress offer/install updates for this plugin straight from
 * GitHub Releases, without a wordpress.org listing or a licensing server.
 *
 * @package Fanaloka\Maintenance
 */

namespace Fanaloka\Maintenance\Updater;

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * GitHubUpdater Class.
 */
class GitHubUpdater {

    /**
     * How long to cache the GitHub API response, in seconds.
     */
    private const CACHE_TTL = 6 * HOUR_IN_SECONDS;

    /**
     * Main plugin file path (the one with the plugin header).
     *
     * @var string
     */
    private string $plugin_file;

    /**
     * Plugin basename, e.g. "fanaloka-maintenance-backup/fanaloka-maintenance.php".
     *
     * @var string
     */
    private string $plugin_basename;

    /**
     * "owner/repo" on GitHub.
     *
     * @var string
     */
    private string $repo;

    /**
     * Currently installed version (from the plugin header).
     *
     * @var string
     */
    private string $current_version;

    /**
     * @param string $plugin_file     Absolute path to the main plugin file (__FILE__).
     * @param string $repo            "owner/repo" on GitHub.
     * @param string $current_version Currently installed version.
     */
    public function __construct( string $plugin_file, string $repo, string $current_version ) {
        $this->plugin_file     = $plugin_file;
        $this->plugin_basename = plugin_basename( $plugin_file );
        $this->repo            = $repo;
        $this->current_version = $current_version;
    }

    /**
     * Register WordPress hooks.
     *
     * @return void
     */
    public function init(): void {
        add_filter( 'pre_set_site_transient_update_plugins', [ $this, 'check_for_update' ] );
        add_filter( 'plugins_api', [ $this, 'plugin_info' ], 10, 3 );
        add_filter( 'upgrader_source_selection', [ $this, 'fix_source_folder_name' ], 10, 4 );
        add_action( 'upgrader_process_complete', [ $this, 'clear_cache' ], 10, 2 );
    }

    /**
     * Fetch the latest release from GitHub, using a cached copy when available.
     *
     * @return array<string, mixed>|null
     */
    private function get_latest_release(): ?array {
        $cache_key = 'fm_github_release_' . md5( $this->repo );
        $cached    = get_transient( $cache_key );

        if ( false !== $cached ) {
            return is_array( $cached ) ? $cached : null;
        }

        $response = wp_remote_get(
            sprintf( 'https://api.github.com/repos/%s/releases/latest', $this->repo ),
            [
                'headers' => [
                    'Accept'     => 'application/vnd.github+json',
                    'User-Agent' => 'FanalokaMaintenanceManager',
                ],
                'timeout' => 10,
            ]
        );

        if ( is_wp_error( $response ) || 200 !== wp_remote_retrieve_response_code( $response ) ) {
            // Cache the miss briefly too, so a flaky API doesn't get hit on every admin page load.
            set_transient( $cache_key, '', 15 * MINUTE_IN_SECONDS );
            return null;
        }

        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( ! is_array( $data ) || empty( $data['tag_name'] ) ) {
            set_transient( $cache_key, '', 15 * MINUTE_IN_SECONDS );
            return null;
        }

        set_transient( $cache_key, $data, self::CACHE_TTL );

        return $data;
    }

    /**
     * Clear the cached release data (e.g. right after an update completes).
     *
     * @return void
     */
    public function clear_cache(): void {
        delete_transient( 'fm_github_release_' . md5( $this->repo ) );
    }

    /**
     * Inject update info into the plugin-update transient checked by WordPress.
     *
     * @param object $transient The update_plugins transient.
     * @return object
     */
    public function check_for_update( $transient ) {
        if ( empty( $transient->checked ) ) {
            return $transient;
        }

        $release = $this->get_latest_release();

        if ( null === $release ) {
            return $transient;
        }

        $remote_version = ltrim( (string) $release['tag_name'], 'vV' );

        if ( ! version_compare( $remote_version, $this->current_version, '>' ) ) {
            return $transient;
        }

        $item = (object) [
            'slug'        => dirname( $this->plugin_basename ),
            'plugin'      => $this->plugin_basename,
            'new_version' => $remote_version,
            'url'         => $release['html_url'] ?? "https://github.com/{$this->repo}",
            'package'     => $release['zipball_url'] ?? '',
            'tested'      => get_bloginfo( 'version' ),
        ];

        $transient->response[ $this->plugin_basename ] = $item;

        return $transient;
    }

    /**
     * Supply data for the "View version x.y.z details" modal.
     *
     * @param false|object|array $result Existing result.
     * @param string             $action Requested plugins_api action.
     * @param object             $args   Request args.
     * @return false|object
     */
    public function plugin_info( $result, $action, $args ) {
        if ( 'plugin_information' !== $action || ( $args->slug ?? '' ) !== dirname( $this->plugin_basename ) ) {
            return $result;
        }

        $release = $this->get_latest_release();

        if ( null === $release ) {
            return $result;
        }

        return (object) [
            'name'          => 'Fanaloka Maintenance Manager',
            'slug'          => dirname( $this->plugin_basename ),
            'version'       => ltrim( (string) $release['tag_name'], 'vV' ),
            'author'        => '<a href="https://fanaloka.com">Fanaloka</a>',
            'homepage'      => "https://github.com/{$this->repo}",
            'sections'      => [
                'description' => wpautop( wp_kses_post( $release['body'] ?? 'See the GitHub release notes.' ) ),
            ],
            'download_link' => $release['zipball_url'] ?? '',
        ];
    }

    /**
     * GitHub's release zipball extracts into a folder named
     * "{owner}-{repo}-{short-sha}", not the plugin's own slug. Rename it so
     * WordPress replaces the existing plugin directory instead of installing
     * a differently-named copy alongside it.
     *
     * @param string       $source        Path to the extracted package.
     * @param string       $remote_source Path to the parent temp directory.
     * @param \WP_Upgrader $upgrader      Upgrader instance.
     * @param array        $hook_extra    Extra args, includes 'plugin' when updating a plugin.
     * @return string|\WP_Error
     */
    public function fix_source_folder_name( $source, $remote_source, $upgrader, $hook_extra = [] ) {
        if ( ( $hook_extra['plugin'] ?? '' ) !== $this->plugin_basename ) {
            return $source;
        }

        $target_slug = dirname( $this->plugin_basename );
        $target_path = trailingslashit( $remote_source ) . $target_slug;

        if ( untrailingslashit( $source ) === untrailingslashit( $target_path ) ) {
            return $source;
        }

        global $wp_filesystem;

        if ( ! $wp_filesystem || ! $wp_filesystem->move( $source, $target_path ) ) {
            return new \WP_Error(
                'fm_rename_failed',
                __( 'Could not rename the downloaded GitHub package to the plugin folder.', 'fanaloka-maintenance' )
            );
        }

        return $target_path;
    }
}
