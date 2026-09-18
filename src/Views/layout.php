<?php
/**
 * Global layout — header/nav/footer per planning/00-portfolio/design-system.md
 * §Global layout and artcollect-design-system.md §6. `$content` and
 * optional `$title` are provided by View::render(). `$critical` (bool),
 * when set true by a page, marks <main> with the `.critical-flow` class —
 * see artcollect-design-system.md §7: deposit/booking payment and the
 * vendor-cancellation-incident surface carry zero decoration. `$currentUser`
 * is injected by View::render() (see src/Core/View.php) for the nav below.
 */
use EventCo\Core\View;

$pageTitle = isset($title) ? $title . ' — event.co.ke' : 'event.co.ke';
$isCritical = $critical ?? false;
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars($pageTitle, ENT_QUOTES, 'UTF-8') ?></title>
<link rel="stylesheet" href="/assets/css/main.css">
</head>
<body>
  <header class="site-header container">
    <a href="/" style="text-decoration:none;color:inherit;"><strong>event.co.ke</strong></a>
    <nav aria-label="Primary" class="site-nav">
      <a href="/vendors" class="btn btn--secondary">Browse vendors</a>
      <a href="/bundles/new" class="btn btn--primary">Build a bundle</a>
      <?php if (!empty($currentUser)): ?>
        <span class="card__meta">Hi, <?= View::e($currentUser['full_name']) ?></span>
        <form method="post" action="/logout" style="display:inline;">
          <button type="submit" class="btn btn--secondary">Log out</button>
        </form>
      <?php else: ?>
        <a href="/login" class="btn btn--secondary">Log in</a>
        <a href="/signup" class="btn btn--primary">Sign up</a>
      <?php endif; ?>
    </nav>
  </header>

  <main class="container<?= $isCritical ? ' critical-flow' : '' ?>" style="padding-block: var(--ac-space-8);">
    <?= $content ?>
  </main>

  <footer class="site-footer container">
    <p class="card__meta">&copy; <?= date('Y') ?> event.co.ke — part of the artcollect.co.ke network. <a href="/showcase.html">Component showcase</a></p>
  </footer>

  <script type="module" src="/assets/js/main.js"></script>
</body>
</html>
