

php artisan migrate --path=database/migrations/2025_09_09_082750_create_video_watch_histories_table.php
php artisan migrate --path=database/migrations/2025_09_09_082744_create_video_favorites_table.php
php artisan migrate --path=database/migrations/2025_09_09_082735_create_video_likes_table.php


composer require vimeo/laravel
php artisan vendor:publish --provider="Vimeo\Laravel\VimeoServiceProvider"

composer require vimeo/vimeo-api

363adc0fcb265a69fb4faf2f583713de vimeo access token


# VIMEO_CLIENT=54c8afb1d79eb97f80283147f307044bff7136bd
# VIMEO_SECRET=bJ50DrPOOYla/sqmmKZixwUnIT6TUZNxaEcp8y11cNEaTO1ENeoaoYihBBPCAOuDZ2ZrNH4tnPIR2dre5qvYe5L5ccjX/OjuW2lt4VmK7iA56IhPn7ZYvVGzriESFMKp
# VIMEO_ACCESS=363adc0fcb265a69fb4faf2f583713de
VIMEO_CLIENT=72ce266439c44ada4e52b6216ff763e5ce5a564b
VIMEO_SECRET=FFJsqJh+JQludk26mi3LHsRVqStoOa8F5267X+pFUfgwqH27x3CjBlaW9zPBtyOQgS9nsOSJwEnkD17OZmIrj7Q7LCAUN3vui//JVctUZbsLoIguH+8HMDdItF58v2fv
VIMEO_ACCESS=ceeef35ea7833754824f21fdc10f3636



---------------------------------------------------------
11-09-2025
php artisan migrate --path=database/migrations/2025_09_09_115521_add_user_id_and_video_id_to_video_likes_table.php
php artisan migrate --path=database/migrations/2025_09_10_074127_add_watch_to_videos_table.php
php artisan migrate --path=database/migrations/2025_09_10_094242_add_video_watch_histories_table.php
php artisan migrate --path=database/migrations/2025_09_10_100925_create_follow_channels_table.php
php artisan migrate --path=database/migrations/2025_09_10_113350_create_seo_region_table.php

ALTER TABLE `videos` CHANGE `video_url` `video_url` TEXT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci NULL;

Video curls:

1): Video like: 
  
curl --location --request POST 'http://127.0.0.1:8000/api/videos/11/like' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU3NDgzNjk0LCJleHAiOjE3NTc0ODcyOTQsIm5iZiI6MTc1NzQ4MzY5NCwianRpIjoicEZHRDdtZ1E4Q1pzb1lMeiIsInN1YiI6IjE5IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.cEUBwOWjLsRufMzF3Mhb6ILrgWZg3R-v8m8efFGFiQQ'


2): Video unlike:

curl --location --request DELETE 'http://127.0.0.1:8000/api/videos/11/unlike' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU3NDgzNjk0LCJleHAiOjE3NTc0ODcyOTQsIm5iZiI6MTc1NzQ4MzY5NCwianRpIjoicEZHRDdtZ1E4Q1pzb1lMeiIsInN1YiI6IjE5IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.cEUBwOWjLsRufMzF3Mhb6ILrgWZg3R-v8m8efFGFiQQ'

3) Video Likes Count: 

curl --location 'http://127.0.0.1:8000/api/videos-check/11/likes-count' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU3NDgzNjk0LCJleHAiOjE3NTc0ODcyOTQsIm5iZiI6MTc1NzQ4MzY5NCwianRpIjoicEZHRDdtZ1E4Q1pzb1lMeiIsInN1YiI6IjE5IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.cEUBwOWjLsRufMzF3Mhb6ILrgWZg3R-v8m8efFGFiQQ'


4) Video Watch:

curl --location --request POST 'http://127.0.0.1:8000/api/videos/9/watch' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU3NTY2MDU0LCJleHAiOjE3NTc1Njk2NTQsIm5iZiI6MTc1NzU2NjA1NCwianRpIjoiNUFyVmFrNkFZb0QxZ2tWdSIsInN1YiI6IjE5IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.Pdx2Ds3Htpi-vv94rbvxp_2-KFbUf9tBouK_fycTdgU'


5) Channel Follow:

curl --location --request POST 'http://127.0.0.1:8000/api/channels/7/follow' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU3NTAxNzg4LCJleHAiOjE3NTc1MDUzODgsIm5iZiI6MTc1NzUwMTc4OCwianRpIjoianJaWVFUdFpxMjR5a1FNUCIsInN1YiI6IjE5IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.mmBYv4cQJ2TGUTR3n5InPI53OgcEElfwfCCpjnXokAA'

6): Channel List:

curl --location 'http://127.0.0.1:8000/api/follows' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU3NTAxNzg4LCJleHAiOjE3NTc1MDUzODgsIm5iZiI6MTc1NzUwMTc4OCwianRpIjoianJaWVFUdFpxMjR5a1FNUCIsInN1YiI6IjE5IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.mmBYv4cQJ2TGUTR3n5InPI53OgcEElfwfCCpjnXokAA'


---------------------------------------------------------12-09-2025
Pets page
curl --location 'http://127.0.0.1:8000/api/categories-pet/region/AU' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU3Njc4MjY2LCJleHAiOjE3NTc2ODE4NjYsIm5iZiI6MTc1NzY3ODI2NiwianRpIjoiYUR0djhkZ2F0YTJ3VWw2YyIsInN1YiI6IjE0IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.hJk17ReiyoQy4nDgyZnto9cRK3u5O_Pfd1kTp0h_FtU'


People Page
curl --location 'http://127.0.0.1:8000/api/categories-people/region/AU' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU3Njc4MjY2LCJleHAiOjE3NTc2ODE4NjYsIm5iZiI6MTc1NzY3ODI2NiwianRpIjoiYUR0djhkZ2F0YTJ3VWw2YyIsInN1YiI6IjE0IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.hJk17ReiyoQy4nDgyZnto9cRK3u5O_Pfd1kTp0h_FtU'




------------------------------------------------------------------------
16-09-2025
php artisan migrate --path=/database/migrations/2025_09_15_064753_add_colors_to_channels_table.php
php artisan migrate --path=/database/migrations/2025_09_15_064833_create_global_colors_table.php
php artisan migrate --path=/database/migrations/2025_09_16_080701_create_affiliate_links_table.php

-- Insert Global / Main Landing Page (Core Brand) colors
INSERT INTO global_colors (name, hex_value, `usage`, created_at, updated_at) VALUES
('Midnight Black', '#0D0D0D', 'Main Landing Page (Core Brand) - Primary', NOW(), NOW()),
('Vibrant Electric Blue', '#2E86C1', 'Main Landing Page (Core Brand) - Secondary', NOW(), NOW()),
('Neon Green', '#1DB954', 'Main Landing Page (Core Brand) - Accent', NOW(), NOW()),
('White', '#FFFFFF', 'Main Landing Page (Core Brand) - Text', NOW(), NOW()),
('Soft Grey', '#BFC9CA', 'Main Landing Page (Core Brand) - Text', NOW(), NOW());

-- Insert Common UI Areas colors
INSERT INTO global_colors (name, hex_value, `usage`, created_at, updated_at) VALUES
('Blue', '#2E86C1', 'Common UI Areas - Button Default', NOW(), NOW()),
('Blue Hover', '#1A5276', 'Common UI Areas - Button Hover', NOW(), NOW()),
('Dark Charcoal', '#1C1C1C', 'Common UI Areas - Background Panels/Modals', NOW(), NOW()),
('White', '#FFFFFF', 'Common UI Areas - Typography (Headers)', NOW(), NOW()),
('Off-White', '#F8F9F9', 'Common UI Areas - Typography (Body)', NOW(), NOW()),
('Success Green', '#27AE60', 'Common UI Areas - Alert Success', NOW(), NOW()),
('Warning Amber', '#F39C12', 'Common UI Areas - Alert Warning', NOW(), NOW()),
('Error Red', '#E74C3C', 'Common UI Areas - Alert Error', NOW(), NOW());


-- Update colors for the 'Bark Bench' channel (Pets Channel)
UPDATE channels 
SET 
    primary_color = '#E74C3C', -- Warm Red
    secondary_color = '#F4D03F', -- Golden Yellow
    accent_color = '#27AE60', -- Grass Green
    background_color = '#FFF9F2' -- Cream White
WHERE name = 'Bark Bench';

-- Update colors for the 'Night Gaze' channel (NightGainz)
UPDATE channels 
SET 
    primary_color = '#C0392B', -- Crimson
    secondary_color = '#000000', -- Black
    accent_color = '#BDC3C7', -- Metallic Silver
    background_color = '#1B1B1B' -- Deep Charcoal
WHERE name = 'Night Gaze';

-- Update colors for the 'Alien Tests' channel (AlienTests)
UPDATE channels 
SET 
    primary_color = '#8E44AD', -- Cosmic Purple
    secondary_color = '#00FFFF', -- Neon Cyan
    accent_color = '#DFFF00', -- Lime
    background_color = '#12122B' -- Dark Galaxy Blue
WHERE name = 'Alien Tests';


-- Update colors for the 'Whisker Width' channel (missing colors)
UPDATE channels 
SET 
    primary_color = '#6C3483', -- Deep Plum
    secondary_color = '#AAB7B8', -- Silver Grey
    accent_color = '#F1948A', -- Rose Pink
    background_color = '#FAF3F6' -- Soft Ivory
WHERE name = 'Whisker Width';

-- Update colors for the 'Parrot' channel (missing colors)
UPDATE channels 
SET 
    primary_color = '#FF4D94', -- Hot Neon Pink
    secondary_color = '#0A0A0A', -- Jet Black
    accent_color = '#00D2FF', -- Electric Aqua
    background_color = '#FFFFFF' -- White
WHERE name = 'Parrot';

-- Update colors for the 'Demo ava' channel (missing colors)
UPDATE channels 
SET 
    primary_color = '#8E44AD', -- Cosmic Purple
    secondary_color = '#00FFFF', -- Neon Cyan
    accent_color = '#DFFF00', -- Lime
    background_color = '#12122B' -- Dark Galaxy Blue
WHERE name = 'Demo ava';

-- Update colors for the 'Parrot 2' channel (missing colors)
UPDATE channels 
SET 
    primary_color = '#FF4D94', -- Hot Neon Pink
    secondary_color = '#0A0A0A', -- Jet Black
    accent_color = '#00D2FF', -- Electric Aqua
    background_color = '#FFFFFF' -- White
WHERE name = 'Parrot 2';

-- Update colors for the 'Parrot 3' channel (missing colors)
UPDATE channels 
SET 
    primary_color = '#FF4D94', -- Hot Neon Pink
    secondary_color = '#0A0A0A', -- Jet Black
    accent_color = '#00D2FF', -- Electric Aqua
    background_color = '#FFFFFF' -- White
WHERE name = 'Parrot 3';

-- Update colors for the 'Big Foot Tried It' channel (missing colors)
UPDATE channels 
SET 
    primary_color = '#FF4D94', -- Hot Neon Pink
    secondary_color = '#0A0A0A', -- Jet Black
    accent_color = '#00D2FF', -- Electric Aqua
    background_color = '#FFFFFF' -- White
WHERE name = 'Big Foot Tried It';

-- Update colors for the 'Crib Critics' channel (missing colors)
UPDATE channels 
SET 
    primary_color = '#FF4D94', -- Hot Neon Pink
    secondary_color = '#0A0A0A', -- Jet Black
    accent_color = '#00D2FF', -- Electric Aqua
    background_color = '#FFFFFF' -- White
WHERE name = 'Crib Critics';

-- Update colors for the 'Yeti Approved' channel (missing colors)
UPDATE channels 
SET 
    primary_color = '#FF4D94', -- Hot Neon Pink
    secondary_color = '#0A0A0A', -- Jet Black
    accent_color = '#00D2FF', -- Electric Aqua
    background_color = '#FFFFFF' -- White
WHERE name = 'Yeti Approved';

-- Update colors for the 'Lizard Lift' channel (missing colors)
UPDATE channels 
SET 
    primary_color = '#FF4D94', -- Hot Neon Pink
    secondary_color = '#0A0A0A', -- Jet Black
    accent_color = '#00D2FF', -- Electric Aqua
    background_color = '#FFFFFF' -- White
WHERE name = 'Lizard Lift';

-- Update colors for the 'Audrey Hunt' channel (missing colors)
UPDATE channels 
SET 
    primary_color = '#8E44AD', -- Cosmic Purple
    secondary_color = '#00FFFF', -- Neon Cyan
    accent_color = '#DFFF00', -- Lime
    background_color = '#12122B' -- Dark Galaxy Blue
WHERE name = 'Audrey Hunt';

-- Update colors for the 'Pets Channel' (BarkTastic)
UPDATE channels 
SET 
    primary_color = '#E74C3C', -- Warm Red
    secondary_color = '#F4D03F', -- Golden Yellow
    accent_color = '#27AE60', -- Grass Green
    background_color = '#FFF9F2' -- Cream White
WHERE name = 'Pets Channel';

-- Update colors for the 'FitnessVibe Channel GLOBAL' (MoonRated)
UPDATE channels 
SET 
    primary_color = '#BDC3C7', -- Moon Silver
    secondary_color = '#7F8C8D', -- Wolf Grey
    accent_color = '#2C3E50', -- Midnight Blue
    background_color = '#145A32' -- Forest Green
WHERE name = 'FitnessVibe Channel GLOBAL';

-- Update colors for the 'My New Channel Canada' (MoonRated)
UPDATE channels 
SET 
    primary_color = '#BDC3C7', -- Moon Silver
    secondary_color = '#7F8C8D', -- Wolf Grey
    accent_color = '#2C3E50', -- Midnight Blue
    background_color = '#145A32' -- Forest Green
WHERE name = 'My New Channel Canada';

-- Update colors for the 'My New Channel2 US' (MoonRated)
UPDATE channels 
SET 
    primary_color = '#BDC3C7', -- Moon Silver
    secondary_color = '#7F8C8D', -- Wolf Grey
    accent_color = '#2C3E50', -- Midnight Blue
    background_color = '#145A32' -- Forest Green
WHERE name = 'My New Channel2 US';



-- Insert Affiliate Links for Video 1: '10-Minute Morning Yoga for Beginners'

-- For AU region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(1, 1, 'Amazon_AU', 'https://www.amazon.com.au/dp/YOGA-MAT-001?tag=beastierated-22'),
(1, 1, 'ASOS_AU', 'https://www.asos.com/product/yoga-mat'),
(1, 1, 'Sephora_AU', 'https://www.sephora.com.au/product/yoga-mat');

-- For US region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(1, 2, 'Amazon_US', 'https://www.amazon.com/dp/YOGA-MAT-001?tag=beastierated-20'),
(1, 2, 'ASOS_US', 'https://www.asos.com/product/yoga-mat'),
(1, 2, 'Sephora_US', 'https://www.sephora.com/product/yoga-mat');

-- For UK region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(1, 3, 'Amazon_UK', 'https://www.amazon.co.uk/dp/YOGA-MAT-001?tag=beastierated-21'),
(1, 3, 'ASOS_UK', 'https://www.asos.com/product/yoga-mat'),
(1, 3, 'Sephora_UK', 'https://www.sephora.co.uk/product/yoga-mat');

-- For CA region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(1, 4, 'Amazon_CA', 'https://www.amazon.ca/dp/YOGA-MAT-001?tag=beastierated-20'),
(1, 4, 'ASOS_CA', 'https://www.asos.ca/product/yoga-mat'),
(1, 4, 'Sephora_CA', 'https://www.sephora.ca/product/yoga-mat');

-- For GLOBAL region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(1, 5, 'Amazon_GLOBAL', 'https://www.amazon.com/dp/YOGA-MAT-001?tag=beastierated-20'),
(1, 5, 'ASOS_GLOBAL', 'https://www.asos.com/product/yoga-mat'),
(1, 5, 'Sephora_GLOBAL', 'https://www.sephora.com/product/yoga-mat');


-- Insert Affiliate Links for Video 2: 'Officia in dolores i'

-- For AU region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(2, 1, 'Amazon_AU', 'https://www.amazon.com.au/dp/1225454?tag=beastierated-22'),
(2, 1, 'ASOS_AU', 'https://www.asos.com/product/product-name'),
(2, 1, 'Sephora_AU', 'https://www.sephora.com.au/product/product-name');

-- For US region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(2, 2, 'Amazon_US', 'https://www.amazon.com/dp/1225454?tag=beastierated-20'),
(2, 2, 'ASOS_US', 'https://www.asos.com/product/product-name'),
(2, 2, 'Sephora_US', 'https://www.sephora.com/product/product-name');

-- For UK region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(2, 3, 'Amazon_UK', 'https://www.amazon.co.uk/dp/1225454?tag=beastierated-21'),
(2, 3, 'ASOS_UK', 'https://www.asos.com/product/product-name'),
(2, 3, 'Sephora_UK', 'https://www.sephora.co.uk/product/product-name');

-- For CA region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(2, 4, 'Amazon_CA', 'https://www.amazon.ca/dp/1225454?tag=beastierated-20'),
(2, 4, 'ASOS_CA', 'https://www.asos.ca/product/product-name'),
(2, 4, 'Sephora_CA', 'https://www.sephora.ca/product/product-name');

-- For GLOBAL region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(2, 5, 'Amazon_GLOBAL', 'https://www.amazon.com/dp/1225454?tag=beastierated-20'),
(2, 5, 'ASOS_GLOBAL', 'https://www.asos.com/product/product-name'),
(2, 5, 'Sephora_GLOBAL', 'https://www.sephora.com/product/product-name');



-- Insert Affiliate Links for Video 1: '10-Minute Morning Yoga for Beginners'

-- For AU region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(1, 1, 'Amazon_AU', 'https://www.amazon.com.au/dp/YOGA-MAT-001?tag=beastierated-22'),
(1, 1, 'ASOS_AU', 'https://www.asos.com/product/yoga-mat'),
(1, 1, 'Sephora_AU', 'https://www.sephora.com.au/product/yoga-mat');

-- For US region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(1, 2, 'Amazon_US', 'https://www.amazon.com/dp/YOGA-MAT-001?tag=beastierated-20'),
(1, 2, 'ASOS_US', 'https://www.asos.com/product/yoga-mat'),
(1, 2, 'Sephora_US', 'https://www.sephora.com/product/yoga-mat');

-- For UK region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(1, 3, 'Amazon_UK', 'https://www.amazon.co.uk/dp/YOGA-MAT-001?tag=beastierated-21'),
(1, 3, 'ASOS_UK', 'https://www.asos.com/product/yoga-mat'),
(1, 3, 'Sephora_UK', 'https://www.sephora.co.uk/product/yoga-mat');

-- For CA region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(1, 4, 'Amazon_CA', 'https://www.amazon.ca/dp/YOGA-MAT-001?tag=beastierated-20'),
(1, 4, 'ASOS_CA', 'https://www.asos.ca/product/yoga-mat'),
(1, 4, 'Sephora_CA', 'https://www.sephora.ca/product/yoga-mat');

-- For GLOBAL region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(1, 5, 'Amazon_GLOBAL', 'https://www.amazon.com/dp/YOGA-MAT-001?tag=beastierated-20'),
(1, 5, 'ASOS_GLOBAL', 'https://www.asos.com/product/yoga-mat'),
(1, 5, 'Sephora_GLOBAL', 'https://www.sephora.com/product/yoga-mat');


-- Insert Affiliate Links for Video 2: 'Officia in dolores i'

-- For AU region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(2, 1, 'Amazon_AU', 'https://www.amazon.com.au/dp/1225454?tag=beastierated-22'),
(2, 1, 'ASOS_AU', 'https://www.asos.com/product/product-name'),
(2, 1, 'Sephora_AU', 'https://www.sephora.com.au/product/product-name');

-- For US region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(2, 2, 'Amazon_US', 'https://www.amazon.com/dp/1225454?tag=beastierated-20'),
(2, 2, 'ASOS_US', 'https://www.asos.com/product/product-name'),
(2, 2, 'Sephora_US', 'https://www.sephora.com/product/product-name');

-- For UK region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(2, 3, 'Amazon_UK', 'https://www.amazon.co.uk/dp/1225454?tag=beastierated-21'),
(2, 3, 'ASOS_UK', 'https://www.asos.com/product/product-name'),
(2, 3, 'Sephora_UK', 'https://www.sephora.co.uk/product/product-name');

-- For CA region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(2, 4, 'Amazon_CA', 'https://www.amazon.ca/dp/1225454?tag=beastierated-20'),
(2, 4, 'ASOS_CA', 'https://www.asos.ca/product/product-name'),
(2, 4, 'Sephora_CA', 'https://www.sephora.ca/product/product-name');

-- For GLOBAL region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(2, 5, 'Amazon_GLOBAL', 'https://www.amazon.com/dp/1225454?tag=beastierated-20'),
(2, 5, 'ASOS_GLOBAL', 'https://www.asos.com/product/product-name'),
(2, 5, 'Sephora_GLOBAL', 'https://www.sephora.com/product/product-name');

-- Insert Affiliate Links for Video 8: 'Zig Zag Close Up'

-- For AU region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(8, 1, 'Amazon_AU', 'https://www.amazon.com.au/dp/ZIGZAG-001?tag=beastierated-22'),
(8, 1, 'ASOS_AU', 'https://www.asos.com/product/zig-zag-close-up'),
(8, 1, 'Sephora_AU', 'https://www.sephora.com.au/product/zig-zag-close-up');

-- For US region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(8, 2, 'Amazon_US', 'https://www.amazon.com/dp/ZIGZAG-001?tag=beastierated-20'),
(8, 2, 'ASOS_US', 'https://www.asos.com/product/zig-zag-close-up'),
(8, 2, 'Sephora_US', 'https://www.sephora.com/product/zig-zag-close-up');

-- For UK region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(8, 3, 'Amazon_UK', 'https://www.amazon.co.uk/dp/ZIGZAG-001?tag=beastierated-21'),
(8, 3, 'ASOS_UK', 'https://www.asos.com/product/zig-zag-close-up'),
(8, 3, 'Sephora_UK', 'https://www.sephora.co.uk/product/zig-zag-close-up');

-- For CA region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(8, 4, 'Amazon_CA', 'https://www.amazon.ca/dp/ZIGZAG-001?tag=beastierated-20'),
(8, 4, 'ASOS_CA', 'https://www.asos.ca/product/zig-zag-close-up'),
(8, 4, 'Sephora_CA', 'https://www.sephora.ca/product/zig-zag-close-up');

-- For GLOBAL region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(8, 5, 'Amazon_GLOBAL', 'https://www.amazon.com/dp/ZIGZAG-001?tag=beastierated-20'),
(8, 5, 'ASOS_GLOBAL', 'https://www.asos.com/product/zig-zag-close-up'),
(8, 5, 'Sephora_GLOBAL', 'https://www.sephora.com/product/zig-zag-close-up');

-- Insert Affiliate Links for Video 3: 'Reiciendis illo nihi'

-- For AU region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(3, 1, 'Amazon_AU', 'https://www.amazon.com.au/dp/NereaArnold?tag=beastierated-22'),
(3, 1, 'ASOS_AU', 'https://www.asos.com/product/nerea-arnold'),
(3, 1, 'Sephora_AU', 'https://www.sephora.com.au/product/nerea-arnold');

-- For US region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(3, 2, 'Amazon_US', 'https://www.amazon.com/dp/NereaArnold?tag=beastierated-20'),
(3, 2, 'ASOS_US', 'https://www.asos.com/product/nerea-arnold'),
(3, 2, 'Sephora_US', 'https://www.sephora.com/product/nerea-arnold');

-- For UK region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(3, 3, 'Amazon_UK', 'https://www.amazon.co.uk/dp/NereaArnold?tag=beastierated-21'),
(3, 3, 'ASOS_UK', 'https://www.asos.com/product/nerea-arnold'),
(3, 3, 'Sephora_UK', 'https://www.sephora.co.uk/product/nerea-arnold');

-- For CA region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(3, 4, 'Amazon_CA', 'https://www.amazon.ca/dp/NereaArnold?tag=beastierated-20'),
(3, 4, 'ASOS_CA', 'https://www.asos.ca/product/nerea-arnold'),
(3, 4, 'Sephora_CA', 'https://www.sephora.ca/product/nerea-arnold');

-- For GLOBAL region
INSERT INTO `affiliate_links` (`video_id`, `region_id`, `retailer`, `url`) VALUES
(3, 5, 'Amazon_GLOBAL', 'https://www.amazon.com/dp/NereaArnold?tag=beastierated-20'),
(3, 5, 'ASOS_GLOBAL', 'https://www.asos.com/product/nerea-arnold'),
(3, 5, 'Sephora_GLOBAL', 'https://www.sephora.com/product/nerea-arnold');
