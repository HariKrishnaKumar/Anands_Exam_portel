-- Reset the brute-force throttle.
--
-- src/php/includes/auth.php locks an account after BRUTE_FORCE_MAX_ATTEMPTS (5)
-- wrong-password attempts inside BRUTE_FORCE_WINDOW (900s). Only rows with
-- attempt_type = 'wrong_password' count toward that (invalid_email is
-- deliberately excluded there), so this clears the throttle without touching
-- the rest of the login-audit history the admin UI displays.
DELETE FROM failed_login_log WHERE attempt_type = 'wrong_password';
