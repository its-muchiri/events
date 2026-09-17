-- event.co.ke — PostgreSQL schema (Neon, used on Vercel).
-- Mirrors schema.sql (MySQL/MariaDB, used for local development) exactly in
-- shape; only dialect differs. See rider-co-ke's schema.postgres.sql header
-- for the full list of translation rules, and
-- planning/00-portfolio/ui-implementation-plan.md for why this file exists.

-- ============================================================
-- SHARED CORE TABLES — identical shape across all five platforms
-- ============================================================

CREATE TABLE users (
    id BIGSERIAL PRIMARY KEY,
    phone_number VARCHAR(20) NOT NULL UNIQUE,
    email VARCHAR(255) NULL,
    password_hash VARCHAR(255) NOT NULL,
    full_name VARCHAR(255) NOT NULL,
    national_id_number VARCHAR(20) NULL,
    account_type VARCHAR(20) NOT NULL CHECK (account_type IN ('customer', 'provider', 'admin')),
    status VARCHAR(30) NOT NULL DEFAULT 'pending_verification' CHECK (status IN ('active', 'suspended', 'banned', 'pending_verification')),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE roles (
    id BIGSERIAL PRIMARY KEY,
    name VARCHAR(100) NOT NULL UNIQUE
);

CREATE TABLE permissions (
    id BIGSERIAL PRIMARY KEY,
    "key" VARCHAR(150) NOT NULL UNIQUE
);

CREATE TABLE role_permissions (
    role_id BIGINT NOT NULL REFERENCES roles(id),
    permission_id BIGINT NOT NULL REFERENCES permissions(id),
    PRIMARY KEY (role_id, permission_id)
);

CREATE TABLE user_roles (
    user_id BIGINT NOT NULL REFERENCES users(id),
    role_id BIGINT NOT NULL REFERENCES roles(id),
    PRIMARY KEY (user_id, role_id)
);

CREATE TABLE payments (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id),
    booking_id BIGINT NULL,
    order_id BIGINT NULL,
    type VARCHAR(20) NOT NULL CHECK (type IN ('charge', 'payout', 'refund', 'commission')),
    method VARCHAR(20) NOT NULL CHECK (method IN ('mpesa_stk', 'mpesa_c2b', 'mpesa_b2c', 'card')),
    amount DECIMAL(12,2) NOT NULL,
    currency CHAR(3) NOT NULL DEFAULT 'KES',
    external_reference VARCHAR(100) NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'completed', 'failed', 'reversed')),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);
CREATE INDEX idx_payments_external_reference ON payments(external_reference);

CREATE TABLE payment_callbacks_log (
    id BIGSERIAL PRIMARY KEY,
    checkout_request_id VARCHAR(100) NOT NULL UNIQUE,
    raw_payload JSON NOT NULL,
    processed_at TIMESTAMP NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE escrow_transactions (
    id BIGSERIAL PRIMARY KEY,
    payment_id BIGINT NOT NULL REFERENCES payments(id),
    booking_id BIGINT NOT NULL,
    held_amount DECIMAL(12,2) NOT NULL,
    retention_percentage DECIMAL(5,2) NOT NULL DEFAULT 0,
    release_condition VARCHAR(30) NOT NULL CHECK (release_condition IN ('auto_timeout', 'customer_confirmation', 'admin_release', 'dispute_resolution')),
    release_at TIMESTAMP NULL,
    released_at TIMESTAMP NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'held' CHECK (status IN ('held', 'released', 'partially_released', 'refunded'))
);

CREATE TABLE commission_rules (
    id BIGSERIAL PRIMARY KEY,
    platform VARCHAR(20) NOT NULL DEFAULT 'event' CHECK (platform IN ('laundry', 'rider', 'construction', 'solar', 'event')),
    category VARCHAR(100) NOT NULL,
    commission_type VARCHAR(20) NOT NULL CHECK (commission_type IN ('percentage', 'flat_fee', 'tiered')),
    value DECIMAL(10,2) NOT NULL,
    min_transaction_value DECIMAL(12,2) NULL,
    max_transaction_value DECIMAL(12,2) NULL,
    effective_from TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    effective_to TIMESTAMP NULL
);

CREATE TABLE kyc_documents (
    id BIGSERIAL PRIMARY KEY,
    user_id BIGINT NOT NULL REFERENCES users(id),
    document_type VARCHAR(30) NOT NULL CHECK (document_type IN ('national_id', 'kra_pin', 'business_registration', 'insurance_certificate', 'professional_certification', 'proof_of_address')),
    file_reference VARCHAR(500) NOT NULL,
    verification_status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (verification_status IN ('pending', 'verified', 'rejected', 'expired')),
    verified_by BIGINT NULL REFERENCES users(id),
    verified_at TIMESTAMP NULL,
    expires_at TIMESTAMP NULL
);

CREATE TABLE reviews (
    id BIGSERIAL PRIMARY KEY,
    booking_id BIGINT NOT NULL,
    reviewer_id BIGINT NOT NULL REFERENCES users(id),
    reviewee_id BIGINT NOT NULL REFERENCES users(id),
    rating SMALLINT NOT NULL,
    comment TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- category values for this platform: vendor_cancellation, vendor_no_show, service_quality, price_disagreement, other
CREATE TABLE disputes (
    id BIGSERIAL PRIMARY KEY,
    booking_id BIGINT NOT NULL,
    raised_by BIGINT NOT NULL REFERENCES users(id),
    category VARCHAR(100) NOT NULL,
    description TEXT NOT NULL,
    evidence_urls JSON NULL,
    status VARCHAR(30) NOT NULL DEFAULT 'open' CHECK (status IN ('open', 'under_review', 'resolved_refund', 'resolved_partial', 'resolved_no_action', 'escalated')),
    resolved_by BIGINT NULL REFERENCES users(id),
    resolution_notes TEXT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    resolved_at TIMESTAMP NULL
);

CREATE TABLE audit_log (
    id BIGSERIAL PRIMARY KEY,
    actor_id BIGINT NULL REFERENCES users(id),
    action VARCHAR(100) NOT NULL,
    entity_type VARCHAR(50) NOT NULL,
    entity_id BIGINT NOT NULL,
    before_state JSON NULL,
    after_state JSON NULL,
    ip_address VARCHAR(45) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

-- ============================================================
-- PLATFORM-SPECIFIC TABLES — event.co.ke
-- ============================================================

CREATE TABLE event_bundle (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL REFERENCES users(id),
    event_type VARCHAR(20) NOT NULL CHECK (event_type IN ('wedding', 'corporate', 'birthday', 'memorial', 'other')),
    event_date DATE NOT NULL,
    event_location_address VARCHAR(500) NOT NULL,
    event_location_lat DECIMAL(10,7) NOT NULL,
    event_location_lng DECIMAL(10,7) NOT NULL,
    total_bundle_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    combined_deposit_amount DECIMAL(14,2) NOT NULL DEFAULT 0,
    status VARCHAR(20) NOT NULL DEFAULT 'assembling' CHECK (status IN ('assembling', 'confirmed', 'at_risk', 'completed', 'cancelled')),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE event_bookings (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL REFERENCES users(id),
    vendor_id BIGINT NOT NULL REFERENCES users(id),
    bundle_id BIGINT NULL REFERENCES event_bundle(id),
    category VARCHAR(100) NOT NULL,
    event_date DATE NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'requested' CHECK (status IN ('requested', 'confirmed', 'at_risk', 'replaced', 'fulfilled', 'completed', 'cancelled', 'disputed')),
    pricing_model VARCHAR(20) NOT NULL CHECK (pricing_model IN ('flat_fee', 'per_head', 'per_hour')),
    head_count INT NULL,
    hours_booked DECIMAL(5,2) NULL,
    total_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    deposit_amount DECIMAL(12,2) NOT NULL DEFAULT 0,
    payment_id BIGINT NULL REFERENCES payments(id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE bundle_cancellation_incidents (
    id BIGSERIAL PRIMARY KEY,
    bundle_id BIGINT NOT NULL REFERENCES event_bundle(id),
    original_booking_id BIGINT NOT NULL REFERENCES event_bookings(id),
    cancelling_vendor_id BIGINT NOT NULL REFERENCES users(id),
    reported_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    days_until_event INT NOT NULL,
    replacement_booking_id BIGINT NULL REFERENCES event_bookings(id),
    replacement_price_differential DECIMAL(10,2) NOT NULL DEFAULT 0,
    resolution_status VARCHAR(30) NOT NULL DEFAULT 'seeking_replacement' CHECK (resolution_status IN ('seeking_replacement', 'replacement_confirmed', 'unresolved_refunded')),
    coordinator_id BIGINT NULL REFERENCES users(id),
    dispute_id BIGINT NULL REFERENCES disputes(id),
    resolved_at TIMESTAMP NULL
);

CREATE TABLE vendor_listings (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES users(id),
    category VARCHAR(100) NOT NULL,
    business_name VARCHAR(255) NOT NULL,
    portfolio_urls JSON NOT NULL,
    pricing_model VARCHAR(20) NOT NULL CHECK (pricing_model IN ('flat_fee', 'per_head', 'per_hour')),
    base_price DECIMAL(12,2) NOT NULL,
    service_area VARCHAR(255) NOT NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive'))
);

CREATE TABLE vendor_availability (
    id BIGSERIAL PRIMARY KEY,
    vendor_id BIGINT NOT NULL REFERENCES users(id),
    date DATE NOT NULL,
    is_booked BOOLEAN NOT NULL DEFAULT FALSE,
    booking_id BIGINT NULL REFERENCES event_bookings(id),
    CONSTRAINT uq_vendor_availability_vendor_date UNIQUE (vendor_id, date)
);

CREATE TABLE vendor_standing (
    vendor_id BIGINT PRIMARY KEY REFERENCES users(id),
    average_rating DECIMAL(3,2) NOT NULL DEFAULT 0,
    completed_events_count INT NOT NULL DEFAULT 0,
    cancellation_count_12mo INT NOT NULL DEFAULT 0,
    standing_status VARCHAR(20) NOT NULL DEFAULT 'good_standing' CHECK (standing_status IN ('good_standing', 'warning', 'suspended')),
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE saved_vendors (
    customer_id BIGINT NOT NULL REFERENCES users(id),
    vendor_id BIGINT NOT NULL REFERENCES users(id),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    PRIMARY KEY (customer_id, vendor_id)
);

CREATE TABLE business_accounts (
    id BIGSERIAL PRIMARY KEY,
    primary_user_id BIGINT NOT NULL REFERENCES users(id),
    organization_name VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

ALTER TABLE disputes ADD CONSTRAINT fk_disputes_booking FOREIGN KEY (booking_id) REFERENCES event_bookings(id);
ALTER TABLE reviews ADD CONSTRAINT fk_reviews_booking FOREIGN KEY (booking_id) REFERENCES event_bookings(id);
ALTER TABLE escrow_transactions ADD CONSTRAINT fk_escrow_booking FOREIGN KEY (booking_id) REFERENCES event_bookings(id);

-- ============================================================
-- E-COMMERCE STORE — shared shape across all five platforms
-- ============================================================

CREATE TABLE store_products (
    id BIGSERIAL PRIMARY KEY,
    seller_id BIGINT NULL REFERENCES users(id),
    category VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    description TEXT NULL,
    price DECIMAL(10,2) NOT NULL,
    stock_quantity INT NOT NULL DEFAULT 0,
    image_urls JSON NULL,
    status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'out_of_stock', 'inactive')),
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE store_orders (
    id BIGSERIAL PRIMARY KEY,
    customer_id BIGINT NOT NULL REFERENCES users(id),
    payment_id BIGINT NULL REFERENCES payments(id),
    status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'paid', 'fulfilled', 'cancelled')),
    total_amount DECIMAL(10,2) NOT NULL,
    delivery_address VARCHAR(500) NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE store_order_items (
    id BIGSERIAL PRIMARY KEY,
    order_id BIGINT NOT NULL REFERENCES store_orders(id),
    product_id BIGINT NOT NULL REFERENCES store_products(id),
    quantity INT NOT NULL,
    unit_price DECIMAL(10,2) NOT NULL
);

ALTER TABLE payments ADD CONSTRAINT fk_payments_store_order FOREIGN KEY (order_id) REFERENCES store_orders(id);
