-- =====================================================================
-- JJ's Firewood Perth – schema catch-up patch
-- Target DB : jjsfirewoodperth-dashboard (restored dump of 2026-09-28)
-- Generated from the project's own migrations (php artisan migrate --pretend)
--
-- What it does:
--   1. Creates the 29 migrations that were never run on this DB
--      (product_upsells, bundles, bundle_extras, blogs, contact_us,
--       weather_campaigns, delivery_follow_ups, faq/tip categories, ...)
--   2. Records them in `migrations` so `php artisan migrate` won't re-run them
--   3. Removes 24 role links that point to users that no longer exist
--
-- It does NOT touch, delete or modify any existing user, order, product
-- or other business data. Only new tables / new nullable-or-defaulted columns.
--
-- TAKE A BACKUP FIRST. Run once. (MySQL DDL auto-commits.)
-- =====================================================================

SET NAMES utf8mb4;

-- 2026_06_08_012500_create_contact_us_table
create table `contact_us` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `email` varchar(255) not null, `subject` varchar(255) not null, `message` text not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

-- 2026_06_08_021416_add_attachment_to_contact_us_table
alter table `contact_us` add `attachment` varchar(255) null after `message`;

-- 2026_06_08_032546_create_blogs_table
create table `blogs` (`id` bigint unsigned not null auto_increment primary key, `title` varchar(255) not null, `short_description` text not null, `long_description` longtext null, `image` varchar(255) null, `status` tinyint not null default '1', `published_at` datetime null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

-- 2026_07_01_000001_create_weather_campaigns_table
create table `weather_campaigns` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `status` varchar(20) not null default 'draft', `channels` json not null, `title` varchar(255) null, `message` text null, `banner_image` varchar(255) null, `banner_cta_text` varchar(255) null, `banner_cta_link` varchar(255) null, `target_zip_group_id` bigint unsigned null, `weather_condition` json not null, `active_days` json null, `active_from` date null, `active_until` date null, `respects_marketing_optout` tinyint(1) not null default '1', `last_evaluated_at` timestamp null, `last_matched_zip_codes` json null, `created_by` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null, `deleted_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `weather_campaigns` add constraint `weather_campaigns_target_zip_group_id_foreign` foreign key (`target_zip_group_id`) references `zip_code_groups` (`id`) on delete set null;
alter table `weather_campaigns` add index `weather_campaigns_status_index`(`status`);

-- 2026_07_01_000002_create_weather_campaign_sends_table
create table `weather_campaign_sends` (`id` bigint unsigned not null auto_increment primary key, `weather_campaign_id` bigint unsigned not null, `user_id` bigint unsigned null, `channel` varchar(20) not null, `trigger_date` date not null, `status` varchar(20) not null, `weather_snapshot` json null, `error` text null, `sent_at` timestamp null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `weather_campaign_sends` add constraint `weather_campaign_sends_weather_campaign_id_foreign` foreign key (`weather_campaign_id`) references `weather_campaigns` (`id`) on delete cascade;
alter table `weather_campaign_sends` add unique `weather_campaign_sends_unique`(`weather_campaign_id`, `user_id`, `channel`, `trigger_date`);
alter table `weather_campaign_sends` add index `weather_campaign_sends_user_id_index`(`user_id`);

-- 2026_07_01_000003_create_delivery_follow_ups_table
create table `delivery_follow_ups` (`id` bigint unsigned not null auto_increment primary key, `order_id` bigint unsigned not null, `user_id` bigint unsigned not null, `status` varchar(20) not null default 'pending', `channels_sent` json null, `action_taken` varchar(20) null, `feedback_rating` tinyint unsigned null, `feedback_comment` text null, `sent_at` timestamp null, `opened_at` timestamp null, `completed_at` timestamp null, `dismissed_at` timestamp null, `error` text null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `delivery_follow_ups` add constraint `delivery_follow_ups_order_id_foreign` foreign key (`order_id`) references `orders` (`id`) on delete cascade;
alter table `delivery_follow_ups` add unique `delivery_follow_ups_order_id_unique`(`order_id`);
alter table `delivery_follow_ups` add constraint `delivery_follow_ups_user_id_foreign` foreign key (`user_id`) references `users` (`id`) on delete cascade;
alter table `delivery_follow_ups` add index `delivery_follow_ups_user_id_index`(`user_id`);
alter table `delivery_follow_ups` add index `delivery_follow_ups_status_index`(`status`);

-- 2026_07_04_000001_create_payment_settings_table
create table `payment_settings` (`id` bigint unsigned not null auto_increment primary key, `square_application_id` varchar(255) null, `square_location_id` varchar(255) null, `square_access_token` text null, `square_environment` varchar(255) not null default 'sandbox', `paypal_client_id` varchar(255) null, `paypal_secret` text null, `paypal_environment` varchar(255) not null default 'sandbox', `webhook_signature_key` text null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

-- 2026_07_15_000001_create_faq_categories_table
create table `faq_categories` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `sort_order` int not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

-- 2026_07_15_000002_add_category_and_sort_order_to_faqs_table
alter table `faqs` add `category_id` bigint unsigned null after `id`;
alter table `faqs` add constraint `faqs_category_id_foreign` foreign key (`category_id`) references `faq_categories` (`id`) on delete set null;
alter table `faqs` add `sort_order` int not null default '0' after `status`;

-- 2026_07_15_000003_create_tip_categories_table
create table `tip_categories` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `sort_order` int not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';

-- 2026_07_15_000004_add_category_id_to_tips_and_guides_table
alter table `tips_and_guides` add `category_id` bigint unsigned null after `id`;
alter table `tips_and_guides` add constraint `tips_and_guides_category_id_foreign` foreign key (`category_id`) references `tip_categories` (`id`) on delete set null;

-- 2026_07_15_000005_add_provider_to_users_table
alter table `users` add `provider` varchar(255) not null default 'email' after `apple_id`;

-- 2026_07_16_000001_add_featured_to_blogs_table
alter table `blogs` add `featured` tinyint(1) not null default '0' after `status`;

-- 2026_07_16_000002_create_wood_education_contents_table
create table `wood_education_contents` (`id` bigint unsigned not null auto_increment primary key, `title` varchar(255) not null, `short_description` text not null, `long_description` longtext null, `image` varchar(255) null, `status` tinyint not null default '1', `published_at` datetime null, `category_id` bigint unsigned null, `sub_category_id` bigint unsigned null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `wood_education_contents` add constraint `wood_education_contents_category_id_foreign` foreign key (`category_id`) references `categories` (`id`) on delete set null;
alter table `wood_education_contents` add constraint `wood_education_contents_sub_category_id_foreign` foreign key (`sub_category_id`) references `sub_categories` (`id`) on delete set null;

-- 2026_07_17_000001_create_bundles_table
create table `bundles` (`id` bigint unsigned not null auto_increment primary key, `name` varchar(255) not null, `description` text null, `image` varchar(255) null, `base_product_id` bigint unsigned not null, `base_price` decimal(10, 2) null, `status` tinyint not null default '1', `position` int not null default '0', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `bundles` add constraint `bundles_base_product_id_foreign` foreign key (`base_product_id`) references `products` (`id`);

-- 2026_07_17_000002_create_bundle_extras_table
create table `bundle_extras` (`id` bigint unsigned not null auto_increment primary key, `bundle_id` bigint unsigned not null, `product_id` bigint unsigned not null, `sort_order` int not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `bundle_extras` add constraint `bundle_extras_bundle_id_foreign` foreign key (`bundle_id`) references `bundles` (`id`) on delete cascade;
alter table `bundle_extras` add constraint `bundle_extras_product_id_foreign` foreign key (`product_id`) references `products` (`id`);

-- 2026_07_17_000003_add_bundle_id_to_carts_table
alter table `carts` add `bundle_id` bigint unsigned null after `giveaway_id`;
alter table `carts` add constraint `carts_bundle_id_foreign` foreign key (`bundle_id`) references `bundles` (`id`) on delete set null;

-- 2026_07_17_000004_add_bundle_id_to_order_items_table
alter table `order_items` add `bundle_id` bigint unsigned null after `giveaway_id`;
alter table `order_items` add constraint `order_items_bundle_id_foreign` foreign key (`bundle_id`) references `bundles` (`id`) on delete set null;

-- 2026_07_17_000005_create_cart_bundle_extras_table
create table `cart_bundle_extras` (`id` bigint unsigned not null auto_increment primary key, `cart_id` bigint unsigned not null, `product_id` bigint unsigned not null, `quantity` decimal(8, 2) not null, `price` decimal(10, 2) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `cart_bundle_extras` add constraint `cart_bundle_extras_cart_id_foreign` foreign key (`cart_id`) references `carts` (`id`) on delete cascade;
alter table `cart_bundle_extras` add constraint `cart_bundle_extras_product_id_foreign` foreign key (`product_id`) references `products` (`id`);

-- 2026_07_17_000006_create_order_item_bundle_extras_table
create table `order_item_bundle_extras` (`id` bigint unsigned not null auto_increment primary key, `order_item_id` bigint unsigned not null, `product_id` bigint unsigned not null, `product_name` varchar(255) not null, `quantity` decimal(8, 2) not null, `price` decimal(10, 2) not null, `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `order_item_bundle_extras` add constraint `order_item_bundle_extras_order_item_id_foreign` foreign key (`order_item_id`) references `order_items` (`id`) on delete cascade;
alter table `order_item_bundle_extras` add constraint `order_item_bundle_extras_product_id_foreign` foreign key (`product_id`) references `products` (`id`);

-- 2026_07_17_000007_create_product_upsells_table
create table `product_upsells` (`id` bigint unsigned not null auto_increment primary key, `product_id` bigint unsigned not null, `upsell_product_id` bigint unsigned not null, `type` enum('extra', 'upgrade') not null, `sort_order` int not null default '0', `status` tinyint not null default '1', `created_at` timestamp null, `updated_at` timestamp null) default character set utf8mb4 collate 'utf8mb4_unicode_ci';
alter table `product_upsells` add constraint `product_upsells_product_id_foreign` foreign key (`product_id`) references `products` (`id`) on delete cascade;
alter table `product_upsells` add constraint `product_upsells_upsell_product_id_foreign` foreign key (`upsell_product_id`) references `products` (`id`) on delete cascade;

-- 2026_07_17_000008_add_discount_percentage_to_bundles_table
alter table `bundles` add `discount_percentage` decimal(5, 2) not null default '0' after `base_price`;

-- 2026_07_17_000009_add_is_required_to_bundle_extras_table
alter table `bundle_extras` add `is_required` tinyint(1) not null default '0' after `product_id`;

-- 2026_07_18_000001_add_product_id_to_push_notifications_table
alter table `push_notifications` add `product_id` bigint unsigned null after `category`;
alter table `push_notifications` add constraint `push_notifications_product_id_foreign` foreign key (`product_id`) references `products` (`id`) on delete set null;

-- 2026_08_17_032738_add_is_bundles_enabled_to_system_settings
alter table `system_settings` add `is_bundles_enabled` tinyint(1) not null default '1' after `id`;

-- 2026_08_17_041711_add_advanced_targeting_to_weather_campaigns_table
alter table `weather_campaigns` add `campaign_type` varchar(50) not null default 'automated' after `status`;
alter table `weather_campaigns` add `requires_approval` tinyint(1) not null default '0' after `campaign_type`;
alter table `weather_campaigns` add `target_segments` json null after `target_zip_group_id`;
alter table `weather_campaigns` add `exclusions` json null after `target_segments`;
alter table `weather_campaigns` add `capacity_thresholds` json null after `exclusions`;
alter table `weather_campaigns` add `channel_content` json null after `capacity_thresholds`;
alter table `weather_campaigns` add `lead_time_hours` int not null default '24' after `weather_condition`;
alter table `weather_campaigns` add `sending_time_start` time null after `active_until`;
alter table `weather_campaigns` add `sending_time_end` time null after `sending_time_start`;

-- 2026_08_29_060844_change_category_id_to_enum_in_tips_and_faqs_tables
alter table `tips_and_guides` drop foreign key `tips_and_guides_category_id_foreign`;
alter table `tips_and_guides` drop `category_id`;
alter table `tips_and_guides` add `category` varchar(255) null after `id`;
alter table `faqs` drop foreign key `faqs_category_id_foreign`;
alter table `faqs` drop `category_id`;
alter table `faqs` add `category` varchar(255) null after `id`;

-- 2026_08_30_054549_add_rewards_synced_to_wc_orders_table
alter table `wc_orders` add `rewards_synced` tinyint(1) not null default '0' after `wc_date_modified`;

-- 2026_08_30_054549_add_woocommerce_customer_id_to_users_table
alter table `users` add `woocommerce_customer_id` bigint unsigned null after `id`;

-- Record migrations as run (batch 64)
INSERT INTO `migrations` (`migration`, `batch`) VALUES
('2026_06_08_012500_create_contact_us_table', 64),
('2026_06_08_021416_add_attachment_to_contact_us_table', 64),
('2026_06_08_032546_create_blogs_table', 64),
('2026_07_01_000001_create_weather_campaigns_table', 64),
('2026_07_01_000002_create_weather_campaign_sends_table', 64),
('2026_07_01_000003_create_delivery_follow_ups_table', 64),
('2026_07_04_000001_create_payment_settings_table', 64),
('2026_07_15_000001_create_faq_categories_table', 64),
('2026_07_15_000002_add_category_and_sort_order_to_faqs_table', 64),
('2026_07_15_000003_create_tip_categories_table', 64),
('2026_07_15_000004_add_category_id_to_tips_and_guides_table', 64),
('2026_07_15_000005_add_provider_to_users_table', 64),
('2026_07_16_000001_add_featured_to_blogs_table', 64),
('2026_07_16_000002_create_wood_education_contents_table', 64),
('2026_07_17_000001_create_bundles_table', 64),
('2026_07_17_000002_create_bundle_extras_table', 64),
('2026_07_17_000003_add_bundle_id_to_carts_table', 64),
('2026_07_17_000004_add_bundle_id_to_order_items_table', 64),
('2026_07_17_000005_create_cart_bundle_extras_table', 64),
('2026_07_17_000006_create_order_item_bundle_extras_table', 64),
('2026_07_17_000007_create_product_upsells_table', 64),
('2026_07_17_000008_add_discount_percentage_to_bundles_table', 64),
('2026_07_17_000009_add_is_required_to_bundle_extras_table', 64),
('2026_07_18_000001_add_product_id_to_push_notifications_table', 64),
('2026_08_17_032738_add_is_bundles_enabled_to_system_settings', 64),
('2026_08_17_041711_add_advanced_targeting_to_weather_campaigns_table', 64),
('2026_08_29_060844_change_category_id_to_enum_in_tips_and_faqs_tables', 64),
('2026_08_30_054549_add_rewards_synced_to_wc_orders_table', 64),
('2026_08_30_054549_add_woocommerce_customer_id_to_users_table', 64);

-- Cleanup: orphaned role assignments (users already deleted)
DELETE mhr FROM `model_has_roles` mhr
LEFT JOIN `users` u ON u.id = mhr.model_id
WHERE mhr.model_type = 'App\\Models\\User' AND u.id IS NULL;
