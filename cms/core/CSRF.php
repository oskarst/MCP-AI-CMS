<?php
/**
 * CSRF - Simple CSRF token generation and validation.
 */

class CSRF
{
    /**
     * Generate a CSRF token and store it in the session.
     *
     * @return string The generated token
     */
    public static function generateToken(): string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        $token = bin2hex(random_bytes(32));
        $_SESSION['csrf_token'] = $token;

        return $token;
    }

    /**
     * Get the current CSRF token from the session.
     *
     * @return string|null The token or null if not set
     */
    public static function getToken(): ?string
    {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }

        return $_SESSION['csrf_token'] ?? null;
    }

    /**
     * Validate a CSRF token.
     *
     * @param string $token Token to validate
     * @return bool True if valid
     */
    public static function validateToken(string $token): bool
    {
        $sessionToken = self::getToken();

        if (!$sessionToken) {
            return false;
        }

        return hash_equals($sessionToken, $token);
    }

    /**
     * Generate a hidden input field with the CSRF token.
     *
     * @return string HTML input field
     */
    public static function inputField(): string
    {
        // Get existing token or generate new one if none exists
        $token = self::getToken();
        if (!$token) {
            $token = self::generateToken();
        }
        return '<input type="hidden" name="csrf_token" value="' . htmlspecialchars($token) . '">';
    }

    /**
     * Verify CSRF token from POST request and die if invalid.
     *
     * @return void
     */
    public static function verifyOrDie(): void
    {
        $token = $_POST['csrf_token'] ?? '';

        if (!self::validateToken($token)) {
            http_response_code(403);
            die('CSRF token validation failed');
        }
    }
}
