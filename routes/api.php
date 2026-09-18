<?php

/**
 * Route table for event.co.ke. Mirrors planning/05-event-co-ke/api-endpoints.md.
 *
 * @var \EventCo\Core\Router $router
 */

use EventCo\Controllers\AuthController;
use EventCo\Controllers\BookingController;
use EventCo\Controllers\BundleController;
use EventCo\Controllers\DisputeController;
use EventCo\Controllers\PaymentController;
use EventCo\Controllers\ReviewController;
use EventCo\Controllers\StoreController;
use EventCo\Controllers\VendorController;

$auth = new AuthController();
$booking = new BookingController();
$bundle = new BundleController();
$payment = new PaymentController();
$review = new ReviewController();
$dispute = new DisputeController();
$vendor = new VendorController();
$store = new StoreController();

// Auth (shared identity/auth module — see planning/00-portfolio/shared-architecture.md)
$router->post('/api/v1/auth/register', [$auth, 'register']);
$router->post('/api/v1/auth/login', [$auth, 'login']);
$router->post('/api/v1/auth/logout', [$auth, 'logout']);
$router->get('/api/v1/auth/me', [$auth, 'me']);

// Bookings (single-vendor)
$router->post('/api/v1/bookings', [$booking, 'create']);
$router->get('/api/v1/bookings/{id}', [$booking, 'show']);
$router->patch('/api/v1/bookings/{id}/status', [$booking, 'updateStatus']);
$router->post('/api/v1/bookings/{id}/cancel', [$booking, 'cancel']);

// Bundles (multi-vendor)
$router->post('/api/v1/event-bundles', [$bundle, 'create']);
$router->post('/api/v1/event-bundles/{id}/vendors', [$bundle, 'addVendor']);
$router->delete('/api/v1/event-bundles/{id}/vendors/{bookingId}', [$bundle, 'removeVendor']);
$router->post('/api/v1/event-bundles/{id}/confirm', [$bundle, 'confirm']);
$router->get('/api/v1/event-bundles/{id}', [$bundle, 'show']);
$router->post('/api/v1/event-bundles/{id}/cancel', [$bundle, 'cancel']);

// Vendor cancellation & replacement
$router->post('/api/v1/bookings/{id}/vendor-cancel', [$bundle, 'reportVendorCancellation']);
$router->get('/api/v1/event-bundles/{id}/cancellation-incidents', [$bundle, 'listCancellationIncidents']);
$router->post('/api/v1/cancellation-incidents/{id}/replacement-options', [$bundle, 'attachReplacementOptions']);
$router->post('/api/v1/cancellation-incidents/{id}/confirm-replacement', [$bundle, 'confirmReplacement']);
$router->post('/api/v1/cancellation-incidents/{id}/unresolved-refund', [$bundle, 'unresolvedRefund']);

// Availability
$router->get('/api/v1/vendors/{id}/availability', [$booking, 'vendorAvailability']);
$router->post('/api/v1/vendors/me/availability', [$booking, 'updateMyAvailability']);

// Payments
$router->post('/api/v1/payments/mpesa/stk-push', [$payment, 'stkPush']);
$router->post('/api/v1/payments/mpesa/callback', [$payment, 'mpesaCallback']);
$router->post('/api/v1/payments/card', [$payment, 'card']);
$router->get('/api/v1/vendors/me/earnings', [$payment, 'myEarnings']);

// Reviews
$router->post('/api/v1/bookings/{id}/review', [$review, 'store']);
$router->get('/api/v1/vendors/{id}/reviews', [$review, 'forVendor']);

// Disputes
$router->post('/api/v1/bookings/{id}/disputes', [$dispute, 'store']);
$router->get('/api/v1/disputes', [$dispute, 'index']);
$router->patch('/api/v1/disputes/{id}/resolve', [$dispute, 'resolve']);

// Vendor onboarding, directory, favorites, business accounts
$router->post('/api/v1/vendors/onboard', [$vendor, 'onboard']);
$router->get('/api/v1/vendors', [$vendor, 'browse']);
$router->get('/api/v1/vendors/{id}', [$vendor, 'profile']);
$router->post('/api/v1/vendors/{id}/save', [$vendor, 'save']);
$router->post('/api/v1/business-accounts', [$vendor, 'createBusinessAccount']);

// Store
$router->get('/api/v1/store/products', [$store, 'listProducts']);
$router->post('/api/v1/store/orders', [$store, 'createOrder']);
