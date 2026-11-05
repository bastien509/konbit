<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/auth_page.php';

if (current_user()) {
    redirect('index.php');
}

$email = '';
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $email = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pass = (string) ($_POST['password'] ?? '');
    if (!csrf_valid()) {
        flash('Your session expired. Please try again.', 'error');
    } elseif (!rate_limit('login-ip:' . client_ip(), 20, 900) || !rate_limit('login-email:' . $email, 8, 900)) {
        flash('Too many attempts. Wait 15 minutes and try again.', 'error');
    } else {
        $u = q('SELECT * FROM fa_users WHERE email = ?', [$email])->fetch();
        // Always run password_verify so timing does not reveal which emails exist.
        $ok = password_verify($pass, $u['pass_hash'] ?? '$2y$10$uSB4e5sqUrU96rfPWGScuuVkTsS.RzeIExbybtPFXG180d/2oTOh.');
        if ($u && $ok && !(int) $u['is_banned']) {
            login_user($u);
            redirect('index.php');
        }
        flash('Wrong email or password.', 'error');
    }
}

auth_shell_open('Sign in', 'Welcome back', 'Sign in to pay your crew.');
?>
    <form method="post" class="form" autocomplete="on">
      <?= csrf_field() ?>
      <label>Email<input type="email" name="email" required maxlength="190" value="<?= e($email) ?>" autocomplete="email"></label>
      <label>Password<input type="password" name="password" required maxlength="200" autocomplete="current-password"></label>
      <button class="btn btn-primary btn-block" type="submit">Sign in</button>
    </form>
    <p class="muted center">New here? <a href="<?= e(url('register.php')) ?>">Create a free workspace</a></p>
<?php
auth_shell_close();
