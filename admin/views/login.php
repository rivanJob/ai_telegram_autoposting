<h1>Admin Login</h1>
<form method="post">
  <input type="hidden" name="_csrf" value="<?= htmlspecialchars(AutoPoster\Security\Csrf::token()) ?>">
  <label>Username <input name="username" required></label>
  <label>Password <input name="password" type="password" required></label>
  <label>TOTP <input name="totp" inputmode="numeric" required></label>
  <button type="submit">Sign in</button>
</form>
