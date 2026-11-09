

CREATE TABLE `tbl_admin` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


INSERT INTO tbl_admin (`id`, `user_name`, `email`, `password`, `status`, `created_at`, `updated_at`) VALUES ('1', 'admin', 'admin@admin.com', '$2y$10$YyG5x1Pmbtyqviz4MkVg.OBvcRhsCZ8zZOAYoDgpEayq.ZnACU8nO', '1', '2022-04-14 23:28:24', '2025-07-25 13:31:59');


CREATE TABLE `tbl_avatar` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi	',
  `image` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_banner` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `is_home_screen` int(11) NOT NULL DEFAULT 1 COMMENT '	1- home screen, 2- other screen	',
  `type_id` int(11) NOT NULL COMMENT 'FK = Type Table',
  `video_type` int(11) NOT NULL DEFAULT 1 COMMENT '1- Movies, 2- TVShow, 3- Category, 4-Language, 5- Upcoming Content, 6- Channel Content, 7- Kids Content, 8- Shorts',
  `subvideo_type` int(11) NOT NULL DEFAULT 0 COMMENT '1- Movies, 2- TVShow',
  `video_id` int(11) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_bookmark` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `is_kids_profile` int(11) NOT NULL COMMENT '0- No, 1- Yes',
  `video_type` int(11) NOT NULL DEFAULT 1 COMMENT '1- Movies, 2- TVShow, 3- Category, 4-Language, 5- Upcoming Content, 6- Channel Content, 7- Kids Content, 8- Shorts',
  `sub_video_type` int(11) NOT NULL DEFAULT 0 COMMENT '	1- Movies, 2- TVShow',
  `video_id` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_cast` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `image` varchar(255) NOT NULL,
  `type` varchar(255) NOT NULL,
  `personal_info` text NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_category` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `image` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_channel` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `portrait_img` varchar(255) NOT NULL,
  `landscape_img` varchar(255) NOT NULL,
  `is_title` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_comment` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `comment_id` int(11) NOT NULL DEFAULT 0,
  `user_id` int(11) NOT NULL,
  `video_type` int(11) NOT NULL COMMENT '1- Movies, 2- TVShow, 3- Category, 4-Language, 5- Upcoming Content, 6- Channel Content, 7- Kids Content, 8- Shorts',
  `sub_video_type` int(11) NOT NULL DEFAULT 0 COMMENT '1- Movies, 2- TVShow',
  `video_id` int(11) NOT NULL,
  `comment` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `tbl_coupon` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(255) NOT NULL,
  `name` varchar(255) NOT NULL,
  `start_date` date NOT NULL,
  `end_date` date NOT NULL,
  `amount_type` int(11) NOT NULL COMMENT '1- Price, 2- Percentage',
  `price` varchar(255) NOT NULL,
  `is_use_limit` int(11) NOT NULL DEFAULT 0 COMMENT '0- No,1- Yes',
  `use_limit` int(11) NOT NULL,
  `is_use` int(11) NOT NULL COMMENT '0- Multiple Use, 1- Single Use',
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `tbl_device_sync` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_name` varchar(255) NOT NULL,
  `device_id` varchar(255) NOT NULL,
  `device_type` int(11) NOT NULL DEFAULT 0 COMMENT '	1- Android, 2- iOS, 3- Web, 4- TV	',
  `device_token` varchar(255) NOT NULL,
  `kids_mode` int(11) NOT NULL DEFAULT 0 COMMENT '0- Off, 1- On',
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `tbl_device_watching` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `device_id` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `tbl_general_setting` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` text NOT NULL,
  `value` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=107 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('1', 'app_name', 'DTLive', '2022-08-03 18:38:42', '2024-08-23 16:38:31');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('2', 'app_version', '1.8', '2022-08-03 18:38:42', '2025-08-29 10:33:44');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('3', 'app_logo', 'app_logo.png', '2022-08-03 18:38:42', '2025-09-25 09:52:09');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('4', 'app_desripation', 'Divinetechs is an IT company specializing in providing technology solutions and services. The company likely focuses on various aspects of IT, such as software development, web and mobile app development, IT consulting, cloud solutions, and possibly more. Divinetechs aims to leverage technology to drive business growth and efficiency for its clients, offering innovative and customized solutions tailored to meet specific business needs.', '2022-08-03 18:38:42', '2024-08-28 22:02:20');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('5', 'author', 'DivineTechs', '2022-08-03 18:38:42', '2024-03-23 10:00:59');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('6', 'email', 'support@divinetechs.com', '2022-08-03 18:38:42', '2023-07-03 10:57:48');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('7', 'contact', '917984798190', '2022-08-03 18:38:42', '2023-07-03 10:57:48');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('8', 'website', 'https://www.divinetechs.com/', '2022-08-03 18:38:42', '2023-07-03 10:57:48');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('9', 'currency', 'USD', '2022-08-03 18:38:42', '2025-09-17 15:53:43');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('10', 'currency_code', '$', '2022-08-03 18:38:42', '2025-09-17 15:53:43');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('11', 'admob_status', '0', '2022-08-03 18:38:42', '2025-08-29 09:52:43');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('12', 'banner_ad', '0', '2022-08-03 18:38:42', '2025-09-25 09:48:05');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('13', 'banner_adid', '', '2022-08-03 18:38:42', '2025-08-29 09:53:54');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('14', 'interstital_ad', '0', '2022-08-03 18:38:42', '2025-08-29 09:53:54');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('15', 'interstital_adid', '', '2022-08-03 18:38:42', '2025-08-29 09:53:54');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('16', 'interstital_adclick', '', '2022-08-03 18:38:42', '2025-08-29 09:53:54');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('17', 'reward_ad', '0', '2022-08-03 18:38:42', '2025-08-29 09:53:54');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('18', 'reward_adid', '', '2022-08-03 18:38:42', '2025-08-29 09:53:54');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('19', 'reward_adclick', '', '2022-08-03 18:38:42', '2025-08-29 09:53:54');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('20', 'ios_banner_ad', '0', '2022-08-03 18:38:42', '2025-08-29 09:54:03');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('21', 'ios_banner_adid', '', '2022-08-03 18:38:42', '2025-08-29 09:54:03');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('22', 'ios_interstital_ad', '0', '2022-08-03 18:38:42', '2025-08-29 09:54:03');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('23', 'ios_interstital_adid', '', '2022-08-03 18:38:42', '2025-08-29 09:54:03');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('24', 'ios_interstital_adclick', '', '2022-08-03 18:38:42', '2025-08-29 09:54:03');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('25', 'ios_reward_ad', '0', '2022-08-03 18:38:42', '2025-08-29 09:54:03');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('26', 'ios_reward_adid', '', '2022-08-03 18:38:42', '2025-08-29 09:54:03');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('27', 'ios_reward_adclick', '', '2022-08-03 18:38:42', '2025-08-29 09:54:03');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('28', 'onesignal_app_id', '', '2022-08-03 18:38:42', '2025-09-25 09:47:23');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('29', 'onesignal_rest_key', '', '2022-08-03 18:38:42', '2025-09-25 09:47:24');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('30', 'tmdb_status', '0', '2023-05-30 16:39:32', '2025-09-04 10:45:20');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('31', 'tmdb_api_key', '', '2024-03-23 10:03:17', '2025-09-25 09:47:26');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('32', 'auto_play_trailer', '0', '2024-03-23 10:03:17', '2024-09-27 19:42:50');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('33', 'parent_control_status', '1', '2024-03-23 10:03:54', '2024-05-06 23:26:09');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('34', 'multiple_device_sync', '0', '2024-03-23 10:03:54', '2024-06-15 14:34:39');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('35', 'no_of_device_sync', '0', '2024-03-23 10:04:05', '2024-06-15 14:34:39');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('36', 'subscription_status', '1', '2024-04-18 10:00:25', '2024-04-18 15:33:01');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('37', 'active_tv_status', '1', '2024-05-06 16:26:43', '2024-05-06 16:26:43');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('38', 'watchlist_status', '1', '2024-05-06 16:26:43', '2024-05-06 16:26:43');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('39', 'download_status', '1', '2024-05-06 16:27:00', '2024-10-11 22:18:50');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('40', 'continue_watching_status', '1', '2024-05-06 16:27:00', '2024-05-06 16:27:00');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('41', 'coupon_status', '1', '2024-05-06 16:27:16', '2024-05-06 16:27:16');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('42', 'rent_status', '1', '2024-05-06 16:27:16', '2024-10-11 22:23:48');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('43', 'on_boarding_screen_status', '1', '2024-05-06 18:13:15', '2024-06-24 16:39:51');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('44', 'vapid_key', '', '2024-08-12 09:44:05', '2025-09-25 09:47:19');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('45', 'page_background_color', '#000000', '2024-08-29 13:11:05', '2025-09-17 16:31:04');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('46', 'page_title_color', '#99e34f', '2024-08-29 13:11:05', '2025-09-17 16:31:27');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('47', 'app_login', '1', '2025-02-21 16:01:16', '2025-02-21 16:23:41');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('48', 'app_logo_storage_type', '1', '2025-02-21 16:14:03', '2025-09-02 12:16:29');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('49', 'panel_login_page_img', '', '2025-03-07 11:57:21', '2025-09-25 09:47:08');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('50', 'panel_profile_no_img', '', '2025-03-07 11:57:21', '2025-09-25 09:47:09');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('51', 'panel_normal_no_img', '', '2025-03-07 11:57:21', '2025-09-25 09:47:10');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('52', 'panel_portrait_no_img', '', '2025-03-07 11:57:21', '2025-09-25 09:47:11');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('53', 'panel_landscape_no_img', '', '2025-03-07 11:57:21', '2025-09-25 09:47:12');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('54', 'commission', '10', '2025-03-07 13:45:36', '2025-09-25 09:48:42');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('55', 'powered_by_image', '', '2025-04-24 16:30:53', '2025-09-25 09:47:16');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('56', 'screen_recording_status', '0', '2025-08-29 10:57:07', '2025-09-25 09:50:21');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('57', 'video_player_ima_ads_status', '0', '2025-08-29 11:17:35', '2025-09-22 11:05:30');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('58', 'min_withdrawal_amount', '100', '2025-09-04 17:19:09', '2025-09-25 09:48:40');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('59', 'vdocipher_status', '0', '2025-09-05 10:24:23', '2025-09-05 13:38:04');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('60', 'vdocipher_api_secret_key', '', '2025-09-05 10:24:23', '2025-09-05 13:40:14');

INSERT INTO tbl_general_setting (`id`, `key`, `value`, `created_at`, `updated_at`) VALUES ('61', 'web_client_id', '', '2025-09-05 10:24:23', '2025-09-05 13:40:14');


CREATE TABLE `tbl_home_section` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `section_type` int(11) NOT NULL COMMENT '1- Manually ,0- Dynamic',
  `is_home_screen` int(11) NOT NULL COMMENT '1- Home Screen, 2- Other Screen	',
  `video_type` int(11) NOT NULL COMMENT '1- Movies, 2- TVShow, 3- Category, 4-Language, 5- Upcoming Content, 6- Channel Content, 7-  Kids Content, 8- Shorts, 101- Continue Watching, 102- Channel List, 103- Rent content',
  `sub_video_type` int(11) NOT NULL DEFAULT 0 COMMENT '1- Movies, 2- TVShow',
  `type_id` int(11) NOT NULL COMMENT 'FK = Type Table',
  `title` varchar(255) NOT NULL,
  `short_title` varchar(255) NOT NULL,
  `screen_layout` varchar(255) NOT NULL,
  `content_ids` text NOT NULL,
  `category_id` int(11) NOT NULL,
  `language_id` int(11) NOT NULL,
  `channel_id` int(11) NOT NULL DEFAULT 0,
  `order_by_upload` int(11) NOT NULL DEFAULT 0 COMMENT '1- ASC, 2- DESC',
  `order_by_view` int(11) NOT NULL DEFAULT 0 COMMENT '1- ASC, 2- DESC',
  `premium_video` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `no_of_content` int(11) NOT NULL DEFAULT 0 COMMENT '0- All',
  `view_all` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_title` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_language` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `image` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_like` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `video_type` int(11) NOT NULL COMMENT '1- Movies, 2- TVShow, 3- Category, 4-Language, 5- Upcoming Content, 6- Channel Content, 7- Kids Content, 8- Shorts',
  `sub_video_type` int(11) NOT NULL DEFAULT 0 COMMENT '1- Movies, 2- TVShow',
  `video_id` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `tbl_notification` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `message` text NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `image` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `tbl_notification_configuration` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type` varchar(255) NOT NULL,
  `send_notification` int(11) NOT NULL,
  `send_mail` int(11) NOT NULL,
  `status` int(11) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=16 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO tbl_notification_configuration (`id`, `type`, `send_notification`, `send_mail`, `status`, `created_at`, `updated_at`) VALUES ('1', 'add_movies', '0', '0', '0', '2025-02-11 15:00:32', '2025-09-25 09:46:21');

INSERT INTO tbl_notification_configuration (`id`, `type`, `send_notification`, `send_mail`, `status`, `created_at`, `updated_at`) VALUES ('2', 'add_tvshow', '0', '0', '0', '2025-02-11 15:00:32', '2025-09-25 09:46:21');

INSERT INTO tbl_notification_configuration (`id`, `type`, `send_notification`, `send_mail`, `status`, `created_at`, `updated_at`) VALUES ('3', 'add_upcoming_content', '0', '0', '0', '2025-02-11 15:00:58', '2025-09-25 09:46:21');

INSERT INTO tbl_notification_configuration (`id`, `type`, `send_notification`, `send_mail`, `status`, `created_at`, `updated_at`) VALUES ('4', 'add_channel_content', '0', '0', '0', '2025-02-11 15:01:07', '2025-09-25 09:46:21');

INSERT INTO tbl_notification_configuration (`id`, `type`, `send_notification`, `send_mail`, `status`, `created_at`, `updated_at`) VALUES ('5', 'add_kids_content', '0', '0', '0', '2025-02-11 15:01:17', '2025-09-25 09:46:21');

INSERT INTO tbl_notification_configuration (`id`, `type`, `send_notification`, `send_mail`, `status`, `created_at`, `updated_at`) VALUES ('6', 'login', '0', '0', '0', '2025-02-11 15:01:29', '2025-09-25 09:46:21');

INSERT INTO tbl_notification_configuration (`id`, `type`, `send_notification`, `send_mail`, `status`, `created_at`, `updated_at`) VALUES ('7', 'package_buy', '0', '0', '0', '2025-02-11 15:01:51', '2025-09-25 09:46:21');

INSERT INTO tbl_notification_configuration (`id`, `type`, `send_notification`, `send_mail`, `status`, `created_at`, `updated_at`) VALUES ('8', 'rent_buy', '0', '0', '0', '2025-02-11 15:02:27', '2025-09-25 09:46:21');

INSERT INTO tbl_notification_configuration (`id`, `type`, `send_notification`, `send_mail`, `status`, `created_at`, `updated_at`) VALUES ('9', 'shorts', '0', '0', '0', '2025-09-23 17:37:54', '2025-09-25 09:46:21');


CREATE TABLE `tbl_onboarding_screen` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `image` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `tbl_package` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `package_type` int(11) NOT NULL COMMENT '1- Paid, 2- Free',
  `name` varchar(255) NOT NULL,
  `price` double(11,2) NOT NULL,
  `type` varchar(255) NOT NULL,
  `time` varchar(255) NOT NULL,
  `watch_on_laptop_tv` int(11) NOT NULL,
  `ads_free_content` int(11) NOT NULL,
  `no_of_device_sync` int(11) NOT NULL,
  `android_product_package` varchar(255) NOT NULL,
  `ios_product_package` varchar(255) NOT NULL,
  `web_product_package` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_package_detail` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `package_id` int(11) NOT NULL,
  `package_key` text NOT NULL,
  `package_value` text NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_page` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `title` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `page_subtitle` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `icon` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO tbl_page (`id`, `title`, `description`, `page_subtitle`, `storage_type`, `icon`, `status`, `created_at`, `updated_at`) VALUES ('1', 'About Us', '', 'Know more about us', '1', '', '1', '2022-09-26 10:31:44', '2025-09-25 09:45:50');

INSERT INTO tbl_page (`id`, `title`, `description`, `page_subtitle`, `storage_type`, `icon`, `status`, `created_at`, `updated_at`) VALUES ('2', 'Privacy Policy', '', 'Read our privacy policy', '1', '', '1', '2022-09-26 10:31:44', '2025-09-25 09:45:52');

INSERT INTO tbl_page (`id`, `title`, `description`, `page_subtitle`, `storage_type`, `icon`, `status`, `created_at`, `updated_at`) VALUES ('3', 'Terms and Conditions', '', 'Know about terms & conditions', '1', '', '1', '2022-09-26 10:31:44', '2025-09-25 09:45:53');

INSERT INTO tbl_page (`id`, `title`, `description`, `page_subtitle`, `storage_type`, `icon`, `status`, `created_at`, `updated_at`) VALUES ('4', 'Refund Policy', '', 'Read our refund policy', '1', '', '1', '2023-01-21 16:21:24', '2025-09-25 09:45:53');

INSERT INTO tbl_page (`id`, `title`, `description`, `page_subtitle`, `storage_type`, `icon`, `status`, `created_at`, `updated_at`) VALUES ('5', 'Copy Right Policy', '', 'Copy Right Policy', '1', '', '1', '2025-02-14 10:47:05', '2025-09-25 09:45:54');

INSERT INTO tbl_page (`id`, `title`, `description`, `page_subtitle`, `storage_type`, `icon`, `status`, `created_at`, `updated_at`) VALUES ('6', 'Rent Policy', '', 'Rent Policy', '1', '', '0', '2025-02-28 17:58:54', '2025-09-25 09:45:55');


CREATE TABLE `tbl_payment_option` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `visibility` varchar(255) NOT NULL,
  `is_live` varchar(255) NOT NULL,
  `key_1` varchar(255) NOT NULL,
  `key_2` varchar(255) NOT NULL,
  `key_3` varchar(255) NOT NULL,
  `key_4` varchar(255) NOT NULL,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


INSERT INTO tbl_payment_option (`id`, `name`, `visibility`, `is_live`, `key_1`, `key_2`, `key_3`, `key_4`, `created_at`, `updated_at`) VALUES ('1', 'inapppurchage', '0', '0', '', '', '', '', '2022-07-29 12:26:54', '2025-08-28 17:59:01');

INSERT INTO tbl_payment_option (`id`, `name`, `visibility`, `is_live`, `key_1`, `key_2`, `key_3`, `key_4`, `created_at`, `updated_at`) VALUES ('2', 'paypal', '0', '0', '', '', '', '', '2022-07-29 12:26:54', '2025-09-25 09:45:24');

INSERT INTO tbl_payment_option (`id`, `name`, `visibility`, `is_live`, `key_1`, `key_2`, `key_3`, `key_4`, `created_at`, `updated_at`) VALUES ('3', 'razorpay', '0', '0', '', '', '', '', '2022-07-29 12:27:09', '2025-09-25 09:45:22');

INSERT INTO tbl_payment_option (`id`, `name`, `visibility`, `is_live`, `key_1`, `key_2`, `key_3`, `key_4`, `created_at`, `updated_at`) VALUES ('4', 'flutterwave', '0', '0', '', '', '', '', '2022-07-29 12:27:09', '2025-09-25 09:45:21');

INSERT INTO tbl_payment_option (`id`, `name`, `visibility`, `is_live`, `key_1`, `key_2`, `key_3`, `key_4`, `created_at`, `updated_at`) VALUES ('5', 'payumoney', '0', '0', '', '', '', '', '2022-07-29 12:27:17', '2025-09-25 09:45:26');

INSERT INTO tbl_payment_option (`id`, `name`, `visibility`, `is_live`, `key_1`, `key_2`, `key_3`, `key_4`, `created_at`, `updated_at`) VALUES ('6', 'paytm', '0', '0', '', '', '', '', '2022-07-29 12:27:17', '2025-09-25 09:45:20');

INSERT INTO tbl_payment_option (`id`, `name`, `visibility`, `is_live`, `key_1`, `key_2`, `key_3`, `key_4`, `created_at`, `updated_at`) VALUES ('7', 'stripe', '0', '0', '', '', '', '', '2023-05-30 16:40:44', '2025-09-25 09:45:19');

INSERT INTO tbl_payment_option (`id`, `name`, `visibility`, `is_live`, `key_1`, `key_2`, `key_3`, `key_4`, `created_at`, `updated_at`) VALUES ('8', 'cash', '0', '0', '', '', '', '', '2023-07-03 11:38:01', '2025-08-28 17:57:49');

INSERT INTO tbl_payment_option (`id`, `name`, `visibility`, `is_live`, `key_1`, `key_2`, `key_3`, `key_4`, `created_at`, `updated_at`) VALUES ('9', 'paystack', '0', '0', '', '', '', '', '2023-09-11 18:11:19', '2025-09-25 09:45:18');

INSERT INTO tbl_payment_option (`id`, `name`, `visibility`, `is_live`, `key_1`, `key_2`, `key_3`, `key_4`, `created_at`, `updated_at`) VALUES ('10', 'instamojo', '0', '0', '', '', '', '', '2023-09-11 18:11:19', '2025-09-25 09:45:27');


CREATE TABLE `tbl_producer` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `mobile_number` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `image` varchar(255) NOT NULL,
  `wallet` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `tbl_read_notification` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `notification_id` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;



CREATE TABLE `tbl_rent_price_list` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `price` double(11,2) NOT NULL,
  `android_product_package` varchar(255) NOT NULL,
  `ios_product_package` varchar(255) NOT NULL,
  `web_price_id` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `tbl_rent_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(255) NOT NULL DEFAULT '' COMMENT 'FK = Coupon Table',
  `user_id` int(11) NOT NULL,
  `producer_id` int(11) NOT NULL,
  `video_type` int(11) NOT NULL COMMENT '1- Video, 2- Show, 3- Category, 4-Language, 5- Upcoming, 6- Channel, 7- Kids, 8- Shorts',
  `sub_video_type` int(11) NOT NULL DEFAULT 0 COMMENT '1- Video, 2- Show',
  `video_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `producer_earning` int(11) NOT NULL,
  `commission` int(11) NOT NULL,
  `transaction_id` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `expiry_date` varchar(255) NOT NULL,
  `transaction_status` int(11) NOT NULL COMMENT '1- Processing, 2- Success, 3- Failed',
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Expiry, 1- Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_season` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_shorts` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_id` int(11) NOT NULL DEFAULT 0 COMMENT 'FK = Type Table',
  `video_type` int(11) NOT NULL COMMENT '1- Movies, 2- TVShow, 3- Category, 4-Language, 5- Upcoming Content, 6- Channel Content, 7- Kids Content, 8- Shorts',
  `producer_id` int(11) NOT NULL DEFAULT 0,
  `category_id` text NOT NULL,
  `language_id` text NOT NULL,
  `cast_id` text NOT NULL,
  `name` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `thumbnail` varchar(255) NOT NULL,
  `trailer_storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `trailer_type` varchar(255) NOT NULL COMMENT 'server_video, external',
  `trailer_url` text NOT NULL,
  `description` text NOT NULL,
  `is_title` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_comment` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_like` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `total_view` int(11) NOT NULL DEFAULT 0,
  `total_like` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_shorts_episode` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `show_id` int(11) NOT NULL,
  `season_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `thumbnail` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `video_storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `video_upload_type` varchar(255) NOT NULL COMMENT 'server_video, external',
  `video_320` varchar(255) NOT NULL,
  `video_duration` int(11) NOT NULL DEFAULT 0,
  `is_premium` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_title` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `total_view` int(11) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_smtp_setting` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `protocol` varchar(255) NOT NULL,
  `host` varchar(255) NOT NULL,
  `port` varchar(255) NOT NULL,
  `user` varchar(255) NOT NULL,
  `pass` varchar(255) NOT NULL,
  `from_name` varchar(255) NOT NULL,
  `from_email` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- No, 1- Yes',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;


INSERT INTO tbl_smtp_setting (`id`, `protocol`, `host`, `port`, `user`, `pass`, `from_name`, `from_email`, `status`, `created_at`, `updated_at`) VALUES ('1', 'smtp123', 'smtp.gmail.com', '587', 'admin@admin.com', 'admin', 'DTLive-Divinetechs', 'admin@admin.com', '0', '2022-08-03 16:14:04', '2025-09-25 09:43:32');


CREATE TABLE `tbl_social_link` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `image` varchar(255) NOT NULL,
  `url` text NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `tbl_storage_setting` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `key` text NOT NULL,
  `value` text NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=13 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;


INSERT INTO tbl_storage_setting (`id`, `key`, `value`, `status`, `created_at`, `updated_at`) VALUES ('1', 'storage_type', '1', '1', '2025-09-01 17:07:53', '2025-09-24 16:54:39');

INSERT INTO tbl_storage_setting (`id`, `key`, `value`, `status`, `created_at`, `updated_at`) VALUES ('2', 's3_access_key', '', '1', '2025-09-01 17:07:53', '2025-09-25 09:42:38');

INSERT INTO tbl_storage_setting (`id`, `key`, `value`, `status`, `created_at`, `updated_at`) VALUES ('3', 's3_secret_key', '', '1', '2025-09-01 17:07:53', '2025-09-25 09:42:39');

INSERT INTO tbl_storage_setting (`id`, `key`, `value`, `status`, `created_at`, `updated_at`) VALUES ('4', 's3_region', '', '1', '2025-09-01 17:07:53', '2025-09-25 09:42:40');

INSERT INTO tbl_storage_setting (`id`, `key`, `value`, `status`, `created_at`, `updated_at`) VALUES ('5', 's3_bucket_name', '', '1', '2025-09-01 17:07:53', '2025-09-25 09:42:41');

INSERT INTO tbl_storage_setting (`id`, `key`, `value`, `status`, `created_at`, `updated_at`) VALUES ('6', 's3_endpoint', '', '1', '2025-09-01 17:07:53', '2025-09-25 09:42:42');

INSERT INTO tbl_storage_setting (`id`, `key`, `value`, `status`, `created_at`, `updated_at`) VALUES ('7', 'wasabi_access_key', '', '1', '2025-09-01 17:07:53', '2025-09-25 09:42:43');

INSERT INTO tbl_storage_setting (`id`, `key`, `value`, `status`, `created_at`, `updated_at`) VALUES ('8', 'wasabi_secret_key', '', '1', '2025-09-01 17:07:53', '2025-09-25 09:42:44');

INSERT INTO tbl_storage_setting (`id`, `key`, `value`, `status`, `created_at`, `updated_at`) VALUES ('9', 'wasabi_region', '', '1', '2025-09-01 17:07:53', '2025-09-25 09:42:45');

INSERT INTO tbl_storage_setting (`id`, `key`, `value`, `status`, `created_at`, `updated_at`) VALUES ('10', 'wasabi_bucket_name', '', '1', '2025-09-01 17:07:53', '2025-09-25 09:42:45');

INSERT INTO tbl_storage_setting (`id`, `key`, `value`, `status`, `created_at`, `updated_at`) VALUES ('11', 'wasabi_endpoint', '', '1', '2025-09-01 17:07:53', '2025-09-25 09:42:46');


CREATE TABLE `tbl_transaction` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unique_id` varchar(255) NOT NULL COMMENT 'FK = Coupon Table	',
  `user_id` int(11) NOT NULL,
  `package_id` int(11) NOT NULL,
  `transaction_id` varchar(255) NOT NULL,
  `price` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `expiry_date` varchar(255) NOT NULL,
  `transaction_status` int(11) NOT NULL COMMENT '1- Processing, 2- Success, 3- Failed',
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Expiry, 1- Active',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_tv_login` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `unique_code` varchar(255) NOT NULL,
  `user_id` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `tbl_tv_show` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_id` int(11) NOT NULL DEFAULT 0 COMMENT 'FK = Type Table',
  `video_type` int(11) NOT NULL COMMENT '1- Movies, 2- TVShow, 3- Category, 4-Language, 5- Upcoming Content, 6- Channel Content, 7- Kids Content, 8- Shorts',
  `channel_id` int(11) NOT NULL DEFAULT 0,
  `producer_id` int(11) NOT NULL DEFAULT 0,
  `category_id` text NOT NULL,
  `language_id` text NOT NULL,
  `cast_id` text NOT NULL,
  `name` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `thumbnail` varchar(100) NOT NULL,
  `landscape` varchar(100) NOT NULL,
  `trailer_storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `trailer_type` varchar(255) NOT NULL COMMENT 'server_video, external, youtube	',
  `trailer_url` text NOT NULL,
  `description` text NOT NULL,
  `release_date` varchar(255) NOT NULL,
  `is_title` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_comment` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_like` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_rent` int(11) NOT NULL COMMENT '0- No, 1- Yes',
  `price` int(11) NOT NULL DEFAULT 0,
  `rent_day` int(11) NOT NULL DEFAULT 0 COMMENT '1 to 30 Day',
  `total_view` int(11) NOT NULL DEFAULT 0,
  `total_like` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_tv_show_video` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `show_id` int(11) NOT NULL,
  `season_id` int(11) NOT NULL,
  `name` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `thumbnail` varchar(255) NOT NULL,
  `landscape` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `video_storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `video_upload_type` varchar(255) NOT NULL COMMENT 'server_video, external, youtube, vdocipher_id',
  `video_320` varchar(255) NOT NULL,
  `video_480` varchar(255) NOT NULL,
  `video_720` varchar(255) NOT NULL,
  `video_1080` varchar(255) NOT NULL,
  `video_extension` varchar(255) NOT NULL,
  `video_duration` int(11) NOT NULL DEFAULT 0,
  `subtitle_storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `subtitle_type` varchar(255) NOT NULL COMMENT 'server_video, external	',
  `subtitle_lang_1` varchar(255) NOT NULL,
  `subtitle_1` varchar(255) NOT NULL,
  `subtitle_lang_2` varchar(255) NOT NULL,
  `subtitle_2` varchar(255) NOT NULL,
  `subtitle_lang_3` varchar(255) NOT NULL,
  `subtitle_3` varchar(255) NOT NULL,
  `is_premium` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_title` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_download` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `total_view` int(11) NOT NULL DEFAULT 0,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_type` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `name` varchar(255) NOT NULL,
  `type` int(11) NOT NULL COMMENT '1- Movies, 2- TVShow, 3- Category, 4-Language, 5- Upcoming Content, 6- Channel Content, 7- Kids Content, 8- Shorts',
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `icon` varchar(255) NOT NULL,
  `sort_order` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB AUTO_INCREMENT=2 DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;


INSERT INTO tbl_type (`id`, `name`, `type`, `storage_type`, `icon`, `sort_order`, `status`, `created_at`, `updated_at`) VALUES ('1', 'Movies', '1', '1', '', '1', '1', '2025-09-25 09:41:10', '2025-09-25 09:41:46');


CREATE TABLE `tbl_user` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_name` varchar(255) NOT NULL,
  `full_name` varchar(255) NOT NULL,
  `email` varchar(255) NOT NULL,
  `password` varchar(255) NOT NULL,
  `mobile_number` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `image_type` int(11) NOT NULL COMMENT '1- File Upload, 2- Avatar',
  `image` varchar(255) NOT NULL,
  `type` int(11) NOT NULL DEFAULT 0 COMMENT '1- OTP, 2- Google, 3- Apple, 4- Normal	',
  `parent_control_status` int(11) NOT NULL DEFAULT 0,
  `parent_control_password` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `tbl_video` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `type_id` int(11) NOT NULL COMMENT 'FK = Type Table',
  `video_type` int(11) NOT NULL COMMENT '1- Movies, 2- TVShow, 3- Category, 4-Language, 5- Upcoming Content, 6- Channel Content, 7- Kids Content, 8- Shorts',
  `channel_id` int(11) NOT NULL DEFAULT 0,
  `producer_id` int(11) NOT NULL DEFAULT 0,
  `category_id` text NOT NULL,
  `language_id` text NOT NULL,
  `cast_id` text NOT NULL,
  `name` varchar(255) NOT NULL,
  `storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `thumbnail` varchar(255) NOT NULL,
  `landscape` varchar(255) NOT NULL,
  `description` text NOT NULL,
  `video_storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `video_upload_type` varchar(255) NOT NULL COMMENT 'server_video, external, youtube, live_stream_url,vdocipher_id',
  `video_320` varchar(255) NOT NULL,
  `video_480` varchar(255) NOT NULL,
  `video_720` varchar(255) NOT NULL,
  `video_1080` varchar(255) NOT NULL,
  `video_extension` varchar(255) NOT NULL,
  `video_duration` int(11) NOT NULL DEFAULT 0,
  `trailer_storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `trailer_type` varchar(255) NOT NULL COMMENT 'server_video, external, youtube	',
  `trailer_url` varchar(255) NOT NULL,
  `subtitle_storage_type` int(11) NOT NULL COMMENT '1- Local, 2- AWS S3, 3- Wasabi',
  `subtitle_type` varchar(255) NOT NULL COMMENT 'server_video, external	',
  `subtitle_lang_1` varchar(255) NOT NULL,
  `subtitle_1` varchar(255) NOT NULL,
  `subtitle_lang_2` varchar(255) NOT NULL,
  `subtitle_2` varchar(255) NOT NULL,
  `subtitle_lang_3` varchar(255) NOT NULL,
  `subtitle_3` varchar(255) NOT NULL,
  `release_date` varchar(255) NOT NULL,
  `is_premium` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_title` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_download` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_comment` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_like` int(11) NOT NULL DEFAULT 0 COMMENT '0- No, 1- Yes',
  `is_rent` int(11) NOT NULL COMMENT '0- No, 1- Yes',
  `price` int(11) NOT NULL DEFAULT 0,
  `rent_day` int(11) NOT NULL DEFAULT 0 COMMENT '1 to 30 Day',
  `total_view` int(11) NOT NULL DEFAULT 0,
  `total_like` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1 COMMENT '0- Hide, 1- Show',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_video_watch` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `is_kids_profile` int(11) NOT NULL COMMENT '	0- No, 1- Yes',
  `video_type` int(11) NOT NULL COMMENT '1- Movies, 2- TVShow, 3- Category, 4-Language, 5- Upcoming Content, 6- Channel Content, 7- Kids Content, 8- Shorts',
  `sub_video_type` int(11) NOT NULL DEFAULT 0 COMMENT '1- Movies, 2- TVShow',
  `video_id` int(11) NOT NULL,
  `episode_id` int(11) NOT NULL DEFAULT 0,
  `stop_time` varchar(255) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_520_ci;



CREATE TABLE `tbl_view` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `user_id` int(11) NOT NULL,
  `video_type` int(11) NOT NULL COMMENT '1- Movies, 2- TVShow, 3- Category, 4-Language, 5- Upcoming Content, 6- Channel Content, 7- Kids Content, 8- Shorts',
  `sub_video_type` int(11) NOT NULL DEFAULT 0 COMMENT '1- Movies, 2- TVShow, 3- Episode',
  `video_id` int(11) NOT NULL,
  `episode_id` int(11) NOT NULL DEFAULT 0,
  `status` int(11) NOT NULL DEFAULT 1,
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci;



CREATE TABLE `tbl_withdrawal_request` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `producer_id` int(11) NOT NULL,
  `price` int(11) NOT NULL,
  `status` int(11) NOT NULL DEFAULT 0 COMMENT '0- Pending, 1- Completed',
  `created_at` timestamp NOT NULL DEFAULT current_timestamp(),
  `updated_at` timestamp NOT NULL DEFAULT current_timestamp() ON UPDATE current_timestamp(),
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

