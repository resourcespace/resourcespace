<?php

declare(strict_types=1);

namespace Montala\ResourceSpace\Tests\Support;

use Closure;
use RuntimeException;

/**
 * Own records created for a single integration test.
 */
final class IntegrationFixtures
{
    private array $resources = [];
    private array $collections = [];
    private array $fields = [];
    private array $users = [];
    private array $groups = [];
    private array $cleanup = [];
    private array $settings = [];
    private array|false $current_user;

    /**
     * Capture the current user and any globals the test will change.
     *
     * @param list<string> $settings Global names to restore during cleanup.
     */
    public function __construct(private array $setting_names = [])
    {
        foreach ($setting_names as $name) {
            if (array_key_exists($name, $GLOBALS)) {
                $this->settings[$name] = $GLOBALS[$name];
            }
        }
        $user = (int) ($GLOBALS['userref'] ?? 0);
        $this->current_user = $user > 0 ? get_user($user) : false;
    }

    /**
     * Create and track a resource using the normal creation helper.
     *
     * @param mixed ...$arguments Arguments for create_resource().
     * @return int Resource reference.
     */
    public function resource(mixed ...$arguments): int
    {
        $ref = create_resource(...$arguments);
        if (!is_int($ref) || $ref <= 0) {
            throw new RuntimeException('Could not create a resource fixture.');
        }
        $this->resources[] = $ref;
        return $ref;
    }

    /**
     * Track a resource created by the operation being tested.
     *
     * @param int $ref Resource reference.
     * @return int The same resource reference.
     */
    public function trackResource(int $ref): int
    {
        $this->resources[] = $ref;
        return $ref;
    }

    /**
     * Create and track a collection using the normal creation helper.
     *
     * @param mixed ...$arguments Arguments for create_collection().
     * @return int Collection reference.
     */
    public function collection(mixed ...$arguments): int
    {
        $ref = create_collection(...$arguments);
        if (!is_int($ref) || $ref <= 0) {
            throw new RuntimeException('Could not create a collection fixture.');
        }
        $this->collections[] = $ref;
        return $ref;
    }

    /**
     * Track a collection created by the operation being tested.
     *
     * @param int $ref Collection reference.
     * @return int The same collection reference.
     */
    public function trackCollection(int $ref): int
    {
        $this->collections[] = $ref;
        return $ref;
    }

    /**
     * Create and track a metadata field and its test options.
     *
     * @param mixed ...$arguments Arguments for create_resource_type_field().
     * @return int Field reference.
     */
    public function field(mixed ...$arguments): int
    {
        $ref = create_resource_type_field(...$arguments);
        if (!is_int($ref) || $ref <= 0) {
            throw new RuntimeException('Could not create a metadata field fixture.');
        }
        $this->fields[] = $ref;
        return $ref;
    }

    /**
     * Create and track a user and its default collection.
     *
     * @param string $username A unique test username.
     * @param int $group User group reference.
     * @return int User reference.
     */
    public function user(string $username, int $group = 2): int
    {
        $ref = new_user($username, $group);
        if (!is_int($ref) || $ref <= 0) {
            throw new RuntimeException('Could not create a user fixture.');
        }
        return $this->trackUser($ref);
    }

    /**
     * Track a user created by the operation being tested and their default collection.
     * @param int $ref User reference.
     * @return int The same user reference.
     */
    public function trackUser(int $ref): int
    {
        $this->users[] = $ref;
        $user = get_user($ref);
        if (isset($user['current_collection'])) {
            $this->collections[] = (int) $user['current_collection'];
        }
        return $ref;
    }

    /**
     * Create a user group to remove after its fixture users.
     *
     * @param string $name Unique group name.
     * @param string $permissions Comma-separated ResourceSpace permissions.
     * @return int Group reference.
     */
    public function group(string $name, string $permissions): int
    {
        $ref = save_usergroup(0, ['name' => $name, 'permissions' => $permissions]);
        if (!is_int($ref) || $ref <= 0) {
            throw new RuntimeException('Could not create a user group fixture.');
        }
        $this->groups[] = $ref;
        return $ref;
    }

    /**
     * Activate an installed plugin and restore its activation and configuration at cleanup.
     * @param string $name Plugin directory and registration name.
     * @return void
     */
    public function plugin(string $name): void
    {
        $original = ps_query('SELECT inst_version, config, config_json FROM plugins WHERE name = ?', ['s', $name]);
        $this->onCleanup(static function () use ($name, $original): void {
            if ($original === []) {
                ps_query('DELETE FROM plugins WHERE name = ?', ['s', $name]);
            } else {
                ps_query('UPDATE plugins SET inst_version = ?, config = ?, config_json = ? WHERE name = ?', [
                    'd', $original[0]['inst_version'], 's', $original[0]['config'],
                    's', $original[0]['config_json'], 's', $name,
                ]);
            }
            clear_query_cache('plugins');
        });
        if (!is_plugin_activated($name) && !activate_plugin($name)) {
            throw new RuntimeException('Could not activate test plugin ' . $name . '.');
        }
    }

    /**
     * Register cleanup for a fixture outside the standard resource, collection, field and user types.
     *
     * @param Closure(): mixed $cleanup Removes the fixture before its parent records are deleted.
     * @return void
     */
    public function onCleanup(Closure $cleanup): void
    {
        $this->cleanup[] = $cleanup;
    }

    /**
     * Restore test settings and remove records in dependency order.
     *
     * @return void
     */
    public function cleanUp(): void
    {
        if (is_array($this->current_user) && ($GLOBALS['userref'] ?? null) !== $this->current_user['ref']) {
            setup_user($this->current_user);
        }
        $this->restoreSettings();

        $deletion_state = $GLOBALS['resource_deletion_state'] ?? null;
        $post = $_POST;
        try {
            $GLOBALS['resource_deletion_state'] = null;
            foreach (array_reverse($this->cleanup) as $cleanup) {
                $cleanup();
            }
            foreach (array_reverse($this->resources) as $ref) {
                if (get_resource_data($ref, false)) {
                    delete_resource($ref);
                }
            }
            foreach (array_reverse($this->collections) as $ref) {
                if (get_collection($ref)) {
                    delete_collection($ref);
                }
            }
            foreach (array_reverse($this->fields) as $ref) {
                if (get_resource_type_field($ref)) {
                    delete_resource_type_field($ref);
                }
            }
            $_POST = ['deleteme' => 'yes'];
            foreach (array_reverse($this->users) as $ref) {
                if (get_user($ref)) {
                    save_user($ref);
                }
            }
            foreach (array_reverse($this->groups) as $ref) {
                if (get_usergroup($ref)) {
                    delete_usergroup($ref);
                }
            }
        } finally {
            $_POST = $post;
            $GLOBALS['resource_deletion_state'] = $deletion_state;
            $this->restoreSettings();
        }
    }

    /**
     * Restore saved globals and remove test globals that did not previously exist.
     *
     * @return void
     */
    private function restoreSettings(): void
    {
        foreach ($this->setting_names as $name) {
            unset($GLOBALS[$name]);
        }
        foreach ($this->settings as $name => $value) {
            $GLOBALS[$name] = $value;
        }
    }
}
