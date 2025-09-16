

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