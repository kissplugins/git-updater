<?php
/**
 * FSM Bootstrap for Git Updater.
 *
 * @package Git_Updater
 */

namespace Fragen\Git_Updater;

use Fragen\Git_Updater\Container;
use Fragen\Git_Updater\FSM\GitUpdaterStateManager;
use Fragen\Git_Updater\Services\GitUpdaterIntegrationService;

/**
 * FSM Bootstrap class.
 * 
 * Initializes the FSM system for Git Updater integration.
 * This class sets up the service container and registers FSM services.
 */
class FSMBootstrap {
    /**
     * Container instance.
     *
     * @var Container
     */
    private Container $container;

    /**
     * State manager instance.
     *
     * @var GitUpdaterStateManager
     */
    private GitUpdaterStateManager $state_manager;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->container = new Container();
        $this->register_fsm_services();
    }

    /**
     * Initialize the FSM system.
     */
    public function init(): void {
        // Initialize state manager
        $this->state_manager = $this->container->get(GitUpdaterStateManager::class);
        
        // Register WordPress hooks
        $this->register_hooks();
        
        // Initialize SSE endpoint
        $this->init_sse_endpoint();
        
        // Log FSM initialization
        error_log('Git Updater FSM: System initialized successfully');
    }

    /**
     * Register FSM services in the container.
     */
    private function register_fsm_services(): void {
        // Register FSM as core service
        $this->container->singleton(GitUpdaterStateManager::class, function($container) {
            return new GitUpdaterStateManager();
        });
        
        // Register Git Updater integration service
        $this->container->singleton(GitUpdaterIntegrationService::class, function($container) {
            return new GitUpdaterIntegrationService(
                $container->get(GitUpdaterStateManager::class)
            );
        });
    }

    /**
     * Register WordPress hooks for FSM integration.
     */
    private function register_hooks(): void {
        // Admin hooks
        add_action('admin_init', [$this, 'admin_init']);
        add_action('admin_enqueue_scripts', [$this, 'enqueue_fsm_assets']);
        
        // AJAX hooks for FSM operations
        add_action('wp_ajax_git_updater_fsm_install', [$this, 'ajax_fsm_install']);
        add_action('wp_ajax_git_updater_fsm_update', [$this, 'ajax_fsm_update']);
        add_action('wp_ajax_git_updater_fsm_switch_branch', [$this, 'ajax_fsm_switch_branch']);
        add_action('wp_ajax_git_updater_fsm_get_state', [$this, 'ajax_fsm_get_state']);
        
        // Hook into existing Git Updater operations
        add_action('gu_install_plugin', [$this, 'hook_install_plugin'], 10, 2);
        add_action('gu_update_plugin', [$this, 'hook_update_plugin'], 10, 2);
    }

    /**
     * Initialize admin functionality.
     */
    public function admin_init(): void {
        // Check if we're on a Git Updater admin page
        if (!$this->is_git_updater_page()) {
            return;
        }
        
        // Initialize FSM for admin pages
        $this->init_admin_fsm();
    }

    /**
     * Enqueue FSM assets for admin pages.
     *
     * @param string $hook_suffix Current admin page hook suffix.
     */
    public function enqueue_fsm_assets(string $hook_suffix): void {
        // Only enqueue on Git Updater pages
        if (!$this->is_git_updater_page()) {
            return;
        }
        
        // Enqueue FSM JavaScript
        wp_enqueue_script(
            'git-updater-fsm',
            plugin_dir_url(PLUGIN_FILE) . 'js/git-updater-fsm.js',
            ['jquery'],
            '1.0.0',
            true
        );
        
        // Enqueue FSM CSS
        wp_enqueue_style(
            'git-updater-fsm',
            plugin_dir_url(PLUGIN_FILE) . 'css/git-updater-fsm.css',
            [],
            '1.0.0'
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
     * Initialize SSE endpoint for real-time updates.
     */
    private function init_sse_endpoint(): void {
        add_action('wp_ajax_git_updater_sse', [$this, 'sse_endpoint']);
        add_action('wp_ajax_nopriv_git_updater_sse', [$this, 'sse_endpoint']);
    }

    /**
     * SSE endpoint for real-time updates.
     */
    public function sse_endpoint(): void {
        // Verify nonce
        if (!wp_verify_nonce($_GET['nonce'] ?? '', 'git_updater_fsm')) {
            wp_die('Invalid nonce');
        }
        
        // Set SSE headers
        header('Content-Type: text/event-stream');
        header('Cache-Control: no-cache');
        header('Connection: keep-alive');
        
        // Send initial connection event
        echo "event: connected\n";
        echo "data: " . json_encode(['timestamp' => time()]) . "\n\n";
        flush();
        
        // Stream events
        $last_check = time();
        while (true) {
            // Check for new events
            $events = get_transient('git_updater_sse_events') ?: [];
            $new_events = array_filter($events, function($event) use ($last_check) {
                return $event['timestamp'] > $last_check;
            });
            
            // Send new events
            foreach ($new_events as $event) {
                echo "event: {$event['type']}\n";
                echo "data: " . json_encode($event['data']) . "\n\n";
                flush();
            }
            
            $last_check = time();
            
            // Break if connection is closed
            if (connection_aborted()) {
                break;
            }
            
            // Sleep for 1 second
            sleep(1);
        }
    }

    /**
     * AJAX handler for FSM install operation.
     */
    public function ajax_fsm_install(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'git_updater_fsm')) {
            wp_die('Invalid nonce');
        }
        
        $repo_url = sanitize_url($_POST['repo_url'] ?? '');
        $branch = sanitize_text_field($_POST['branch'] ?? 'main');
        
        if (empty($repo_url)) {
            wp_send_json_error('Repository URL is required');
        }
        
        $integration_service = $this->container->get(GitUpdaterIntegrationService::class);
        $result = $integration_service->install_via_git_updater($repo_url, $branch);
        
        if ($result) {
            wp_send_json_success(['message' => 'Installation started successfully']);
        } else {
            wp_send_json_error('Installation failed to start');
        }
    }

    /**
     * AJAX handler for FSM update operation.
     */
    public function ajax_fsm_update(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'git_updater_fsm')) {
            wp_die('Invalid nonce');
        }
        
        $plugin_file = sanitize_text_field($_POST['plugin_file'] ?? '');
        
        if (empty($plugin_file)) {
            wp_send_json_error('Plugin file is required');
        }
        
        $integration_service = $this->container->get(GitUpdaterIntegrationService::class);
        $result = $integration_service->check_for_updates($plugin_file);
        
        wp_send_json_success(['has_update' => $result]);
    }

    /**
     * AJAX handler for FSM branch switch operation.
     */
    public function ajax_fsm_switch_branch(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'git_updater_fsm')) {
            wp_die('Invalid nonce');
        }
        
        $plugin_file = sanitize_text_field($_POST['plugin_file'] ?? '');
        $new_branch = sanitize_text_field($_POST['new_branch'] ?? '');
        
        if (empty($plugin_file) || empty($new_branch)) {
            wp_send_json_error('Plugin file and branch are required');
        }
        
        $integration_service = $this->container->get(GitUpdaterIntegrationService::class);
        $result = $integration_service->switch_branch($plugin_file, $new_branch);
        
        if ($result) {
            wp_send_json_success(['message' => 'Branch switch completed successfully']);
        } else {
            wp_send_json_error('Branch switch failed');
        }
    }

    /**
     * AJAX handler for getting FSM state.
     */
    public function ajax_fsm_get_state(): void {
        // Verify nonce
        if (!wp_verify_nonce($_POST['nonce'] ?? '', 'git_updater_fsm')) {
            wp_die('Invalid nonce');
        }
        
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
     * Hook into existing Git Updater install operation.
     *
     * @param string $repo_url Repository URL.
     * @param array $config Installation configuration.
     */
    public function hook_install_plugin(string $repo_url, array $config): void {
        // Update FSM state when Git Updater installs a plugin
        $this->state_manager->transition($repo_url, \Fragen\Git_Updater\Enums\PluginState::GIT_UPDATER_INSTALLING);
    }

    /**
     * Hook into existing Git Updater update operation.
     *
     * @param string $plugin_file Plugin file.
     * @param array $config Update configuration.
     */
    public function hook_update_plugin(string $plugin_file, array $config): void {
        // Update FSM state when Git Updater updates a plugin
        $this->state_manager->transition($plugin_file, \Fragen\Git_Updater\Enums\PluginState::GIT_UPDATER_UPDATING);
    }

    /**
     * Check if current page is a Git Updater admin page.
     *
     * @return bool True if on Git Updater page.
     */
    private function is_git_updater_page(): bool {
        $screen = get_current_screen();
        return $screen && (
            strpos($screen->id, 'git-updater') !== false ||
            strpos($screen->base, 'git-updater') !== false
        );
    }

    /**
     * Initialize FSM for admin pages.
     */
    private function init_admin_fsm(): void {
        // Initialize any admin-specific FSM functionality
        // This could include setting up UI enhancements, etc.
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

    /**
     * Get container instance.
     *
     * @return Container Container instance.
     */
    public function get_container(): Container {
        return $this->container;
    }

    /**
     * Get state manager instance.
     *
     * @return GitUpdaterStateManager State manager instance.
     */
    public function get_state_manager(): GitUpdaterStateManager {
        return $this->state_manager;
    }
}
