<?php

/**
 * Server-rendered page routes (see src/Views/ and src/Core/View.php) —
 * distinct from routes/api.php's JSON API. Covers the primary customer
 * journey (browse -> book/bundle -> track) per
 * planning/05-event-co-ke/prd.md's Core Features 1-2; the coordinator's
 * cancellation-incident console is not built here. Mirrors
 * laundry.co.ke's routes/web.php pattern (see
 * planning/00-portfolio/ui-implementation-plan.md).
 *
 * @var \EventCo\Core\Router $router
 */

use EventCo\Controllers\AuthController;
use EventCo\Controllers\PageController;

$page = new PageController();
$auth = new AuthController();

$router->get('/signup', [$auth, 'showSignup']);
$router->post('/signup', [$auth, 'register']);
$router->get('/login', [$auth, 'showLogin']);
$router->post('/login', [$auth, 'login']);
$router->post('/logout', [$auth, 'logout']);

$router->get('/', [$page, 'home']);
$router->get('/vendors', [$page, 'vendorIndex']);
$router->get('/vendors/{id}', [$page, 'vendorProfile']);
$router->get('/bookings/new', [$page, 'bookingForm']);
$router->get('/bookings/{id}', [$page, 'bookingStatus']);
$router->get('/bundles/new', [$page, 'bundleForm']);
$router->get('/bundles/{id}', [$page, 'bundleStatus']);
