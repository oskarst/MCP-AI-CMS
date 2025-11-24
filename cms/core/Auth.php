<?php
/**
 * Auth - Handles user authentication for the admin panel.
 *
 * Uses PHP sessions and password_verify() for authentication.
 * User data is stored in /cms/config/users.json
 */

class Auth
{
    private string $usersFile;

    /**
     * @param string $usersFile Absolute path to users.json
     */
    public function __construct(string $usersFile)
    {
        $this->usersFile = $usersFile;

        // Start session if not already started
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    /**
     * Attempt to log in a user.
     *
     * @param string $username Username
     * @param string $password Password
     * @return bool True if login successful
     */
    public function login(string $username, string $password): bool
    {
        $users = $this->loadUsers();

        foreach ($users as $user) {
            if ($user['username'] === $username) {
                if (password_verify($password, $user['password_hash'])) {
                    // Set session
                    $_SESSION['cms_user'] = [
                        'username' => $user['username'],
                        'email' => $user['email'],
                        'role' => $user['role'],
                    ];
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Log out the current user.
     *
     * @return void
     */
    public function logout(): void
    {
        unset($_SESSION['cms_user']);
        session_destroy();
    }

    /**
     * Check if a user is currently logged in.
     *
     * @return bool True if logged in
     */
    public function isLoggedIn(): bool
    {
        return isset($_SESSION['cms_user']);
    }

    /**
     * Get the currently logged-in user.
     *
     * @return array|null User data or null if not logged in
     */
    public function getCurrentUser(): ?array
    {
        return $_SESSION['cms_user'] ?? null;
    }

    /**
     * Require authentication (redirect to login if not authenticated).
     *
     * @return void
     */
    public function requireAuth(): void
    {
        if (!$this->isLoggedIn()) {
            header('Location: /cms/admin/login.php');
            exit;
        }
    }

    /**
     * Load users from the JSON file.
     *
     * @return array Array of users
     */
    private function loadUsers(): array
    {
        if (!file_exists($this->usersFile)) {
            return [];
        }

        $json = file_get_contents($this->usersFile);
        $data = json_decode($json, true);

        return $data['users'] ?? [];
    }

    /**
     * Save users to the JSON file.
     *
     * @param array $users Array of users
     * @return void
     * @throws Exception if save fails
     */
    public function saveUsers(array $users): void
    {
        $data = ['users' => $users];
        $json = json_encode($data, JSON_PRETTY_PRINT);

        if (file_put_contents($this->usersFile, $json) === false) {
            throw new Exception("Failed to save users file");
        }
    }

    /**
     * Create a new user.
     *
     * @param string $username Username
     * @param string $email Email
     * @param string $password Plain text password
     * @param string $role User role (default: 'owner')
     * @return void
     * @throws Exception if user already exists
     */
    public function createUser(string $username, string $email, string $password, string $role = 'owner'): void
    {
        $users = $this->loadUsers();

        // Check if username already exists
        foreach ($users as $user) {
            if ($user['username'] === $username) {
                throw new Exception("Username already exists");
            }
        }

        // Add new user
        $users[] = [
            'username' => $username,
            'email' => $email,
            'password_hash' => password_hash($password, PASSWORD_DEFAULT),
            'role' => $role,
        ];

        $this->saveUsers($users);
    }
}
