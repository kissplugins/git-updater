<?php
/**
 * Git Updater State Manager - FSM Core for Git Updater Integration.
 *
 * @package Git_Updater\FSM
 */

namespace Fragen\Git_Updater\FSM;

use Fragen\Git_Updater\Enums\PluginState;
use Exception;

/**
 * ⚠️ ⚠️ ⚠️ CRITICAL FSM STATE MANAGER - HANDLE WITH EXTREME CARE ⚠️ ⚠️ ⚠️
 *
 * This is the backend heart of Git Updater's FSM state management system.
 * Adapted from KISS SBI's StateManager for Git Updater integration.
 * It manages state transitions, validation, and persistence for all repositories.
 *
 * BEFORE MODIFYING THIS CLASS:
 * 1. Test all state transitions manually
 * 2. Verify SSE integration still works
 * 3. Check frontend FSM synchronization
 * 4. Test with Git Updater operations
 * 5. Validate state persistence across requests
 *
 * CRITICAL AREAS - DO NOT MODIFY WITHOUT EXTENSIVE TESTING:
 * - State transition validation logic
 * - State persistence and caching
 * - SSE event emission
 * - State refresh mechanisms
 * - Error state handling
 *
 * INTEGRATION POINTS:
 * - Git Updater installation pipeline
 * - Git Updater update system
 * - Branch switching operations
 * - Frontend FSM (JavaScript)
 * - SSE real-time updates
 * - AJAX handlers
 */
class GitUpdaterStateManager {
    /**
     * Repository states cache.
     *
     * @var array<string, PluginState>
     */
    protected array $states = [];

    /**
     * State metadata storage for additional FSM context.
     * Stores metadata like Git Updater flags, error context, etc.
     *
     * @var array<string, array>
     */
    private array $state_metadata = [];

    /**
     * Allowed transitions cache.
     *
     * @var array<string, array<string>>
     */
    private array $allowed_transitions = [];

    /**
     * Cache expiration time (5 minutes).
     */
    private const CACHE_EXPIRATION = 5 * 60; // 5 minutes in seconds

    /**
     * Event log transient TTL (1 day) and max entries per repo.
     */
    private const EVENT_LOG_TTL = 24 * 60 * 60; // 1 day in seconds
    private const EVENT_LOG_LIMIT = 30;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->init_transitions();
        $this->load_states();
    }

    /**
     * Initialize allowed transitions for Git Updater FSM.
     */
    private function init_transitions(): void {
        $this->allowed_transitions = [
            // Core states
            PluginState::UNKNOWN->value => [
                PluginState::CHECKING->value,
                PluginState::AVAILABLE->value,
                PluginState::NOT_PLUGIN->value,
                PluginState::ERROR->value,
                PluginState::INSTALLED_INACTIVE->value,
                PluginState::INSTALLED_ACTIVE->value
            ],
            PluginState::CHECKING->value => [
                PluginState::AVAILABLE->value,
                PluginState::NOT_PLUGIN->value,
                PluginState::ERROR->value
            ],
            PluginState::AVAILABLE->value => [
                PluginState::GIT_UPDATER_INSTALLING->value,
                PluginState::INSTALLED_INACTIVE->value,
                PluginState::ERROR->value
            ],
            PluginState::INSTALLED_INACTIVE->value => [
                PluginState::INSTALLED_ACTIVE->value,
                PluginState::GIT_UPDATER_UPDATING->value,
                PluginState::ERROR->value
            ],
            PluginState::INSTALLED_ACTIVE->value => [
                PluginState::INSTALLED_INACTIVE->value,
                PluginState::GIT_UPDATER_UPDATING->value,
                PluginState::BRANCH_SWITCHING->value,
                PluginState::ERROR->value
            ],
            PluginState::NOT_PLUGIN->value => [
                PluginState::CHECKING->value,
                PluginState::AVAILABLE->value
            ],
            PluginState::ERROR->value => [
                PluginState::CHECKING->value,
                PluginState::AVAILABLE->value,
                PluginState::NOT_PLUGIN->value
            ],

            // Git Updater specific states
            PluginState::GIT_UPDATER_INSTALLING->value => [
                PluginState::INSTALLED_INACTIVE->value,
                PluginState::GIT_UPDATER_MANAGED->value,
                PluginState::INSTALLATION_FAILED->value,
                PluginState::ERROR->value
            ],
            PluginState::GIT_UPDATER_UPDATING->value => [
                PluginState::INSTALLED_ACTIVE->value,
                PluginState::GIT_UPDATER_MANAGED->value,
                PluginState::UPDATE_FAILED->value,
                PluginState::ERROR->value
            ],
            PluginState::GIT_UPDATER_MANAGED->value => [
                PluginState::INSTALLED_ACTIVE->value,
                PluginState::UPDATE_AVAILABLE->value,
                PluginState::GIT_UPDATER_UPDATING->value,
                PluginState::BRANCH_SWITCHING->value,
                PluginState::ERROR->value
            ],
            PluginState::UPDATE_AVAILABLE->value => [
                PluginState::GIT_UPDATER_UPDATING->value,
                PluginState::GIT_UPDATER_MANAGED->value,
                PluginState::ERROR->value
            ],
            PluginState::BRANCH_SWITCHING->value => [
                PluginState::INSTALLED_ACTIVE->value,
                PluginState::GIT_UPDATER_MANAGED->value,
                PluginState::ERROR->value
            ],
            PluginState::INSTALLATION_FAILED->value => [
                PluginState::AVAILABLE->value,
                PluginState::GIT_UPDATER_INSTALLING->value,
                PluginState::ERROR->value
            ],
            PluginState::UPDATE_FAILED->value => [
                PluginState::INSTALLED_ACTIVE->value,
                PluginState::GIT_UPDATER_MANAGED->value,
                PluginState::GIT_UPDATER_UPDATING->value,
                PluginState::ERROR->value
            ],
        ];
    }

    /**
     * Get current state for a repository.
     *
     * @param string $repository_url Repository URL.
     * @return PluginState Current state.
     */
    public function get_state(string $repository_url): PluginState {
        $key = $this->get_cache_key($repository_url);
        
        if (!isset($this->states[$key])) {
            $this->states[$key] = PluginState::UNKNOWN;
        }
        
        return $this->states[$key];
    }

    /**
     * Set state for a repository.
     *
     * @param string $repository_url Repository URL.
     * @param PluginState $state New state.
     * @param bool $force Force state change without validation.
     * @return bool True if state was set successfully.
     */
    public function set_state(string $repository_url, PluginState $state, bool $force = false): bool {
        $key = $this->get_cache_key($repository_url);
        $current_state = $this->get_state($repository_url);

        // Validate transition unless forced
        if (!$force && !$this->can_transition($current_state, $state)) {
            error_log("Git Updater FSM: Invalid transition from {$current_state->value} to {$state->value} for {$repository_url}");
            return false;
        }

        $this->states[$key] = $state;
        $this->save_states();
        
        // Log the transition
        $this->log_event($repository_url, 'state_transition', [
            'from' => $current_state->value,
            'to' => $state->value,
            'forced' => $force,
            'timestamp' => time()
        ]);

        return true;
    }

    /**
     * Transition to a new state with validation.
     *
     * @param string $repository_url Repository URL.
     * @param PluginState $new_state Target state.
     * @param array $metadata Optional metadata for the transition.
     * @return bool True if transition was successful.
     */
    public function transition(string $repository_url, PluginState $new_state, array $metadata = []): bool {
        $current_state = $this->get_state($repository_url);
        
        if (!$this->can_transition($current_state, $new_state)) {
            error_log("Git Updater FSM: Invalid transition from {$current_state->value} to {$new_state->value} for {$repository_url}");
            return false;
        }

        // Set the new state
        if (!$this->set_state($repository_url, $new_state)) {
            return false;
        }

        // Store metadata if provided
        if (!empty($metadata)) {
            $this->set_metadata($repository_url, $metadata);
        }

        // Broadcast the state change
        $this->broadcast('git_updater_state_changed', [
            'repository' => $repository_url,
            'old_state' => $current_state->value,
            'new_state' => $new_state->value,
            'metadata' => $metadata,
            'timestamp' => time()
        ]);

        return true;
    }

    /**
     * Check if a state transition is allowed.
     *
     * @param PluginState $from Current state.
     * @param PluginState $to Target state.
     * @return bool True if transition is allowed.
     */
    public function can_transition(PluginState $from, PluginState $to): bool {
        // Same state is always allowed
        if ($from === $to) {
            return true;
        }

        $allowed = $this->allowed_transitions[$from->value] ?? [];
        return in_array($to->value, $allowed, true);
    }

    /**
     * Get metadata for a repository.
     *
     * @param string $repository_url Repository URL.
     * @return array Metadata array.
     */
    public function get_metadata(string $repository_url): array {
        $key = $this->get_cache_key($repository_url);
        return $this->state_metadata[$key] ?? [];
    }

    /**
     * Set metadata for a repository.
     *
     * @param string $repository_url Repository URL.
     * @param array $metadata Metadata to set.
     */
    public function set_metadata(string $repository_url, array $metadata): void {
        $key = $this->get_cache_key($repository_url);
        $this->state_metadata[$key] = array_merge($this->get_metadata($repository_url), $metadata);
        $this->save_metadata();
    }

    /**
     * Broadcast an event via SSE.
     *
     * @param string $event_type Event type.
     * @param array $data Event data.
     */
    public function broadcast(string $event_type, array $data): void {
        // Store event for SSE pickup
        $events = get_transient('git_updater_sse_events') ?: [];
        $events[] = [
            'type' => $event_type,
            'data' => $data,
            'timestamp' => time()
        ];
        
        // Keep only last 50 events
        $events = array_slice($events, -50);
        set_transient('git_updater_sse_events', $events, 300); // 5 minutes
    }

    /**
     * Generate cache key for repository.
     *
     * @param string $repository_url Repository URL.
     * @return string Cache key.
     */
    private function get_cache_key(string $repository_url): string {
        return 'git_updater_' . md5($repository_url);
    }

    /**
     * Load states from WordPress transients.
     */
    private function load_states(): void {
        $cached_states = get_transient('git_updater_fsm_states');
        if ($cached_states && is_array($cached_states)) {
            foreach ($cached_states as $key => $state_value) {
                if (is_string($state_value)) {
                    $this->states[$key] = PluginState::from($state_value);
                }
            }
        }

        $cached_metadata = get_transient('git_updater_fsm_metadata');
        if ($cached_metadata && is_array($cached_metadata)) {
            $this->state_metadata = $cached_metadata;
        }
    }

    /**
     * Save states to WordPress transients.
     */
    private function save_states(): void {
        $states_to_save = [];
        foreach ($this->states as $key => $state) {
            $states_to_save[$key] = $state->value;
        }
        set_transient('git_updater_fsm_states', $states_to_save, self::CACHE_EXPIRATION);
    }

    /**
     * Save metadata to WordPress transients.
     */
    private function save_metadata(): void {
        set_transient('git_updater_fsm_metadata', $this->state_metadata, self::CACHE_EXPIRATION);
    }

    /**
     * Log an event for debugging and audit trail.
     *
     * @param string $repository_url Repository URL.
     * @param string $event_type Event type.
     * @param array $data Event data.
     */
    private function log_event(string $repository_url, string $event_type, array $data): void {
        $key = 'git_updater_events_' . md5($repository_url);
        $events = get_transient($key) ?: [];
        
        $events[] = [
            'type' => $event_type,
            'data' => $data,
            'timestamp' => time()
        ];
        
        // Keep only recent events
        $events = array_slice($events, -self::EVENT_LOG_LIMIT);
        set_transient($key, $events, self::EVENT_LOG_TTL);
    }
}
