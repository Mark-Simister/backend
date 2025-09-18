

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

17-09-2025
My Lair page

php artisan migrate --path=/database/migrations/2025_09_17_050036_create_category_follows_table.php
php artisan migrate --path=/database/migrations/2025_09_17_071158_create_product_messages_table.php



Follow a category

curl --location 'http://127.0.0.1:8000/api/categories/10/follow' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer {your_access_token}' \
--data '{"category_id": "{categoryId}"}'

Followed categories

curl --location 'http://127.0.0.1:8000/api/followed-categories' \
--header 'Authorization: Bearer {your_access_token}'

Video Watch Record

curl --location --request POST 'http://127.0.0.1:8000/api/videos/6/watch' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU4MDgzNzUwLCJleHAiOjE3NTgwODczNTAsIm5iZiI6MTc1ODA4Mzc1MCwianRpIjoiQkU3czYzYXAxeUREb0JKVyIsInN1YiI6IjE0IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.YR_4AWnx5bjZI4-pS_v8ALAe8mfwpmP_w9Eh3d0rhDE'

Last Watched Videos

curl --location 'http://127.0.0.1:8000/api/my-watch-histories' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU4MDgzNzUwLCJleHAiOjE3NTgwODczNTAsIm5iZiI6MTc1ODA4Mzc1MCwianRpIjoiQkU3czYzYXAxeUREb0JKVyIsInN1YiI6IjE0IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.YR_4AWnx5bjZI4-pS_v8ALAe8mfwpmP_w9Eh3d0rhDE'

Send Product Message

curl --location 'http://127.0.0.1:8000/api/product-messages' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU4MDkzOTI0LCJleHAiOjE3NTgwOTc1MjQsIm5iZiI6MTc1ODA5MzkyNCwianRpIjoiR2hKNzY4d1BBYW9HZ3VxZyIsInN1YiI6IjE0IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.WYze23dg48Ldaf5K5RkKTT3Idm0wrU4XpYxJ_8pHusk' \
--header 'Cookie: XSRF-TOKEN=eyJpdiI6InR6N0RraGhzRW1ORFRMZHRKeUJFalE9PSIsInZhbHVlIjoiSEp2NmxWc1hrT0t6SW9VSm93cDMwWlNDWWlvM20rU01yOGRCbEV2SU9FQUJwdXdWZnRNNy9FekVmZXovQlk5SDF6MEFuU2xhN1dnUnFFajZ6SW51N1Nybk1NRVpJVVhWTkxSWXFuVEp4TXFyY0xndFc5aXJEbEZGbllBaXV1cXEiLCJtYWMiOiJlZGU5MWUxZmQ3Mjk1NTYxYzZhM2Y3NTg5MmEzZjA2NzhjMGU1MWZhNzdlY2FjYzEwMjhjYWU4ZmEyYzZlZGEwIiwidGFnIjoiIn0%3D; beastierated_session=eyJpdiI6InUwTGRrb1lhd1grbldiZjUwdEhCelE9PSIsInZhbHVlIjoiaUszdFhYRTNhc09WTjNQQ0IyVDlCaGpKZHhJbDd0cUZWbm5BU2E0bkw0YVE1STZVdExtUk5QdVZYbmJ4b0s2UDRYUCtKUUtzY0UxSDJTWkJPQXVKaXBVK3h4VCt4MFloUFdJaUhBZFp3VDRXbmJ0ZE1POWJNbFgyZERlalZvQk0iLCJtYWMiOiJiODA2OTI4YmExM2NmYzM0Mjk3NTJiNjY3ZjNjYWJhYzdlZjNmNGUxZDBmZGQwYWUzYmRlMTRiYjQ1NWIxYWU3IiwidGFnIjoiIn0%3D' \
--data-raw '{
        "first_name": "John Doe",
        "last_name": "Last",
        "email": "johndoe@example.com",
        "subject": "subject",
        "message": "This is a product message from a guest."
    }'


    php artisan migrate --path=/database/migrations/2025_09_17_130255_add_subject_to_product_messages_table.php

18-09-2025

Advance Search
 
curl --location 'https://bstg.beastierated.com/api/advanced-search/region/US?search_term=chan' \
--header 'Accept: application/json' \
--header 'Authorization: Bearer YOUR_API_TOKEN'

List comments for a video

curl --location 'https://bstg.beastierated.com/api/videos/3/comments' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwczovL2JzdGcuYmVhc3RpZXJhdGVkLmNvbS9hcGkvbG9naW4iLCJpYXQiOjE3NTc1Nzg2OTgsImV4cCI6MTc1NzU4MjI5OCwibmJmIjoxNzU3NTc4Njk4LCJqdGkiOiJkYjdKSUtDT0JpOXppdk5iIiwic3ViIjoiMTQiLCJwcnYiOiIwYmY2YzcxZjdjMzliODFhMmMxYjc1MTYwYzlkYTJlNTdjMmZlZDY2In0.DBc3Z4ElOh49UCxgqEjo3FdBv6g5njlZyBE2bXdh-oM'

Create a new top-level comment

curl --location 'https://bstg.beastierated.com/api/videos/3/comments' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU4MTcwMjcyLCJleHAiOjE3NTgyNTY2NzIsIm5iZiI6MTc1ODE3MDI3MiwianRpIjoiT1VFd2FuZ3RmNFpkUkVKSCIsInN1YiI6IjE0IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.RMWXU-013u4K2vta3M2TUG4b5r1iLaAVVZxq8QYdnk0' \
--data '{"body": "This is my first comment"}'

Reply to a comment

curl --location 'https://bstg.beastierated.com/api/videos/3/comments' \
--header 'Content-Type: application/json' \
--header 'Authorization: Bearer eyJ0eXAiOiJKV1QiLCJhbGciOiJIUzI1NiJ9.eyJpc3MiOiJodHRwOi8vMTI3LjAuMC4xOjgwMDAvYXBpL2xvZ2luIiwiaWF0IjoxNzU4MTcwMjcyLCJleHAiOjE3NTgyNTY2NzIsIm5iZiI6MTc1ODE3MDI3MiwianRpIjoiT1VFd2FuZ3RmNFpkUkVKSCIsInN1YiI6IjE0IiwicHJ2IjoiMGJmNmM3MWY3YzM5YjgxYTJjMWI3NTE2MGM5ZGEyZTU3YzJmZWQ2NiJ9.RMWXU-013u4K2vta3M2TUG4b5r1iLaAVVZxq8QYdnk0' \
--data '{"body": "This is a second reply to first comment", "parent_id": 9}'

Update a comment

curl --location --request PATCH 'https://bstg.beastierated.com/api/comments/6' \
--header 'Content-Type: application/json' \
--data '{"body": "Updated comment text"}'

Delete a comment

curl --location --request DELETE 'https://bstg.beastierated.com/api/comments/6'

Also

Paid Video Detail api comments and replies added,
Free Video Detail api comments and replies added,
