<?php
declare(strict_types=1);

require __DIR__ . '/inc/bootstrap.php';
require __DIR__ . '/inc/layout.php';
require __DIR__ . '/inc/auth_page.php';

if (current_user()) {
    redirect('index.php');
}

$v = ['name' => '', 'business' => '', 'email' => ''];
if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    $v['name'] = trim(mb_substr((string) ($_POST['name'] ?? ''), 0, 80));
    $v['business'] = trim(mb_substr((string) ($_POST['business'] ?? ''), 0, 120));
    $v['email'] = strtolower(trim((string) ($_POST['email'] ?? '')));
    $pass = (string) ($_POST['password'] ?? '');
    if (!csrf_valid()) {
        flash('Your session expired. Please try again.', 'error');
    } elseif (!rate_limit('register:' . client_ip(), 6, 3600)) {
        flash('Too many sign-ups from this network. Try again later.', 'error');
    } elseif ($v['name'] === '' || !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) {
        flash('Enter your name and a valid email.', 'error');
    } elseif (strlen($pass) < 8 || strlen($pass) > 200) {
        flash('Use a password of at least 8 characters.', 'error');
    } elseif (q('SELECT id FROM fa_users WHERE email = ?', [$v['email']])->fetch()) {
        flash('That email already has a workspace. Sign in instead.', 'error');
    } else {
        q('INSERT INTO fa_users (email, name, business_name, pass_hash) VALUES (?, ?, ?, ?)', [$v['email'], $v['name'], $v['business'], password_hash($pass, PASSWORD_DEFAULT)]);
        login_user(q('SELECT * FROM fa_users WHERE id = ?', [(int) db()->lastInsertId()])->fetch());
        redirect('index.php');
    }
}

auth_shell_open('Create workspace', 'Create your workspace', 'Free while in the PayPal sandbox. No card needed.');
?>
    <form method="post" class="form">
      <?= csrf_field() ?>
      <label>Your name<input name="name" required maxlength="80" value="<?= e($v['name']) ?>" autocomplete="name"></label>
      <label>Studio or business name <span class="muted">(clients see it)</span><input name="business" maxlength="120" value="<?= e($v['business']) ?>" autocomplete="organization"></label>
      <label>Email<input type="email" name="email" required maxlength="190" value="<?= e($v['email']) ?>" autocomplete="email"></label>
      <label>Password<input type="password" name="password" required minlength="8" maxlength="200" autocomplete="new-password"></label>
      <button class="btn btn-primary btn-block" type="submit">Create workspace</button>
    </form>
    <p class="muted center">Already have one? <a href="<?= e(url('login.php')) ?>">Sign in</a></p>
<?php
auth_shell_close();
