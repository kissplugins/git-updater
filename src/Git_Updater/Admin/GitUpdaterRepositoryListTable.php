<?php
/**
 * Enhanced Repository List Table for Git Updater.
 *
 * @package Git_Updater\Admin
 */

namespace Fragen\Git_Updater\Admin;

use WP_List_Table;
use Fragen\Git_Updater\FSM\GitUpdaterStateManager;
use Fragen\Git_Updater\Enums\PluginState;

/**
 * Git Updater Repository List Table class.
 * 
 * Replaces Git Updater's basic forms with an advanced List Table interface
 * featuring real-time state management, batch operations, and enhanced UX.
 */
class GitUpdaterRepositoryListTable extends WP_List_Table {

    /**
     * State manager instance.
     *
     * @var GitUpdaterStateManager
     */
    private GitUpdaterStateManager $state_manager;

    /**
     * Current organization/user for repository fetching.
     *
     * @var string
     */
    private string $organization = '';

    /**
     * Repository data cache.
     *
     * @var array
     */
    private array $repositories = [];

    /**
     * Constructor.
     *
     * @param GitUpdaterStateManager $state_manager State manager instance.
     */
    public function __construct(GitUpdaterStateManager $state_manager) {
        $this->state_manager = $state_manager;

        parent::__construct([
            'singular' => 'repository',
            'plural'   => 'repositories',
            'ajax'     => true,
        ]);
    }

    /**
     * Set organization for repository fetching.
     *
     * @param string $organization GitHub organization/user name.
     */
    public function set_organization(string $organization): void {
        $this->organization = $organization;
    }

    /**
     * Set repository data.
     *
     * @param array $repositories Repository data array.
     */
    public function set_repositories(array $repositories): void {
        $this->repositories = $repositories;
    }

    /**
     * Get table columns.
     *
     * @return array Column definitions.
     */
    public function get_columns(): array {
        return [
            'cb'                => '<input type="checkbox" />',
            'name'              => __('Repository', 'git-updater'),
            'description'       => __('Description', 'git-updater'),
            'installation_method' => __('Installation Method', 'git-updater'),
            'state'             => __('Status', 'git-updater'),
            'current_branch'    => __('Branch', 'git-updater'),
            'last_updated'      => __('Last Updated', 'git-updater'),
            'actions'           => __('Actions', 'git-updater'),
        ];
    }

    /**
     * Get sortable columns.
     *
     * @return array Sortable column definitions.
     */
    public function get_sortable_columns(): array {
        return [
            'name'         => ['name', false],
            'last_updated' => ['last_updated', true],
            'state'        => ['state', false],
        ];
    }

    /**
     * Get bulk actions.
     *
     * @return array Bulk action definitions.
     */
    public function get_bulk_actions(): array {
        return [
            'install_selected'   => __('Install Selected', 'git-updater'),
            'update_selected'    => __('Update Selected', 'git-updater'),
            'activate_selected'  => __('Activate Selected', 'git-updater'),
            'deactivate_selected' => __('Deactivate Selected', 'git-updater'),
            'refresh_selected'   => __('Refresh Status', 'git-updater'),
        ];
    }

    /**
     * Prepare table items.
     */
    public function prepare_items(): void {
        // Process repositories with state information
        $processed_items = [];
        foreach ($this->repositories as $repo) {
            $processed_items[] = $this->process_repository($repo);
        }

        // Handle sorting
        $orderby = $_GET['orderby'] ?? 'last_updated';
        $order = $_GET['order'] ?? 'desc';
        
        usort($processed_items, function($a, $b) use ($orderby, $order) {
            $result = 0;
            
            switch ($orderby) {
                case 'name':
                    $result = strcmp($a['name'], $b['name']);
                    break;
                case 'last_updated':
                    $result = strtotime($a['updated_at']) - strtotime($b['updated_at']);
                    break;
                case 'state':
                    $result = strcmp($a['state']->value, $b['state']->value);
                    break;
            }
            
            return ($order === 'asc') ? $result : -$result;
        });

        // Handle pagination
        $per_page = 20;
        $current_page = $this->get_pagenum();
        $total_items = count($processed_items);

        $this->set_pagination_args([
            'total_items' => $total_items,
            'per_page'    => $per_page,
            'total_pages' => ceil($total_items / $per_page),
        ]);

        $this->items = array_slice($processed_items, ($current_page - 1) * $per_page, $per_page);

        $this->_column_headers = [
            $this->get_columns(),
            [],
            $this->get_sortable_columns(),
        ];
    }

    /**
     * Process repository data with state information.
     *
     * @param array $repo Repository data.
     * @return array Processed repository data.
     */
    private function process_repository(array $repo): array {
        $repo_url = $repo['html_url'] ?? $repo['clone_url'] ?? '';
        $state = $this->state_manager->get_state($repo_url);
        $metadata = $this->state_manager->get_metadata($repo_url);

        // Determine installation method
        $installation_method = 'standard';
        if ($state->isGitUpdaterManaged()) {
            $installation_method = 'git_updater';
        }

        // Get current branch
        $current_branch = $metadata['current_branch'] ?? $repo['default_branch'] ?? 'main';

        // Check if plugin is installed
        $plugin_file = $this->find_installed_plugin($repo['name']);
        $is_installed = !empty($plugin_file);
        $is_active = $is_installed && is_plugin_active($plugin_file);

        return [
            'id'                  => $repo['id'],
            'name'                => $repo['name'],
            'full_name'           => $repo['full_name'],
            'description'         => $repo['description'] ?? '',
            'html_url'            => $repo_url,
            'clone_url'           => $repo['clone_url'] ?? '',
            'default_branch'      => $repo['default_branch'] ?? 'main',
            'current_branch'      => $current_branch,
            'updated_at'          => $repo['updated_at'] ?? '',
            'state'               => $state,
            'installation_method' => $installation_method,
            'plugin_file'         => $plugin_file,
            'is_installed'        => $is_installed,
            'is_active'           => $is_active,
            'metadata'            => $metadata,
        ];
    }

    /**
     * Find installed plugin file for repository.
     *
     * @param string $repo_name Repository name.
     * @return string Plugin file path or empty string.
     */
    private function find_installed_plugin(string $repo_name): string {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $normalized_repo = strtolower(str_replace(['-', '_'], '', $repo_name));

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            $plugin_dir = dirname($plugin_file);
            $normalized_dir = strtolower(str_replace(['-', '_'], '', $plugin_dir));

            if ($normalized_dir === $normalized_repo) {
                return $plugin_file;
            }
        }

        return '';
    }

    /**
     * Render checkbox column.
     *
     * @param array $item Repository item.
     * @return string Checkbox HTML.
     */
    public function column_cb($item): string {
        return sprintf(
            '<input type="checkbox" name="repositories[]" value="%s" />',
            esc_attr($item['html_url'])
        );
    }

    /**
     * Render repository name column.
     *
     * @param array $item Repository item.
     * @return string Repository name HTML.
     */
    public function column_name($item): string {
        $actions = [];
        $state = $item['state'];

        // Build row actions based on state
        if ($state === PluginState::AVAILABLE) {
            $actions['install'] = sprintf(
                '<a href="#" class="git-updater-install-btn" data-repo-url="%s" data-branch="%s">%s</a>',
                esc_attr($item['html_url']),
                esc_attr($item['default_branch']),
                __('Install', 'git-updater')
            );
        } elseif ($state->isInstalled()) {
            if ($item['is_active']) {
                $actions['deactivate'] = sprintf(
                    '<a href="#" class="git-updater-deactivate-btn" data-plugin-file="%s">%s</a>',
                    esc_attr($item['plugin_file']),
                    __('Deactivate', 'git-updater')
                );
            } else {
                $actions['activate'] = sprintf(
                    '<a href="#" class="git-updater-activate-btn" data-plugin-file="%s">%s</a>',
                    esc_attr($item['plugin_file']),
                    __('Activate', 'git-updater')
                );
            }

            if ($state === PluginState::UPDATE_AVAILABLE) {
                $actions['update'] = sprintf(
                    '<a href="#" class="git-updater-update-btn" data-plugin-file="%s">%s</a>',
                    esc_attr($item['plugin_file']),
                    __('Update', 'git-updater')
                );
            }
        }

        // Always add refresh action
        $actions['refresh'] = sprintf(
            '<a href="#" class="git-updater-refresh-btn" data-repo-url="%s">%s</a>',
            esc_attr($item['html_url']),
            __('Refresh', 'git-updater')
        );

        $name_html = sprintf(
            '<strong><a href="%s" target="_blank">%s</a></strong>',
            esc_url($item['html_url']),
            esc_html($item['name'])
        );

        return $name_html . $this->row_actions($actions);
    }

    /**
     * Render description column.
     *
     * @param array $item Repository item.
     * @return string Description HTML.
     */
    public function column_description($item): string {
        $description = esc_html($item['description']);
        if (empty($description)) {
            $description = '<em>' . __('No description available', 'git-updater') . '</em>';
        }
        return $description;
    }

    /**
     * Render installation method column.
     *
     * @param array $item Repository item.
     * @return string Installation method HTML.
     */
    public function column_installation_method($item): string {
        if ($item['installation_method'] === 'git_updater') {
            return '<span class="git-updater-badge">' . __('Git Updater', 'git-updater') . '</span>';
        }
        return '<span class="standard-badge">' . __('Standard', 'git-updater') . '</span>';
    }

    /**
     * Render state column.
     *
     * @param array $item Repository item.
     * @return string State HTML.
     */
    public function column_state($item): string {
        $state = $item['state'];
        return sprintf(
            '<span class="git-updater-state %s">%s</span>',
            esc_attr($state->getCssClass()),
            esc_html($state->getLabel())
        );
    }

    /**
     * Render current branch column.
     *
     * @param array $item Repository item.
     * @return string Branch HTML.
     */
    public function column_current_branch($item): string {
        $branch_html = esc_html($item['current_branch']);
        
        if ($item['state']->isInstalled()) {
            $branch_html .= sprintf(
                ' <a href="#" class="git-updater-switch-branch-btn" data-plugin-file="%s" title="%s">%s</a>',
                esc_attr($item['plugin_file']),
                esc_attr__('Switch branch', 'git-updater'),
                '<span class="dashicons dashicons-randomize"></span>'
            );
        }
        
        return $branch_html;
    }

    /**
     * Render last updated column.
     *
     * @param array $item Repository item.
     * @return string Last updated HTML.
     */
    public function column_last_updated($item): string {
        if (empty($item['updated_at'])) {
            return '<em>' . __('Unknown', 'git-updater') . '</em>';
        }
        
        $timestamp = strtotime($item['updated_at']);
        return sprintf(
            '<time datetime="%s" title="%s">%s</time>',
            esc_attr(date('c', $timestamp)),
            esc_attr(date('Y-m-d H:i:s', $timestamp)),
            esc_html(human_time_diff($timestamp) . ' ago')
        );
    }

    /**
     * Render actions column.
     *
     * @param array $item Repository item.
     * @return string Actions HTML.
     */
    public function column_actions($item): string {
        $actions = [];
        $state = $item['state'];

        if ($state === PluginState::AVAILABLE) {
            $actions[] = sprintf(
                '<button type="button" class="button button-primary git-updater-install-btn" data-repo-url="%s" data-branch="%s">%s</button>',
                esc_attr($item['html_url']),
                esc_attr($item['default_branch']),
                __('Install', 'git-updater')
            );
        } elseif ($state->isInstalled()) {
            if ($state === PluginState::UPDATE_AVAILABLE) {
                $actions[] = sprintf(
                    '<button type="button" class="button button-primary git-updater-update-btn" data-plugin-file="%s">%s</button>',
                    esc_attr($item['plugin_file']),
                    __('Update', 'git-updater')
                );
            }

            if ($item['is_active']) {
                $actions[] = sprintf(
                    '<button type="button" class="button git-updater-deactivate-btn" data-plugin-file="%s">%s</button>',
                    esc_attr($item['plugin_file']),
                    __('Deactivate', 'git-updater')
                );
            } else {
                $actions[] = sprintf(
                    '<button type="button" class="button git-updater-activate-btn" data-plugin-file="%s">%s</button>',
                    esc_attr($item['plugin_file']),
                    __('Activate', 'git-updater')
                );
            }
        }

        return implode(' ', $actions);
    }

    /**
     * Default column renderer.
     *
     * @param array  $item        Repository item.
     * @param string $column_name Column name.
     * @return string Column HTML.
     */
    public function column_default($item, $column_name): string {
        return $item[$column_name] ?? '';
    }

    /**
     * Display the table with enhanced features.
     */
    public function display(): void {
        echo '<div class="git-updater-repository-table-wrapper">';
        echo '<div class="git-updater-progress-container" style="display: none;">';
        echo '<div class="git-updater-progress-bar"></div>';
        echo '</div>';
        
        parent::display();
        
        echo '</div>';
    }
}
