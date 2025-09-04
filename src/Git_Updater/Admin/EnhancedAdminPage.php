<?php
/**
 * Enhanced Admin Page for Git Updater with FSM integration.
 *
 * @package Git_Updater\Admin
 */

namespace Fragen\Git_Updater\Admin;

use Fragen\Git_Updater\FSM\GitUpdaterStateManager;
use Fragen\Git_Updater\Services\GitUpdaterIntegrationService;
use Fragen\Git_Updater\API\GitUpdaterAjaxHandler;

/**
 * Enhanced Admin Page class.
 * 
 * Provides a modern, FSM-powered admin interface that replaces
 * Git Updater's basic forms with advanced List Table functionality.
 */
class EnhancedAdminPage {

    /**
     * State manager instance.
     *
     * @var GitUpdaterStateManager
     */
    private GitUpdaterStateManager $state_manager;

    /**
     * Integration service instance.
     *
     * @var GitUpdaterIntegrationService
     */
    private GitUpdaterIntegrationService $integration_service;

    /**
     * AJAX handler instance.
     *
     * @var GitUpdaterAjaxHandler
     */
    private GitUpdaterAjaxHandler $ajax_handler;

    /**
     * Constructor.
     *
     * @param GitUpdaterStateManager $state_manager State manager.
     * @param GitUpdaterIntegrationService $integration_service Integration service.
     * @param GitUpdaterAjaxHandler $ajax_handler AJAX handler.
     */
    public function __construct(
        GitUpdaterStateManager $state_manager,
        GitUpdaterIntegrationService $integration_service,
        GitUpdaterAjaxHandler $ajax_handler
    ) {
        $this->state_manager = $state_manager;
        $this->integration_service = $integration_service;
        $this->ajax_handler = $ajax_handler;
    }

    /**
     * Initialize the enhanced admin page.
     */
    public function init(): void {
        // Register the enhanced installation tab
        add_filter('gu_add_settings_tabs', [$this, 'add_enhanced_tabs']);
        add_action('gu_add_admin_page', [$this, 'add_enhanced_admin_page'], 10, 2);

        // Register AJAX handlers
        $this->ajax_handler->register_hooks();

        // Redirect is handled by FSMBootstrap
    }

    /**
     * Add enhanced tabs to Git Updater settings.
     *
     * @param array $tabs Existing tabs.
     * @return array Enhanced tabs.
     */
    public function add_enhanced_tabs(array $tabs): array {
        $enhanced_tabs = [
            'git_updater_enhanced_install' => __('Enhanced Install', 'git-updater'),
            'git_updater_repository_manager' => __('Repository Manager', 'git-updater'),
        ];

        return array_merge($tabs, $enhanced_tabs);
    }

    /**
     * Add enhanced admin page content.
     *
     * @param string $tab Current tab.
     * @param string $action Form action.
     */
    public function add_enhanced_admin_page(string $tab, string $action): void {
        if ($tab === 'git_updater_enhanced_install') {
            $this->render_enhanced_install_page();
        } elseif ($tab === 'git_updater_repository_manager') {
            $this->render_repository_manager_page();
        }
    }

    /**
     * Render enhanced installation page.
     */
    private function render_enhanced_install_page(): void {
        ?>
        <div class="git-updater-enhanced-install">
            <h2><?php _e('Enhanced Git Updater Installation', 'git-updater'); ?></h2>
            <p class="description">
                <?php _e('Install and manage WordPress plugins from Git repositories with real-time status updates and batch operations.', 'git-updater'); ?>
            </p>

            <!-- Organization Input -->
            <div class="git-updater-organization-section">
                <h3><?php _e('Repository Source', 'git-updater'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="git-updater-organization"><?php _e('GitHub Organization/User', 'git-updater'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="git-updater-organization" 
                                   class="regular-text" 
                                   placeholder="<?php esc_attr_e('e.g., wordpress, automattic, your-username', 'git-updater'); ?>"
                                   value="<?php echo esc_attr(get_option('git_updater_default_organization', '')); ?>" />
                            <button type="button" 
                                    id="git-updater-fetch-repos" 
                                    class="button button-primary">
                                <?php _e('Fetch Repositories', 'git-updater'); ?>
                            </button>
                            <p class="description">
                                <?php _e('Enter a GitHub organization or username to fetch available repositories.', 'git-updater'); ?>
                            </p>
                        </td>
                    </tr>
                </table>
            </div>

            <!-- Repository List Container -->
            <div id="git-updater-repository-list-container" style="display: none;">
                <h3><?php _e('Available Repositories', 'git-updater'); ?></h3>
                <div id="git-updater-repository-list"></div>
            </div>

            <!-- Manual Installation -->
            <div class="git-updater-manual-section">
                <h3><?php _e('Manual Installation', 'git-updater'); ?></h3>
                <table class="form-table">
                    <tr>
                        <th scope="row">
                            <label for="git-updater-manual-repo"><?php _e('Repository URL', 'git-updater'); ?></label>
                        </th>
                        <td>
                            <input type="url" 
                                   id="git-updater-manual-repo" 
                                   class="regular-text" 
                                   placeholder="https://github.com/user/repository" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row">
                            <label for="git-updater-manual-branch"><?php _e('Branch', 'git-updater'); ?></label>
                        </th>
                        <td>
                            <input type="text" 
                                   id="git-updater-manual-branch" 
                                   class="regular-text" 
                                   value="main" 
                                   placeholder="main" />
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"></th>
                        <td>
                            <button type="button" 
                                    id="git-updater-manual-install" 
                                    class="button button-primary">
                                <?php _e('Install Plugin', 'git-updater'); ?>
                            </button>
                        </td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="git-updater-loading-overlay" style="display: none;">
            <div class="git-updater-spinner"></div>
            <p><?php _e('Loading repositories...', 'git-updater'); ?></p>
        </div>
        <?php
    }

    /**
     * Render repository manager page.
     */
    private function render_repository_manager_page(): void {
        ?>
        <div class="git-updater-repository-manager">
            <h2><?php _e('Repository Manager', 'git-updater'); ?></h2>
            <p class="description">
                <?php _e('Manage all your Git Updater repositories with real-time status monitoring and batch operations.', 'git-updater'); ?>
            </p>

            <!-- Filter Controls -->
            <div class="git-updater-filters">
                <div class="alignleft actions">
                    <select id="git-updater-filter-state">
                        <option value=""><?php _e('All States', 'git-updater'); ?></option>
                        <option value="available"><?php _e('Available', 'git-updater'); ?></option>
                        <option value="installed_inactive"><?php _e('Installed (Inactive)', 'git-updater'); ?></option>
                        <option value="installed_active"><?php _e('Installed (Active)', 'git-updater'); ?></option>
                        <option value="git_updater_managed"><?php _e('Git Updater Managed', 'git-updater'); ?></option>
                        <option value="update_available"><?php _e('Update Available', 'git-updater'); ?></option>
                        <option value="error"><?php _e('Error', 'git-updater'); ?></option>
                    </select>
                    
                    <select id="git-updater-filter-method">
                        <option value=""><?php _e('All Methods', 'git-updater'); ?></option>
                        <option value="git_updater"><?php _e('Git Updater', 'git-updater'); ?></option>
                        <option value="standard"><?php _e('Standard', 'git-updater'); ?></option>
                    </select>
                    
                    <button type="button" id="git-updater-apply-filters" class="button">
                        <?php _e('Filter', 'git-updater'); ?>
                    </button>
                    
                    <button type="button" id="git-updater-refresh-all" class="button">
                        <?php _e('Refresh All', 'git-updater'); ?>
                    </button>
                </div>
                
                <div class="alignright actions">
                    <button type="button" id="git-updater-check-updates" class="button">
                        <?php _e('Check for Updates', 'git-updater'); ?>
                    </button>
                </div>
            </div>

            <!-- Repository Manager Table -->
            <div id="git-updater-manager-table-container">
                <?php $this->render_installed_repositories_table(); ?>
            </div>

            <!-- Batch Operations -->
            <div class="git-updater-batch-operations" style="margin-top: 20px;">
                <h3><?php _e('Batch Operations', 'git-updater'); ?></h3>
                <p class="description">
                    <?php _e('Select repositories above and choose a batch operation to perform on multiple items at once.', 'git-updater'); ?>
                </p>
                
                <div class="git-updater-batch-buttons">
                    <button type="button" id="git-updater-batch-update" class="button" disabled>
                        <?php _e('Update Selected', 'git-updater'); ?>
                    </button>
                    <button type="button" id="git-updater-batch-activate" class="button" disabled>
                        <?php _e('Activate Selected', 'git-updater'); ?>
                    </button>
                    <button type="button" id="git-updater-batch-deactivate" class="button" disabled>
                        <?php _e('Deactivate Selected', 'git-updater'); ?>
                    </button>
                    <button type="button" id="git-updater-batch-refresh" class="button" disabled>
                        <?php _e('Refresh Selected', 'git-updater'); ?>
                    </button>
                </div>
            </div>
        </div>
        <?php
    }

    /**
     * Render installed repositories table.
     */
    private function render_installed_repositories_table(): void {
        // Get all installed plugins managed by Git Updater
        $installed_repos = $this->get_installed_git_updater_repositories();
        
        $list_table = new GitUpdaterRepositoryListTable($this->state_manager);
        $list_table->set_repositories($installed_repos);
        $list_table->prepare_items();
        
        echo '<form id="git-updater-repositories-form" method="post">';
        wp_nonce_field('git_updater_bulk_action', 'git_updater_nonce');
        $list_table->display();
        echo '</form>';
    }

    /**
     * Get installed repositories managed by Git Updater.
     *
     * @return array Installed repository data.
     */
    private function get_installed_git_updater_repositories(): array {
        if (!function_exists('get_plugins')) {
            require_once ABSPATH . 'wp-admin/includes/plugin.php';
        }

        $all_plugins = get_plugins();
        $git_updater_repos = [];

        foreach ($all_plugins as $plugin_file => $plugin_data) {
            // Check if plugin has Git Updater headers
            if ($this->is_git_updater_managed($plugin_data)) {
                $repo_url = $this->extract_repo_url($plugin_data);
                $state = $this->state_manager->get_state($repo_url ?: $plugin_file);
                
                $git_updater_repos[] = [
                    'id' => md5($plugin_file),
                    'name' => $plugin_data['Name'],
                    'full_name' => $plugin_data['Name'],
                    'description' => $plugin_data['Description'],
                    'html_url' => $repo_url,
                    'clone_url' => $repo_url,
                    'default_branch' => 'main', // Could be extracted from headers
                    'updated_at' => date('c'), // Could be from plugin version
                    'plugin_file' => $plugin_file,
                    'version' => $plugin_data['Version'],
                    'is_active' => is_plugin_active($plugin_file),
                ];
            }
        }

        return $git_updater_repos;
    }

    /**
     * Check if plugin is managed by Git Updater.
     *
     * @param array $plugin_data Plugin data.
     * @return bool True if managed by Git Updater.
     */
    private function is_git_updater_managed(array $plugin_data): bool {
        // Check for Git Updater specific headers
        $git_headers = [
            'GitHub Plugin URI',
            'GitLab Plugin URI', 
            'Bitbucket Plugin URI',
            'Gitea Plugin URI',
            'Git Plugin URI'
        ];

        foreach ($git_headers as $header) {
            if (!empty($plugin_data[$header])) {
                return true;
            }
        }

        return false;
    }

    /**
     * Extract repository URL from plugin data.
     *
     * @param array $plugin_data Plugin data.
     * @return string Repository URL or empty string.
     */
    private function extract_repo_url(array $plugin_data): string {
        $git_headers = [
            'GitHub Plugin URI',
            'GitLab Plugin URI',
            'Bitbucket Plugin URI', 
            'Gitea Plugin URI',
            'Git Plugin URI'
        ];

        foreach ($git_headers as $header) {
            if (!empty($plugin_data[$header])) {
                return $plugin_data[$header];
            }
        }

        return '';
    }


}
