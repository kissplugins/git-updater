<?php
/**
 * KISS SBI x Git Updater Admin Menu
 *
 * Creates a dedicated WordPress admin menu for the KISS SBI x Git Updater integration.
 * This provides a clean entry point that bypasses any Freemius interference.
 */

namespace Fragen\Git_Updater\Admin;

use Fragen\Git_Updater\FSM\GitUpdaterStateManager;
use Fragen\Git_Updater\Services\GitUpdaterIntegrationService;
use Fragen\Git_Updater\API\GitUpdaterAjaxHandler;
use Fragen\Git_Updater\Admin\GitUpdaterRepositoryListTable;

/**
 * KISS SBI Admin Menu class.
 */
class KissSbiAdminMenu {
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
     * @param GitUpdaterStateManager $state_manager State manager instance.
     * @param GitUpdaterIntegrationService $integration_service Integration service instance.
     * @param GitUpdaterAjaxHandler $ajax_handler AJAX handler instance.
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
     * Initialize the admin menu.
     */
    public function init(): void {
        add_action('admin_menu', [$this, 'add_admin_menu']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_assets']);
        
        // Register AJAX handlers
        $this->ajax_handler->register_hooks();
    }

    /**
     * Add the admin menu.
     */
    public function add_admin_menu(): void {
        // Add main menu page
        add_menu_page(
            __('KISS SBI x Git Updater', 'git-updater'),           // Page title
            __('KISS SBI x GU', 'git-updater'),                   // Menu title
            'manage_options',                                      // Capability
            'kiss-sbi-git-updater',                               // Menu slug
            [$this, 'render_main_page'],                          // Callback
            'dashicons-download',                                  // Icon
            30                                                     // Position
        );

        // Add submenu pages
        add_submenu_page(
            'kiss-sbi-git-updater',                               // Parent slug
            __('Enhanced Install', 'git-updater'),               // Page title
            __('Enhanced Install', 'git-updater'),               // Menu title
            'manage_options',                                      // Capability
            'kiss-sbi-git-updater',                               // Menu slug (same as parent for main page)
            [$this, 'render_main_page']                           // Callback
        );

        add_submenu_page(
            'kiss-sbi-git-updater',                               // Parent slug
            __('Repository Manager', 'git-updater'),             // Page title
            __('Repository Manager', 'git-updater'),             // Menu title
            'manage_options',                                      // Capability
            'kiss-sbi-repository-manager',                        // Menu slug
            [$this, 'render_repository_manager']                  // Callback
        );

        add_submenu_page(
            'kiss-sbi-git-updater',                               // Parent slug
            __('Batch Operations', 'git-updater'),               // Page title
            __('Batch Operations', 'git-updater'),               // Menu title
            'manage_options',                                      // Capability
            'kiss-sbi-batch-operations',                          // Menu slug
            [$this, 'render_batch_operations']                    // Callback
        );
    }

    /**
     * Enqueue assets for our admin pages.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_assets(string $hook_suffix): void {
        // Only enqueue on our pages
        if (!$this->is_our_admin_page($hook_suffix)) {
            return;
        }

        // Enqueue our enhanced CSS and JS
        wp_enqueue_style(
            'git-updater-fsm',
            plugin_dir_url(dirname(__DIR__, 2)) . 'css/git-updater-fsm.css',
            [],
            '1.0.0'
        );

        wp_enqueue_script(
            'git-updater-fsm',
            plugin_dir_url(dirname(__DIR__, 2)) . 'js/git-updater-fsm.js',
            ['jquery'],
            '1.0.0',
            true
        );

        // Localize script with FSM data
        wp_localize_script('git-updater-fsm', 'gitUpdaterFSM', [
            'ajaxUrl' => admin_url('admin-ajax.php'),
            'nonce' => wp_create_nonce('git_updater_fsm'),
            'sseUrl' => admin_url('admin-ajax.php?action=git_updater_sse'),
            'states' => $this->get_localized_states()
        ]);
    }

    /**
     * Check if current page is one of our admin pages.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     * @return bool True if it's our page.
     */
    private function is_our_admin_page(string $hook_suffix): bool {
        $our_pages = [
            'toplevel_page_kiss-sbi-git-updater',
            'kiss-sbi-x-gu_page_kiss-sbi-repository-manager',
            'kiss-sbi-x-gu_page_kiss-sbi-batch-operations'
        ];

        return in_array($hook_suffix, $our_pages, true);
    }

    /**
     * Render the main Enhanced Install page.
     */
    public function render_main_page(): void {
        ?>
        <div class="wrap">
            <h1><?php _e('KISS SBI x Git Updater - Enhanced Install', 'git-updater'); ?></h1>
            
            <div class="notice notice-info">
                <p>
                    <strong><?php _e('Welcome to the enhanced Git Updater interface!', 'git-updater'); ?></strong>
                    <?php _e('This modern interface provides real-time status updates, batch operations, and advanced repository management.', 'git-updater'); ?>
                </p>
            </div>

            <?php $this->render_enhanced_install_content(); ?>
        </div>
        <?php
    }

    /**
     * Render the Repository Manager page.
     */
    public function render_repository_manager(): void {
        ?>
        <div class="wrap">
            <h1><?php _e('KISS SBI x Git Updater - Repository Manager', 'git-updater'); ?></h1>
            
            <div class="notice notice-info">
                <p>
                    <?php _e('Manage all your Git Updater repositories with real-time status monitoring and batch operations.', 'git-updater'); ?>
                </p>
            </div>

            <?php $this->render_repository_manager_content(); ?>
        </div>
        <?php
    }

    /**
     * Render the Batch Operations page.
     */
    public function render_batch_operations(): void {
        ?>
        <div class="wrap">
            <h1><?php _e('KISS SBI x Git Updater - Batch Operations', 'git-updater'); ?></h1>
            
            <div class="notice notice-info">
                <p>
                    <?php _e('Perform operations on multiple repositories simultaneously with real-time progress tracking.', 'git-updater'); ?>
                </p>
            </div>

            <?php $this->render_batch_operations_content(); ?>
        </div>
        <?php
    }

    /**
     * Render enhanced install content.
     */
    private function render_enhanced_install_content(): void {
        // Include the enhanced install page from EnhancedAdminPage
        $enhanced_admin = new EnhancedAdminPage(
            $this->state_manager,
            $this->integration_service,
            $this->ajax_handler
        );
        
        // Use reflection to call the private method
        $reflection = new \ReflectionClass($enhanced_admin);
        $method = $reflection->getMethod('render_enhanced_install_page');
        $method->setAccessible(true);
        $method->invoke($enhanced_admin);
    }

    /**
     * Render repository manager content.
     */
    private function render_repository_manager_content(): void {
        // Include the repository manager page from EnhancedAdminPage
        $enhanced_admin = new EnhancedAdminPage(
            $this->state_manager,
            $this->integration_service,
            $this->ajax_handler
        );
        
        // Use reflection to call the private method
        $reflection = new \ReflectionClass($enhanced_admin);
        $method = $reflection->getMethod('render_repository_manager_page');
        $method->setAccessible(true);
        $method->invoke($enhanced_admin);
    }

    /**
     * Render batch operations content.
     */
    private function render_batch_operations_content(): void {
        ?>
        <div class="git-updater-batch-operations-page">
            <div class="git-updater-filters">
                <div class="alignleft">
                    <label for="filter-by-state"><?php _e('Filter by State:', 'git-updater'); ?></label>
                    <select id="filter-by-state">
                        <option value=""><?php _e('All States', 'git-updater'); ?></option>
                        <option value="installed_active"><?php _e('Active', 'git-updater'); ?></option>
                        <option value="installed_inactive"><?php _e('Inactive', 'git-updater'); ?></option>
                        <option value="update_available"><?php _e('Update Available', 'git-updater'); ?></option>
                        <option value="git_managed"><?php _e('Git Managed', 'git-updater'); ?></option>
                    </select>
                </div>
                
                <div class="alignright">
                    <button type="button" class="button" id="git-updater-refresh-all">
                        <?php _e('Refresh All', 'git-updater'); ?>
                    </button>
                    <button type="button" class="button" id="git-updater-check-updates">
                        <?php _e('Check Updates', 'git-updater'); ?>
                    </button>
                </div>
                <div class="clear"></div>
            </div>

            <!-- Repository List for Batch Operations -->
            <div id="git-updater-batch-table-container">
                <?php $this->render_batch_operations_table(); ?>
            </div>

            <!-- Batch Operation Controls -->
            <div class="git-updater-batch-controls" style="margin-top: 20px;">
                <h3><?php _e('Batch Operations', 'git-updater'); ?></h3>
                <p class="description">
                    <?php _e('Select repositories above and choose a batch operation to perform on multiple items at once.', 'git-updater'); ?>
                </p>
                
                <div class="git-updater-batch-buttons">
                    <button type="button" class="button button-primary" id="git-updater-batch-install" disabled>
                        <?php _e('Install Selected', 'git-updater'); ?>
                    </button>
                    <button type="button" class="button" id="git-updater-batch-update" disabled>
                        <?php _e('Update Selected', 'git-updater'); ?>
                    </button>
                    <button type="button" class="button" id="git-updater-batch-activate" disabled>
                        <?php _e('Activate Selected', 'git-updater'); ?>
                    </button>
                    <button type="button" class="button" id="git-updater-batch-deactivate" disabled>
                        <?php _e('Deactivate Selected', 'git-updater'); ?>
                    </button>
                    <button type="button" class="button" id="git-updater-batch-refresh" disabled>
                        <?php _e('Refresh Selected', 'git-updater'); ?>
                    </button>
                </div>
            </div>
        </div>

        <!-- Loading Overlay -->
        <div id="git-updater-loading-overlay" style="display: none;">
            <div class="git-updater-spinner"></div>
            <p><?php _e('Processing batch operation...', 'git-updater'); ?></p>
        </div>
        <?php
    }

    /**
     * Render batch operations table.
     */
    private function render_batch_operations_table(): void {
        $list_table = new GitUpdaterRepositoryListTable($this->state_manager);
        
        // Get all installed plugins managed by Git Updater
        $installed_repos = $this->get_installed_git_updater_repositories();
        $list_table->set_repositories($installed_repos);
        $list_table->prepare_items();
        
        echo '<form id="git-updater-batch-form" method="post">';
        wp_nonce_field('git_updater_batch_action', 'git_updater_nonce');
        $list_table->display();
        echo '</form>';
    }

    /**
     * Get installed Git Updater repositories.
     *
     * @return array Array of repository data.
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
                
                $git_updater_repos[] = [
                    'id' => md5($plugin_file),
                    'name' => $plugin_data['Name'],
                    'description' => $plugin_data['Description'],
                    'version' => $plugin_data['Version'],
                    'plugin_file' => $plugin_file,
                    'repo_url' => $repo_url,
                    'is_active' => is_plugin_active($plugin_file),
                    'installation_method' => 'git_updater',
                    'last_updated' => date('Y-m-d H:i:s')
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

    /**
     * Get localized states for JavaScript.
     *
     * @return array Localized state data.
     */
    private function get_localized_states(): array {
        $states = [];
        foreach (\Fragen\Git_Updater\Enums\PluginState::cases() as $state) {
            $states[$state->value] = [
                'label' => $state->getLabel(),
                'css_class' => $state->getCssClass()
            ];
        }
        return $states;
    }
}
