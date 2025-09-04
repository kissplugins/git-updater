<?php
/**
 * Enhanced AJAX handler for Git Updater with FSM integration.
 *
 * @package Git_Updater\API
 */

namespace Fragen\Git_Updater\API;

use WP_Error;
use Fragen\Git_Updater\FSM\GitUpdaterStateManager;
use Fragen\Git_Updater\Services\GitUpdaterIntegrationService;
use Fragen\Git_Updater\Enums\PluginState;
use Fragen\Git_Updater\Admin\GitUpdaterRepositoryListTable;

/**
 * Git Updater AJAX handler class.
 * 
 * Provides enhanced AJAX endpoints for real-time Git Updater operations
 * with FSM state management and modern UI interactions.
 */
class GitUpdaterAjaxHandler {

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
     * Constructor.
     *
     * @param GitUpdaterStateManager $state_manager State manager.
     * @param GitUpdaterIntegrationService $integration_service Integration service.
     */
    public function __construct(
        GitUpdaterStateManager $state_manager,
        GitUpdaterIntegrationService $integration_service
    ) {
        $this->state_manager = $state_manager;
        $this->integration_service = $integration_service;
    }

    /**
     * Register AJAX hooks.
     */
    public function register_hooks(): void {
        // Repository management
        add_action('wp_ajax_git_updater_fetch_repositories', [$this, 'fetch_repositories']);
        add_action('wp_ajax_git_updater_refresh_repository', [$this, 'refresh_repository']);
        add_action('wp_ajax_git_updater_get_repository_state', [$this, 'get_repository_state']);

        // Plugin operations
        add_action('wp_ajax_git_updater_install_plugin', [$this, 'install_plugin']);
        add_action('wp_ajax_git_updater_update_plugin', [$this, 'update_plugin']);
        add_action('wp_ajax_git_updater_activate_plugin', [$this, 'activate_plugin']);
        add_action('wp_ajax_git_updater_deactivate_plugin', [$this, 'deactivate_plugin']);
        add_action('wp_ajax_git_updater_switch_branch', [$this, 'switch_branch']);

        // Batch operations
        add_action('wp_ajax_git_updater_batch_install', [$this, 'batch_install']);
        add_action('wp_ajax_git_updater_batch_update', [$this, 'batch_update']);
        add_action('wp_ajax_git_updater_batch_activate', [$this, 'batch_activate']);
        add_action('wp_ajax_git_updater_batch_deactivate', [$this, 'batch_deactivate']);

        // State management
        add_action('wp_ajax_git_updater_get_state', [$this, 'get_state']);
        add_action('wp_ajax_git_updater_set_state', [$this, 'set_state']);
        add_action('wp_ajax_git_updater_get_metadata', [$this, 'get_metadata']);

        // UI operations
        add_action('wp_ajax_git_updater_render_table', [$this, 'render_table']);
        add_action('wp_ajax_git_updater_render_row', [$this, 'render_row']);

        // SSE endpoint (already registered in FSMBootstrap)
        // add_action('wp_ajax_git_updater_sse', [$this, 'sse_stream']);
    }

    /**
     * Fetch repositories from GitHub organization.
     */
    public function fetch_repositories(): void {
        $this->verify_nonce();

        $organization = sanitize_text_field($_POST['organization'] ?? '');
        if (empty($organization)) {
            wp_send_json_error('Organization is required');
        }

        try {
            // This would integrate with GitHub API or other Git services
            // For now, return mock data structure
            $repositories = $this->mock_fetch_repositories($organization);

            wp_send_json_success([
                'repositories' => $repositories,
                'organization' => $organization,
                'count' => count($repositories)
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Failed to fetch repositories: ' . $e->getMessage());
        }
    }

    /**
     * Install plugin via Git Updater.
     */
    public function install_plugin(): void {
        $this->verify_nonce();

        $repo_url = sanitize_url($_POST['repo_url'] ?? '');
        $branch = sanitize_text_field($_POST['branch'] ?? 'main');

        if (empty($repo_url)) {
            wp_send_json_error('Repository URL is required');
        }

        try {
            $result = $this->integration_service->install_via_git_updater($repo_url, $branch);

            if ($result) {
                wp_send_json_success([
                    'message' => 'Installation started successfully',
                    'repository' => $repo_url,
                    'branch' => $branch
                ]);
            } else {
                wp_send_json_error('Installation failed to start');
            }

        } catch (\Exception $e) {
            wp_send_json_error('Installation error: ' . $e->getMessage());
        }
    }

    /**
     * Update plugin via Git Updater.
     */
    public function update_plugin(): void {
        $this->verify_nonce();

        $plugin_file = sanitize_text_field($_POST['plugin_file'] ?? '');

        if (empty($plugin_file)) {
            wp_send_json_error('Plugin file is required');
        }

        try {
            $result = $this->integration_service->check_for_updates($plugin_file);

            wp_send_json_success([
                'has_update' => $result,
                'plugin_file' => $plugin_file
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Update check failed: ' . $e->getMessage());
        }
    }

    /**
     * Activate plugin.
     */
    public function activate_plugin(): void {
        $this->verify_nonce();

        $plugin_file = sanitize_text_field($_POST['plugin_file'] ?? '');

        if (empty($plugin_file)) {
            wp_send_json_error('Plugin file is required');
        }

        if (!current_user_can('activate_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            $result = activate_plugin($plugin_file);

            if (is_wp_error($result)) {
                wp_send_json_error($result->get_error_message());
            }

            // Update FSM state
            $this->state_manager->transition($plugin_file, PluginState::INSTALLED_ACTIVE);

            wp_send_json_success([
                'message' => 'Plugin activated successfully',
                'plugin_file' => $plugin_file
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Activation failed: ' . $e->getMessage());
        }
    }

    /**
     * Deactivate plugin.
     */
    public function deactivate_plugin(): void {
        $this->verify_nonce();

        $plugin_file = sanitize_text_field($_POST['plugin_file'] ?? '');

        if (empty($plugin_file)) {
            wp_send_json_error('Plugin file is required');
        }

        if (!current_user_can('deactivate_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }

        try {
            deactivate_plugins($plugin_file);

            // Update FSM state
            $this->state_manager->transition($plugin_file, PluginState::INSTALLED_INACTIVE);

            wp_send_json_success([
                'message' => 'Plugin deactivated successfully',
                'plugin_file' => $plugin_file
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Deactivation failed: ' . $e->getMessage());
        }
    }

    /**
     * Switch plugin branch.
     */
    public function switch_branch(): void {
        $this->verify_nonce();

        $plugin_file = sanitize_text_field($_POST['plugin_file'] ?? '');
        $new_branch = sanitize_text_field($_POST['new_branch'] ?? '');

        if (empty($plugin_file) || empty($new_branch)) {
            wp_send_json_error('Plugin file and branch are required');
        }

        try {
            $result = $this->integration_service->switch_branch($plugin_file, $new_branch);

            if ($result) {
                wp_send_json_success([
                    'message' => 'Branch switch completed successfully',
                    'plugin_file' => $plugin_file,
                    'new_branch' => $new_branch
                ]);
            } else {
                wp_send_json_error('Branch switch failed');
            }

        } catch (\Exception $e) {
            wp_send_json_error('Branch switch error: ' . $e->getMessage());
        }
    }

    /**
     * Batch install multiple repositories.
     */
    public function batch_install(): void {
        $this->verify_nonce();

        $repositories = $_POST['repositories'] ?? [];
        if (empty($repositories) || !is_array($repositories)) {
            wp_send_json_error('No repositories selected');
        }

        $results = [];
        foreach ($repositories as $repo_url) {
            $repo_url = sanitize_url($repo_url);
            try {
                $result = $this->integration_service->install_via_git_updater($repo_url);
                $results[$repo_url] = $result ? 'success' : 'failed';
            } catch (\Exception $e) {
                $results[$repo_url] = 'error: ' . $e->getMessage();
            }
        }

        wp_send_json_success([
            'message' => 'Batch installation completed',
            'results' => $results
        ]);
    }

    /**
     * Get repository state.
     */
    public function get_state(): void {
        $this->verify_nonce();

        $repository = sanitize_text_field($_POST['repository'] ?? '');
        if (empty($repository)) {
            wp_send_json_error('Repository is required');
        }

        $state = $this->state_manager->get_state($repository);
        $metadata = $this->state_manager->get_metadata($repository);

        wp_send_json_success([
            'state' => $state->value,
            'label' => $state->getLabel(),
            'css_class' => $state->getCssClass(),
            'metadata' => $metadata
        ]);
    }

    /**
     * Refresh repository state.
     */
    public function refresh_repository(): void {
        $this->verify_nonce();

        $repository = sanitize_text_field($_POST['repository'] ?? '');
        if (empty($repository)) {
            wp_send_json_error('Repository is required');
        }

        try {
            // Force refresh repository state
            $this->state_manager->set_state($repository, PluginState::CHECKING, true);
            
            // Trigger state refresh logic here
            // This would check actual plugin status and update accordingly
            
            $state = $this->state_manager->get_state($repository);
            
            wp_send_json_success([
                'message' => 'Repository refreshed',
                'repository' => $repository,
                'state' => $state->value,
                'label' => $state->getLabel()
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Refresh failed: ' . $e->getMessage());
        }
    }

    /**
     * Render repository table.
     */
    public function render_table(): void {
        $this->verify_nonce();

        try {
            $organization = sanitize_text_field($_POST['organization'] ?? '');
            $repositories = $this->mock_fetch_repositories($organization);

            $list_table = new GitUpdaterRepositoryListTable($this->state_manager);
            $list_table->set_organization($organization);
            $list_table->set_repositories($repositories);
            $list_table->prepare_items();

            ob_start();
            $list_table->display();
            $table_html = ob_get_clean();

            wp_send_json_success([
                'html' => $table_html,
                'organization' => $organization,
                'count' => count($repositories)
            ]);

        } catch (\Exception $e) {
            wp_send_json_error('Failed to render table: ' . $e->getMessage());
        }
    }

    /**
     * Verify AJAX nonce.
     */
    private function verify_nonce(): void {
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'git_updater_fsm')) {
            wp_send_json_error('Invalid nonce');
        }

        if (!current_user_can('install_plugins')) {
            wp_send_json_error('Insufficient permissions');
        }
    }

    /**
     * Mock repository fetching for testing.
     * In production, this would integrate with actual Git APIs.
     *
     * @param string $organization Organization name.
     * @return array Mock repository data.
     */
    private function mock_fetch_repositories(string $organization): array {
        return [
            [
                'id' => 1,
                'name' => 'sample-plugin',
                'full_name' => $organization . '/sample-plugin',
                'description' => 'A sample WordPress plugin',
                'html_url' => "https://github.com/{$organization}/sample-plugin",
                'clone_url' => "https://github.com/{$organization}/sample-plugin.git",
                'default_branch' => 'main',
                'updated_at' => date('c', strtotime('-2 days'))
            ],
            [
                'id' => 2,
                'name' => 'another-plugin',
                'full_name' => $organization . '/another-plugin',
                'description' => 'Another WordPress plugin',
                'html_url' => "https://github.com/{$organization}/another-plugin",
                'clone_url' => "https://github.com/{$organization}/another-plugin.git",
                'default_branch' => 'master',
                'updated_at' => date('c', strtotime('-1 week'))
            ]
        ];
    }
}
