<?php
$is_schema = (env('APP_ENV', 'local') == 'production' || env('APP_ENV', 'local') == 'development')?true:false;
return [
    'notification'=>$is_schema?env('DB_NOTIFICATION_SCHEMA', 'public'):'public',
    'upload'=>$is_schema?env('DB_UPLOAD_SCHEMA', 'public'):'public',
    'user'=>$is_schema?env('DB_USER_SCHEMA', 'public'):'public',
];
